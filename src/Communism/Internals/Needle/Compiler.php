<?php

/*============================================================================*
 * SPDX-License-Identifier: 0BSD                                              *
 * SPDX-FileCopyrightText: 2026 Luca Mollema                                  *
 * Copyright (C) 2026 Luca Mollema                                            *
 *                                                                            *
 * Permission to use, copy, modify, and/or distribute this software for any   *
 * purpose with or without fee is hereby granted.                             *
 *                                                                            *
 * THE SOFTWARE IS PROVIDED “AS IS” AND THE AUTHOR DISCLAIMS ALL WARRANTIES   *
 * WITH REGARD TO THIS SOFTWARE INCLUDING ALL IMPLIED WARRANTIES OF           *
 * MERCHANTABILITY AND FITNESS. IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR    *
 * ANY SPECIAL, DIRECT, INDIRECT, OR CONSEQUENTIAL DAMAGES OR ANY DAMAGES     *
 * WHATSOEVER RESULTING FROM LOSS OF USE, DATA OR PROFITS, WHETHER IN AN      *
 * ACTION OF CONTRACT, NEGLIGENCE OR OTHER TORTIOUS ACTION, ARISING OUT OF OR *
 * IN CONNECTION WITH THE USE OR PERFORMANCE OF THIS SOFTWARE.                *
 *============================================================================*
 * :: Communism :: "In comrade PHP, all are public" ::                        *
 *----------------------------------------------------------------------------*
 * File: Compiler.php                                                         *
 * Consumer: Internal                                                         *
 * Purpose: Compile PHP files to detached, non-executing bytecode snapshots.  *
 *============================================================================*/

declare(strict_types=1);

namespace Communism\Internals\Needle;

use Communism\Internals\Zend;
use InvalidArgumentException;
use RuntimeException;

use function is_file;
use function strtolower;

/** Internal compiler bridge; normal users should use the Mixin API instead. */
final class Compiler
{
    private const int ZEND_INCLUDE = 1 << 1;
    private const int ZEND_COMPILE_HANDLE_OP_ARRAY = 1 << 2;
    private const int ZEND_COMPILE_IGNORE_INTERNAL_CLASSES = 1 << 4;
    private const int ZEND_COMPILE_DELAYED_BINDING = 1 << 5;
    private const int ZEND_COMPILE_NO_CONSTANT_SUBSTITUTION = 1 << 6;
    private const int ZEND_COMPILE_IGNORE_OTHER_FILES = 1 << 13;
    private const int ZEND_COMPILE_WITHOUT_EXECUTION = 1 << 14;
    private const int ZEND_COMPILE_IGNORE_OBSERVER = 1 << 18;

    public static function compileFile(string $filename): CompiledFile
    {
        if (!is_file($filename)) {
            throw new InvalidArgumentException(sprintf('Cannot compile missing file %s', $filename));
        }

        $ffi = Zend::ffi();
        $handle = $ffi->new('zend_file_handle');
        $ffi->zend_stream_init_filename(\FFI::addr($handle), $filename);

        $compilerGlobals = Zend::compilerGlobals();
        $originalOptions = $compilerGlobals->compiler_options;
        $classTable = $compilerGlobals->class_table;
        $functionTable = $compilerGlobals->function_table;
        $originalArena = $compilerGlobals->arena;
        $arenaCheckpoint = $originalArena?->ptr;
        $originalClassKeys = self::keys($classTable);
        $originalFunctionKeys = self::keys($functionTable);
        /** @var list<array{key: string, length: int, pointer: object}> $compiledClasses */
        $compiledClasses = [];
        /** @var list<array{key: string, length: int, pointer: object}> $compiledFunctions */
        $compiledFunctions = [];
        /** @var list<array{key: string, length: int}> $compiledClassKeys */
        $compiledClassKeys = [];
        /** @var list<array{key: string, length: int}> $compiledFunctionKeys */
        $compiledFunctionKeys = [];
        $opArray = null;

        try {
            $compilerGlobals->compiler_options = $originalOptions
                | self::ZEND_COMPILE_HANDLE_OP_ARRAY
                | self::ZEND_COMPILE_DELAYED_BINDING
                | self::ZEND_COMPILE_NO_CONSTANT_SUBSTITUTION
                | self::ZEND_COMPILE_IGNORE_OTHER_FILES
                | self::ZEND_COMPILE_IGNORE_OBSERVER
                | self::ZEND_COMPILE_WITHOUT_EXECUTION;
            if (PHP_OS_FAMILY === 'Windows') {
                $compilerGlobals->compiler_options |= self::ZEND_COMPILE_IGNORE_INTERNAL_CLASSES;
            }
            $opArray = $ffi->compile_file(\FFI::addr($handle), self::ZEND_INCLUDE);
            if ($opArray === null) {
                throw new RuntimeException(sprintf('Zend could not compile %s', $filename));
            }

            // Capture this before decompilation. Decompiling can autoload
            // helper classes, and those must not be mistaken for declarations
            // from the file being inspected.
            $compiledClasses = self::newEntries($classTable, $originalClassKeys);
            $compiledFunctions = self::newEntries($functionTable, $originalFunctionKeys);
            $compiledClassKeys = self::entryKeys($compiledClasses);
            $compiledFunctionKeys = self::entryKeys($compiledFunctions);
            $body = Decompiler::decompileOpArray($opArray, $filename);
            $classes = self::newClasses($compiledClasses);
            $functions = self::newFunctions($compiledFunctions);

            return new CompiledFile(
                $body,
                array_map(static fn(CompiledClass $class): string => strtolower($class->name), $classes),
                $classes,
                $functions,
            );
        } finally {
            $compilerGlobals->compiler_options = $originalOptions;
            if ($opArray !== null) {
                $ffi->destroy_op_array($opArray);
                $ffi->_efree($opArray);
            }

            // Release declaration pointers before mutating the tables holding
            // their Buckets. Cleanup only needs the copied key data.
            $compiledClasses = [];
            $compiledFunctions = [];
            self::removeEntries($classTable, $compiledClassKeys);
            self::removeEntries($functionTable, $compiledFunctionKeys);

            $ffi->zend_destroy_file_handle(\FFI::addr($handle));
            self::releaseArena($compilerGlobals, $originalArena, $arenaCheckpoint);
        }
    }

    /**
     * @param list<array{key: string, length: int, pointer: object}> $entries
     * @return list<CompiledClass>
     */
    private static function newClasses(array $entries): array
    {
        $ffi = Zend::ffi();
        $classes = [];
        foreach ($entries as $entry) {
            $class = $ffi->cast('zend_class_entry *', $entry['pointer']);
            if ($class->name === null) {
                continue;
            }

            $name = self::zendString($class->name);
            $classes[] = new CompiledClass($name, self::methods($class, $name));
        }

        return $classes;
    }

    /**
     * @param list<array{key: string, length: int, pointer: object}> $entries
     * @return list<CompiledMethod>
     */
    private static function newFunctions(array $entries): array
    {
        $ffi = Zend::ffi();
        $functions = [];
        foreach ($entries as $entry) {
            $function = $ffi->cast('zend_function *', $entry['pointer']);
            if ($function->function_name === null || $function->op_array->opcodes === null) {
                continue;
            }

            $name = self::zendString($function->function_name);
            $functions[] = new CompiledMethod($name, Decompiler::decompileOpArray($function->op_array, $name));
        }

        return $functions;
    }

    /**
     * @phpstan-param \Communism_FFI\zend_class_entry $class
     * @param object $class A zend_class_entry pointer.
     * @return array<string, CompiledMethod>
     */
    private static function methods(object $class, string $className): array
    {
        $table = $class->function_table;
        if ($table->arData === null) {
            return [];
        }

        $ffi = Zend::ffi();
        $entries = self::entries($table);
        $methods = [];
        foreach ($entries as $entry) {
            $function = $ffi->cast('zend_function *', $entry['pointer']);
            if ($function->function_name === null || $function->op_array->opcodes === null) {
                continue;
            }

            $name = self::zendString($function->function_name);
            $methods[strtolower($name)] = new CompiledMethod(
                $name,
                Decompiler::decompileOpArray($function->op_array, $className . '::' . $name),
            );
        }

        return $methods;
    }

    /** @phpstan-param \Communism_FFI\zend_string $string */
    private static function zendString(object $string): string
    {
        $ffi = Zend::ffi();

        return \FFI::string($ffi->cast('char *', $string->val), $string->len);
    }

    /**
     * @param list<array{key: string, length: int}> $entries
     * @phpstan-param \Communism_FFI\HashTable|null $table
     */
    private static function removeEntries(?object $table, array $entries): void
    {
        if ($table === null || $table->arData === null) {
            return;
        }

        $ffi = Zend::ffi();
        foreach ($entries as $entry) {
            $ffi->zend_hash_str_del($table, $entry['key'], $entry['length']);
        }
    }

    /**
     * Snapshot all live string-keyed entries before any operation can resize
     * the hash table. The pointer is to the heap-owned declaration, not to the
     * Bucket storage, so it remains valid while the table is being inspected.
     *
     * @return list<array{key: string, length: int, pointer: object}>
     * @phpstan-param \Communism_FFI\HashTable|null $table
     */
    private static function entries(?object $table): array
    {
        if ($table === null || $table->arData === null) {
            return [];
        }

        $ffi = Zend::ffi();
        $entries = [];
        for ($index = 0; $index < $table->nNumUsed; $index++) {
            $bucket = $table->arData[$index];
            $pointer = $bucket->val->value->ptr;
            if ($pointer === null || $bucket->key === null) {
                continue;
            }

            $entries[] = [
                'key' => \FFI::string($ffi->cast('char *', $bucket->key->val), $bucket->key->len),
                'length' => $bucket->key->len,
                'pointer' => $pointer,
            ];
        }
        $bucket = null;

        return $entries;
    }

    /**
     * @phpstan-param \Communism_FFI\HashTable|null $table
     * @return array<string, int>
     */
    private static function keys(?object $table): array
    {
        $keys = [];
        foreach (self::entries($table) as $entry) {
            $keys[$entry['key']] = $entry['length'];
        }

        return $keys;
    }

    /**
     * @param list<array{key: string, length: int, pointer: object}> $entries
     * @return list<array{key: string, length: int}>
     */
    private static function entryKeys(array $entries): array
    {
        return array_map(
            static fn(array $entry): array => [
                'key' => $entry['key'],
                'length' => $entry['length'],
            ],
            $entries,
        );
    }

    /**
     * @param array<string, int> $originalKeys
     * @phpstan-param \Communism_FFI\HashTable|null $table
     * @return list<array{key: string, length: int, pointer: object}>
     */
    private static function newEntries(?object $table, array $originalKeys): array
    {
        return array_values(array_filter(
            self::entries($table),
            static fn(array $entry): bool => !isset($originalKeys[$entry['key']]),
        ));
    }

    /**
     * @phpstan-param \Communism_FFI\zend_compiler_globals $compilerGlobals
     * @phpstan-param \Communism_FFI\zend_arena|null $originalArena
     */
    private static function releaseArena(
        object $compilerGlobals,
        ?object $originalArena,
        ?object $checkpoint,
    ): void {
        if ($originalArena === null || $checkpoint === null) {
            return;
        }

        $ffi = Zend::ffi();
        $arena = $compilerGlobals->arena;
        $originalAddress = $ffi->cast('uintptr_t', $originalArena)->cdata;
        while ($arena !== null && $ffi->cast('uintptr_t', $arena)->cdata !== $originalAddress) {
            $previous = $arena->prev;
            $ffi->_efree($arena);
            $arena = $previous;
        }

        if ($arena !== null) {
            $arena->ptr = $checkpoint;
            $compilerGlobals->arena = $arena;
        }
    }
}
