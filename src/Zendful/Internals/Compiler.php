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
 * :: Zendful :: "When PHP doesn't provide it, we do!" ::                     *
 *----------------------------------------------------------------------------*
 * File: Compiler.php                                                         *
 * Consumer: Internal                                                         *
 * Purpose: Compile PHP source into detached, safe Zendful handles.           *
 *============================================================================*/

declare(strict_types=1);

namespace Zendful\Internals;

use FFI;
use InvalidArgumentException;
use RuntimeException;
use Throwable;
use Zendful\CompiledClassHandle;
use Zendful\CompiledFileHandle;
use Zendful\CompiledMethodHandle;

use function is_file;
use function strtolower;

use const ZEND_THREAD_SAFE;

/** The only layer allowed to own compiler FFI storage. */
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

    public static function compileFile(string $filename): CompiledFileHandle
    {
        if (!is_file($filename)) {
            throw new InvalidArgumentException(sprintf('Cannot compile missing file %s', $filename));
        }

        $ffi = Natives::ffi();
        $handle = $ffi->new('zend_file_handle');
        $globals = null;
        $options = null;
        $classTable = null;
        $functionTable = null;
        $originalArena = null;
        $checkpoint = null;
        $originalClasses = [];
        $originalFunctions = [];
        $opArray = null;
        $classEntries = [];
        $functionEntries = [];
        $classKeys = [];
        $functionKeys = [];
        $failure = null;
        $result = null;

        try {
            $ffi->zend_stream_init_filename(FFI::addr($handle), $filename);
            $globals = self::compilerGlobals($ffi);
            $options = $globals->compiler_options;
            $classTable = $globals->class_table;
            $functionTable = $globals->function_table;
            $originalArena = $globals->arena;
            $arenaAddress = $originalArena === null
                ? 0
                : $ffi->cast('uintptr_t', $originalArena)->cdata;
            $checkpointBytes = $arenaAddress !== 0 && ($arenaAddress & 7) === 0
                ? unpack('P', FFI::string($ffi->cast('char *', $originalArena), PHP_INT_SIZE))
                : false;
            $checkpointAddress = is_array($checkpointBytes) && isset($checkpointBytes[1]) && is_int($checkpointBytes[1])
                ? $checkpointBytes[1]
                : null;
            $checkpoint = $checkpointAddress !== null && ($checkpointAddress & 7) === 0
                ? $checkpointAddress
                : null;
            $originalClasses = self::keys($classTable, $ffi);
            $originalFunctions = self::keys($functionTable, $ffi);
            $globals->compiler_options = $options
                | self::ZEND_COMPILE_HANDLE_OP_ARRAY
                | self::ZEND_COMPILE_DELAYED_BINDING
                | self::ZEND_COMPILE_NO_CONSTANT_SUBSTITUTION
                | self::ZEND_COMPILE_IGNORE_OTHER_FILES
                | self::ZEND_COMPILE_IGNORE_OBSERVER
                | self::ZEND_COMPILE_WITHOUT_EXECUTION;
            if (PHP_OS_FAMILY === 'Windows') {
                $globals->compiler_options |= self::ZEND_COMPILE_IGNORE_INTERNAL_CLASSES;
            }

            $opArray = $ffi->compile_file(FFI::addr($handle), self::ZEND_INCLUDE);
            // @codeCoverageIgnoreStart
            if ($opArray === null) {
                throw new RuntimeException(sprintf('Zend could not compile %s', $filename));
            }
            // @codeCoverageIgnoreEnd

            $classEntries = self::newEntries($classTable, $originalClasses, $ffi);
            $functionEntries = self::newEntries($functionTable, $originalFunctions, $ffi);
            $classKeys = self::entryKeys($classEntries);
            $functionKeys = self::entryKeys($functionEntries);

            $classes = self::classSnapshots($classEntries, $ffi);
            $functions = self::functionSnapshots($functionEntries, $ffi);

            $result = new CompiledFileHandle(
                Executor::snapshotOpArray($opArray),
                array_map(static fn(CompiledClassHandle $class): string => strtolower($class->name), $classes),
                $classes,
                $functions,
            );
        } catch (Throwable $exception) {
            $failure = $exception;
        } finally {
            $cleanupFailure = self::cleanup(
                $ffi,
                $handle,
                $globals,
                $options,
                $opArray,
                $classEntries,
                $functionEntries,
                $classTable,
                $classKeys,
                $functionTable,
                $functionKeys,
                $originalArena,
                $checkpoint,
            );
            if ($failure === null) {
                $failure = $cleanupFailure;
            }
        }

        if ($failure !== null) {
            throw $failure;
        }

        if ($result === null) {
            // @codeCoverageIgnoreStart
            throw new RuntimeException('Compiler did not produce a result');
            // @codeCoverageIgnoreEnd
        }

        return $result;
    }

    /**
     * @param list<array{key: string, length: int, pointer: object}> $entries
     * @return list<CompiledClassHandle>
     */
    private static function classSnapshots(?array $entries, FFI $ffi): array
    {
        $classes = [];
        foreach ($entries ?? [] as $entry) {
            $class = $ffi->cast('zend_class_entry *', $entry['pointer']);
            $className = $class->name;
            if ($className === null || FFI::isNull($className)) {
                continue;
            }

            $name = self::zendString($className, $ffi);
            $methods = [];
            foreach (self::entries($class->function_table, $ffi) as $methodEntry) {
                $function = $ffi->cast('zend_function *', $methodEntry['pointer']);
                $functionName = $function->function_name;
                $opcodes = $function->op_array->opcodes;
                if ($function->type !== Natives::ZEND_FUNCTION_TYPE_USER
                    || $functionName === null
                    || FFI::isNull($functionName)
                    || $opcodes === null
                    || FFI::isNull($opcodes)
                ) {
                    // @codeCoverageIgnoreStart
                    continue;
                    // @codeCoverageIgnoreEnd
                }

                $methodName = self::zendString($functionName, $ffi);
                $methods[strtolower($methodName)] = new CompiledMethodHandle(
                    $methodName,
                    Executor::snapshotOpArray($function->op_array),
                );
            }
            $classes[] = new CompiledClassHandle($name, $methods);
        }

        return $classes;
    }

    /**
     * @param list<array{key: string, length: int, pointer: object}> $entries
     * @return list<CompiledMethodHandle>
     */
    private static function functionSnapshots(?array $entries, FFI $ffi): array
    {
        $functions = [];
        foreach ($entries ?? [] as $entry) {
            $function = $ffi->cast('zend_function *', $entry['pointer']);
            $functionName = $function->function_name;
            $opcodes = $function->op_array->opcodes;
            if ($function->type !== Natives::ZEND_FUNCTION_TYPE_USER
                || $functionName === null
                || FFI::isNull($functionName)
                || $opcodes === null
                || FFI::isNull($opcodes)
            ) {
                continue;
            }

            $name = self::zendString($functionName, $ffi);
            $functions[] = new CompiledMethodHandle($name, Executor::snapshotOpArray($function->op_array));
        }

        return $functions;
    }

    /** @phpstan-return \Zendful_FFI\zend_compiler_globals */
    private static function compilerGlobals(FFI $ffi): object
    {
        // @codeCoverageIgnoreStart
        if (ZEND_THREAD_SAFE) {
            $globals = $ffi->ts_resource_ex($ffi->compiler_globals_id, null);
            if ($globals === null || FFI::isNull($globals)) {
                throw new RuntimeException('Compiler globals are not available');
            }

            return $ffi->cast('zend_compiler_globals *', $globals);
        }
        // @codeCoverageIgnoreEnd

        return $ffi->compiler_globals;
    }

    /**
     * @phpstan-param \Zendful_FFI\HashTable|null $table
     * @return list<array{key: string, length: int, pointer: object}>
     */
    private static function entries(?object $table, FFI $ffi): array
    {
        if ($table === null || $table->arData === null) {
            return [];
        }

        $entries = [];
        for ($index = 0; $index < $table->nNumUsed; $index++) {
            $bucket = $table->arData[$index];
            $key = $bucket->key;
            if ($bucket->val->u1->v->type !== Natives::ZEND_TYPE_PTR) {
                continue;
            }
            $pointer = $bucket->val->value->ptr;
            if ($pointer === null || FFI::isNull($pointer) || $key === null) {
                continue;
            }
            if (FFI::isNull($key)) {
                // @codeCoverageIgnoreStart
                continue;
                // @codeCoverageIgnoreEnd
            }

            $entries[] = [
                'key' => self::zendString($key, $ffi),
                'length' => $key->len,
                'pointer' => $pointer,
            ];
        }

        return $entries;
    }

    /** @phpstan-param \Zendful_FFI\HashTable|null $table
     * @return array<string, int>
     */
    private static function keys(?object $table, FFI $ffi): array
    {
        $keys = [];
        foreach (self::entries($table, $ffi) as $entry) {
            $keys[$entry['key']] = $entry['length'];
        }

        return $keys;
    }

    /**
     * @phpstan-param \Zendful_FFI\HashTable|null $table
     * @param array<string, int> $original
     * @return list<array{key: string, length: int, pointer: object}>
     */
    private static function newEntries(?object $table, array $original, FFI $ffi): array
    {
        return array_values(array_filter(
            self::entries($table, $ffi),
            static fn(array $entry): bool => !isset($original[$entry['key']]),
        ));
    }

    /** @param list<array{key: string, length: int, pointer: object}> $entries
     * @return list<array{key: string, length: int}>
     */
    private static function entryKeys(array $entries): array
    {
        return array_map(
            static fn(array $entry): array => ['key' => $entry['key'], 'length' => $entry['length']],
            $entries,
        );
    }

    /**
     * @phpstan-param \Zendful_FFI\HashTable|null $table
     * @param list<array{key: string, length: int}> $entries
     */
    private static function removeEntries(?object $table, array $entries, FFI $ffi): void
    {
        if ($table === null || $table->arData === null) {
            return;
        }

        foreach ($entries as $entry) {
            $ffi->zend_hash_str_del($table, $entry['key'], $entry['length']);
        }
    }

    /**
     * Release every compiler-owned resource, retaining the first cleanup error.
     * Cleanup must continue after an individual FFI operation fails.
     *
     * @phpstan-param \Zendful_FFI\zend_compiler_globals|null $globals
     * @phpstan-param \Zendful_FFI\zend_op_array|null $opArray
     * @phpstan-param \Zendful_FFI\HashTable|null $classTable
     * @phpstan-param \Zendful_FFI\HashTable|null $functionTable
     * @phpstan-param \Zendful_FFI\zend_arena|null $originalArena
     * @param list<array{key: string, length: int, pointer: object}> $classEntries
     * @param list<array{key: string, length: int, pointer: object}> $functionEntries
     * @param list<array{key: string, length: int}> $classKeys
     * @param list<array{key: string, length: int}> $functionKeys
     * @return Throwable|null
     */
    private static function cleanup(
        FFI $ffi,
        object $handle,
        ?object $globals,
        ?int $options,
        ?object $opArray,
        array &$classEntries,
        array &$functionEntries,
        ?object $classTable,
        array $classKeys,
        ?object $functionTable,
        array $functionKeys,
        ?object $originalArena,
        ?int $checkpoint,
    ): ?Throwable {
        $failure = null;
        if ($globals !== null && $options !== null) {
            self::restoreCompilerOptions($globals, $options);
        }

        if ($opArray !== null) {
            try {
                $ffi->destroy_op_array($opArray);
                $ffi->_efree($opArray);
            } catch (Throwable $exception) {
                $failure = $exception;
            }
        }

        $classEntries = [];
        $functionEntries = [];
        try {
            self::removeEntries($classTable, $classKeys, $ffi);
            // @codeCoverageIgnoreStart
        } catch (Throwable $exception) {
            if ($failure === null) {
                $failure = $exception;
            }
        }
        // @codeCoverageIgnoreEnd
        try {
            self::removeEntries($functionTable, $functionKeys, $ffi);
        } catch (Throwable $exception) {
            if ($failure === null) {
                $failure = $exception;
            }
        }
        // @codeCoverageIgnoreStart
        try {
            $ffi->zend_destroy_file_handle(FFI::addr($handle));
        } catch (Throwable $exception) {
            if ($failure === null) {
                $failure = $exception;
            }
        }
        // @codeCoverageIgnoreEnd
        try {
            if ($globals !== null) {
                self::releaseArena($globals, $originalArena, $checkpoint, $ffi);
            }
        } catch (Throwable $exception) {
            if ($failure === null) {
                $failure = $exception;
            }
        }
        return $failure;
    }

    /**
     * @phpstan-param \Zendful_FFI\zend_compiler_globals $globals
     */
    private static function restoreCompilerOptions(object $globals, int $options): void
    {
        $globals->compiler_options = $options;
    }

    /** @phpstan-param \Zendful_FFI\zend_string $string */
    private static function zendString(object $string, FFI $ffi): string
    {
        if ($string->len <= 0) {
            return '';
        }
        if ($string->len > Natives::ZEND_MAX_SAFE_STRING_LENGTH) {
            throw new RuntimeException('Zend string exceeds Zendful safety limits.');
        }

        return FFI::string($ffi->cast('char *', $string->val), $string->len);
    }

    /**
     * @phpstan-param \Zendful_FFI\zend_compiler_globals $globals
     * @phpstan-param \Zendful_FFI\zend_arena|null $original
     * @phpstan-param int|null $checkpoint
     */
    private static function releaseArena(object $globals, ?object $original, ?int $checkpoint, FFI $ffi): void
    {
        if ($original === null || $checkpoint === null) {
            return;
        }

        $arena = $globals->arena;
        $originalAddress = $ffi->cast('uintptr_t', $original)->cdata;
        // @codeCoverageIgnoreStart
        while ($arena !== null && $ffi->cast('uintptr_t', $arena)->cdata !== $originalAddress) {
            $previous = $arena->prev;
            $ffi->_efree($arena);
            $arena = $previous;
        }

        if ($arena !== null) {
            $arena->ptr = $ffi->cast('char *', $checkpoint);
            $globals->arena = $arena;
        }
        // @codeCoverageIgnoreEnd
    }
}
