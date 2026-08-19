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
 * File: Zendful.php                                                          *
 * Consumer: Internal                                                         *
 * Purpose: Source file for Zendful.php.                                      *
 *============================================================================*/

declare(strict_types=1);

namespace Zendful;

/** Safe, typed entry point for Zend runtime operations. */
final class Zendful
{
    public static function function(string $name): FunctionHandle
    {
        if ($name === '' || str_contains($name, "\0")) {
            throw new \InvalidArgumentException('Function names must not be empty or contain NUL bytes.');
        }
        if (!function_exists($name)) {
            throw new \InvalidArgumentException(sprintf('Function %s does not exist.', $name));
        }

        return new FunctionHandle($name);
    }

    /** Resolve a PHP 8.6+ frameless-call dispatch-table entry. */
    public static function framelessFunction(int $index): ?FunctionHandle
    {
        return Internals\Executor::framelessFunction($index);
    }

    public static function method(string $className, string $methodName): MethodHandle
    {
        if ($className === '' || $methodName === '' || str_contains($className, "\0") || str_contains($methodName, "\0")) {
            throw new \InvalidArgumentException('Method names must not be empty or contain NUL bytes.');
        }
        if (!self::loadedTypeExists($className) || !method_exists($className, $methodName)) {
            throw new \InvalidArgumentException(sprintf('Method %s::%s does not exist.', $className, $methodName));
        }

        return new MethodHandle($className, $methodName);
    }

    public static function property(string $className, string $propertyName): PropertyHandle
    {
        if ($className === '' || $propertyName === '' || str_contains($className, "\0") || str_contains($propertyName, "\0")) {
            throw new \InvalidArgumentException('Property names must not be empty or contain NUL bytes.');
        }
        if (!self::loadedTypeExists($className) || !property_exists($className, $propertyName)) {
            throw new \InvalidArgumentException(sprintf('Property %s::$%s does not exist.', $className, $propertyName));
        }

        return new PropertyHandle($className, $propertyName);
    }

    public static function class(string $className): ClassHandle
    {
        if ($className === '' || str_contains($className, "\0")) {
            throw new \InvalidArgumentException('Class names must not be empty or contain NUL bytes.');
        }
        if (!self::loadedTypeExists($className)) {
            throw new \InvalidArgumentException(sprintf('Class %s does not exist.', $className));
        }

        return new ClassHandle($className);
    }

    public static function compileFile(string $filename): CompiledFileHandle
    {
        return CompilerHandle::compileFile($filename);
    }

    public static function opcodeId(string $opcode): int
    {
        if ($opcode === '' || str_contains($opcode, "\0")) {
            throw new \InvalidArgumentException('Opcode names must not be empty or contain NUL bytes.');
        }

        return OpcodeHandle::id($opcode);
    }

    public static function assemble(OpArrayHandle $target, AssemblyPlanHandle $plan): void
    {
        $target->assemble($plan);
    }

    public static function disableJitForMethod(MethodHandle $method): void
    {
        $method->disableJit();
    }

    public static function disableJitForFunction(FunctionHandle $function): void
    {
        $function->disableJit();
    }

    public static function disableJitForClass(ClassHandle $class): void
    {
        $class->disableJit();
    }

    private static function loadedTypeExists(string $name): bool
    {
        foreach (array_merge(get_declared_classes(), get_declared_interfaces(), get_declared_traits()) as $declaredType) {
            if (strcasecmp($declaredType, $name) === 0) {
                return true;
            }
        }

        return false;
    }

}
