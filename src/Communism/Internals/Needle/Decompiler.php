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
 * File: Decompiler.php                                                       *
 * Consumer: Internal                                                         *
 * Purpose: Source file for Decompiler.php.                                   *
 *============================================================================*/

declare(strict_types=1);

namespace Communism\Internals\Needle;

use InvalidArgumentException;
use ReflectionException;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use RuntimeException;
use Zendful\CompiledOpArrayHandle;
use Zendful\FunctionHandle;
use Zendful\MethodHandle;
use Zendful\OpArrayHandle;
use Zendful\OperandHandle;
use Zendful\Zendful;

use function preg_match;
use function is_array;
use function is_object;
use function is_string;
use function sprintf;
use function str_contains;

final class Decompiler
{
    /**
     * @param callable|string $function
     */
    public static function decompile(callable|string $function): MethodBody
    {
        [$reflection, $source] = self::resolve($function);
        if (!$source->isUserDefined() || !$source->hasBytecode()) {
            throw new InvalidArgumentException(sprintf('%s has no userland bytecode', self::describe($reflection)));
        }

        return self::decompileHandle($source->opArray(), self::describe($reflection));
    }

    private static function decompileHandle(OpArrayHandle|CompiledOpArrayHandle $opArray, string $name): MethodBody
    {
        $instructions = [];
        for ($index = 0; $index < $opArray->instructionCount(); $index++) {
            $opline = $opArray->opcode($index);
            $opcodeName = self::handleOpcodeName($opline->name(), $opline->opcode());
            $framelessFunction = preg_match('/^FRAMELESS_ICALL_[0-3]$/', $opcodeName) === 1
                ? Zendful::framelessFunction($opline->extendedValue())
                : null;
            $instructions[] = new Instruction(
                $opline->opcode(),
                $opcodeName,
                self::handleOperand($opline->result()),
                self::handleOperand($opline->operand1()),
                self::handleOperand($opline->operand2()),
                $opline->extendedValue(),
                $opline->line(),
                null,
                $index,
                $framelessFunction?->name(),
            );
        }

        return new MethodBody(
            $name,
            $opArray->filename(),
            $opArray->lineStart(),
            $opArray->lineEnd(),
            $instructions,
            $opArray->variableNames(),
            $opArray->temporaryCount(),
            $opArray->cacheSize(),
        );
    }

    private static function handleOperand(OperandHandle $operand): Operand
    {
        return match ($operand->type()) {
            OperandHandle::TYPE_UNUSED => Operand::unused($operand->number()),
            OperandHandle::TYPE_CONST => Operand::constant($operand->value(), $operand->constant(), $operand->literalIndex()),
            OperandHandle::TYPE_TMP_VAR => Operand::temporary($operand->variable()),
            OperandHandle::TYPE_VAR => Operand::variable($operand->variable()),
            OperandHandle::TYPE_CV => Operand::cv($operand->variable()),
            default => Operand::raw($operand->type(), $operand->number()),
        };
    }

    private static function handleOpcodeName(string $name, int $opcode): string
    {
        if ($name === '') {
            // @codeCoverageIgnoreStart
            return sprintf('OP_%d', $opcode);
            // @codeCoverageIgnoreEnd
        }

        return str_contains($name, 'ZEND_') ? substr($name, 5) : $name;
    }

    public static function decompileCompiledOpArray(CompiledOpArrayHandle $opArray, string $name): MethodBody
    {
        return self::decompileHandle($opArray, $name);
    }

    /** @return array{0: ReflectionFunctionAbstract, 1: FunctionHandle|MethodHandle} */
    private static function resolve(callable|string $function): array
    {
        try {
            if (is_string($function)) {
                if (str_contains($function, '::')) {
                    [$class, $method] = explode('::', $function, 2);
                    $reflection = new ReflectionMethod($class, $method);
                    $source = Zendful::method($reflection->getDeclaringClass()->getName(), $reflection->getName());
                } else {
                    $reflection = new ReflectionFunction($function);
                    $source = Zendful::function($reflection->getName());
                }
            } elseif (is_array($function)) {
                [$target, $method] = $function;
                $reflection = new ReflectionMethod($target, $method);
                $source = Zendful::method($reflection->getDeclaringClass()->getName(), $reflection->getName());
            } elseif (is_object($function)) {
                $reflection = new ReflectionMethod($function, '__invoke');
                $source = Zendful::method($reflection->getDeclaringClass()->getName(), $reflection->getName());
            } else {
                throw new InvalidArgumentException('Unsupported callable shape');
            }
        } catch (ReflectionException $exception) {
            throw new InvalidArgumentException($exception->getMessage(), previous: $exception);
        }

        if (!$source->exists()) {
            // @codeCoverageIgnoreStart
            throw new RuntimeException(sprintf('Unable to locate runtime metadata for %s', self::describe($reflection)));
            // @codeCoverageIgnoreEnd
        }

        return [$reflection, $source];
    }

    private static function describe(ReflectionFunctionAbstract $reflection): string
    {
        if ($reflection instanceof ReflectionMethod) {
            return $reflection->getDeclaringClass()->getName() . '::' . $reflection->getName();
        }

        return $reflection->getName();
    }
}
