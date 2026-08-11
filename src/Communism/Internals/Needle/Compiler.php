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
        $originalClassCount = $classTable === null ? 0 : $classTable->nNumUsed;
        $originalFunctionCount = $functionTable === null ? 0 : $functionTable->nNumUsed;
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

            // Freeze these bounds before decompilation autoloads any helper
            // classes into Zend's request-local tables.
            $compiledClassEnd = $classTable === null ? 0 : $classTable->nNumUsed;
            $compiledFunctionEnd = $functionTable === null ? 0 : $functionTable->nNumUsed;
            $body = Decompiler::decompileOpArray($opArray, $filename);
            $classes = self::newClasses($classTable, $originalClassCount, $compiledClassEnd);
            $functions = self::newFunctions($functionTable, $originalFunctionCount, $compiledFunctionEnd);

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

            self::removeEntries($classTable, $originalClassCount, $compiledClassEnd ?? $originalClassCount);
            self::removeEntries($functionTable, $originalFunctionCount, $compiledFunctionEnd ?? $originalFunctionCount);

            $ffi->zend_destroy_file_handle(\FFI::addr($handle));
            self::releaseArena($compilerGlobals, $originalArena, $arenaCheckpoint);
        }
    }

    /**
     * @phpstan-param \Communism_FFI\HashTable|null $classTable
     * @return list<CompiledClass>
     */
    private static function newClasses(?object $classTable, int $originalCount, int $end): array
    {
        if ($classTable === null || $classTable->arData === null) {
            return [];
        }

        $ffi = Zend::ffi();
        $classes = [];
        for ($index = $originalCount; $index < $end; $index++) {
            $bucket = $classTable->arData[$index];
            $pointer = $bucket->val->value->ptr;
            if ($pointer === null) {
                continue;
            }

            $class = $ffi->cast('zend_class_entry *', $pointer);
            if ($class->name === null) {
                continue;
            }

            $name = self::zendString($class->name);
            $classes[] = new CompiledClass($name, self::methods($class, $name));
        }

        return $classes;
    }

    /**
     * @phpstan-param \Communism_FFI\HashTable|null $functionTable
     * @return list<CompiledMethod>
     */
    private static function newFunctions(?object $functionTable, int $originalCount, int $end): array
    {
        if ($functionTable === null || $functionTable->arData === null) {
            return [];
        }

        $ffi = Zend::ffi();
        $functions = [];
        for ($index = $originalCount; $index < $end; $index++) {
            $bucket = $functionTable->arData[$index];
            $pointer = $bucket->val->value->ptr;
            if ($pointer === null) {
                continue;
            }

            $function = $ffi->cast('zend_function *', $pointer);
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
        $methods = [];
        for ($index = 0; $index < $table->nNumUsed; $index++) {
            $pointer = $table->arData[$index]->val->value->ptr;
            if ($pointer === null) {
                continue;
            }

            $function = $ffi->cast('zend_function *', $pointer);
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

    /** @phpstan-param \Communism_FFI\HashTable|null $table */
    private static function removeEntries(?object $table, int $originalCount, int $end): void
    {
        if ($table === null || $table->arData === null) {
            return;
        }

        $buckets = $table->arData;
        $ffi = Zend::ffi();
        for ($index = $end - 1; $index >= $originalCount; $index--) {
            $bucket = $buckets[$index];
            if ($bucket->val->value->ptr === null) {
                continue;
            }

            $ffi->zend_hash_del_bucket($table, \FFI::addr($bucket));
        }
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
