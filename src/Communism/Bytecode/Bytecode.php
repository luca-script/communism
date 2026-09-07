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
 * File: Bytecode.php                                                         *
 * Consumer: Users                                                            *
 * Purpose: Interact with userland bytecode                                   *
 *============================================================================*/

declare(strict_types=1);

namespace Communism\Bytecode;

use InvalidArgumentException;
use ReflectionException;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use RuntimeException;
use Zendful\FunctionHandle;
use Zendful\MethodHandle;
use Zendful\OpArrayHandle;
use Zendful\OpcodeHandle;
use Zendful\OperandHandle;
use Zendful\Zendful;

use function implode;
use function is_array;
use function is_object;
use function is_string;
use function sprintf;
use function str_contains;

final class Bytecode
{
    /**
     * @param callable|string $function
     */
    public static function disassemble(callable|string $function): string
    {
        [$reflection, $source] = self::resolveCallable($function);

        if (!$source->isUserDefined()) {
            throw new InvalidArgumentException(sprintf(
                '%s is not a userland function or method',
                self::describeReflection($reflection),
            ));
        }

        if (!$source->hasBytecode()) {
            // Only malformed/incomplete runtime metadata can reach this for userland code.
            // @codeCoverageIgnoreStart
            throw new RuntimeException(sprintf('Bytecode is unavailable for %s', self::describeReflection($reflection)));
            // @codeCoverageIgnoreEnd
        }

        return self::disassembleOpArray($reflection, $source->opArray());
    }

    /**
     * @return array{0: ReflectionFunctionAbstract, 1: FunctionHandle|MethodHandle}
     */
    private static function resolveCallable(callable|string $function): array
    {
        try {
            $source = null;
            if ($function instanceof \Closure) {
                throw new InvalidArgumentException('Closures cannot be disassembled through lookup tables yet.');
            }

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
                throw new InvalidArgumentException('Unsupported callable shape.');
            }
        } catch (ReflectionException $exception) {
            throw new InvalidArgumentException($exception->getMessage(), previous: $exception);
        }

        return [$reflection, $source];
    }

    /**
     * @param ReflectionFunctionAbstract $reflection
     * @param OpArrayHandle $opArray
     * @return string
     */
    private static function disassembleOpArray(ReflectionFunctionAbstract $reflection, OpArrayHandle $opArray): string
    {
        $lines = [];
        $lines[] = sprintf(
            '%s:',
            self::describeReflection($reflection),
        );
        $lines[] = sprintf(
            '     ; (lines=%d, args=%d, vars=%d, tmps=%d)',
            $opArray->instructionCount(),
            $opArray->argumentCount(),
            $opArray->variableCount(),
            $opArray->temporaryCount(),
        );
        if ($opArray->filename() !== null) {
            $lines[] = sprintf(
                '     ; %s:%d-%d',
                $opArray->filename(),
                $opArray->lineStart(),
                $opArray->lineEnd(),
            );
        }

        for ($i = 0; $i < $opArray->instructionCount(); $i++) {
            $lines[] = sprintf('%04d %s', $i, self::formatOpcode($opArray->opcode($i)));
        }

        return implode(PHP_EOL, $lines) . PHP_EOL;
    }

    /**
     * @param OpcodeHandle $opline
     * @return string
     */
    private static function formatOpcode(OpcodeHandle $opline): string
    {
        $opcodeName = $opline->name();
        if ($opcodeName === '') {
            // OpcodeHandle validation rejects empty names in the available backend.
            // @codeCoverageIgnoreStart
            $opcodeName = sprintf('OP_%d', $opline->opcode());
            // @codeCoverageIgnoreEnd
        } elseif (str_contains($opcodeName, 'ZEND_')) {
            $opcodeName = substr($opcodeName, 5);
        }

        $parts = [];
        if ($opline->result()->type() !== OperandHandle::TYPE_UNUSED) {
            $parts[] = self::formatOperand($opline->result());
            $parts[] = '=';
        }
        $parts[] = $opcodeName;

        $operand1 = self::formatOperand($opline->operand1());
        $operand2 = self::formatOperand($opline->operand2());

        if ($operand1 !== null) {
            $parts[] = $operand1;
        }

        if ($operand2 !== null) {
            $parts[] = ',';
            $parts[] = $operand2;
        }

        if ($opline->extendedValue() !== 0) {
            $parts[] = sprintf('[ext=%d]', $opline->extendedValue());
        }

        return implode(' ', $parts);
    }

    /**
     * @param OperandHandle $operand
     */
    private static function formatOperand(OperandHandle $operand): ?string
    {
        $type = $operand->type();

        return match ($type) {
            OperandHandle::TYPE_UNUSED => ($operand->number() === 0 ? null : sprintf('NUM(%d)', $operand->number())),
            OperandHandle::TYPE_CONST => $operand->constantDescription(),
            OperandHandle::TYPE_TMP_VAR => sprintf('TMP@%d', $operand->variable()),
            OperandHandle::TYPE_VAR => sprintf('VAR@%d', $operand->variable()),
            OperandHandle::TYPE_CV => sprintf('CV@%d', $operand->variable()),
            default => sprintf('TYPE(%d)@%d', $type, $operand->number()),
        };
    }

    private static function describeReflection(ReflectionFunctionAbstract $reflection): string
    {
        if ($reflection instanceof ReflectionMethod) {
            return sprintf('%s::%s', $reflection->getDeclaringClass()->getName(), $reflection->getName());
        }

        return $reflection->getName();
    }

}
