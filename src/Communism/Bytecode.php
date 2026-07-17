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
 * Purpose: Interact with userland bytecode                                   *
 *============================================================================*/

declare(strict_types=1);

namespace Communism;

use InvalidArgumentException;
use ReflectionException;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use RuntimeException;

use function implode;
use function is_array;
use function is_object;
use function is_string;
use function sprintf;
use function str_contains;

final class Bytecode
{
    private const TYPE_UNUSED = 0;
    private const TYPE_CONST = 1;
    private const TYPE_TMP_VAR = 2;
    private const TYPE_VAR = 4;
    private const TYPE_CV = 8;

    /**
     * @param callable $function
     */
    public static function disassemble(callable $function): string
    {
        [$reflection, $zendFunction] = self::resolveCallable($function);

        if ($zendFunction->type !== __Underlying__::ZEND_USER_FUNCTION) {
            throw new InvalidArgumentException(sprintf(
                '%s is not a userland function or method',
                self::describeReflection($reflection),
            ));
        }

        if ($zendFunction->op_array->opcodes === null) {
            throw new RuntimeException(sprintf('Bytecode is unavailable for %s', self::describeReflection($reflection)));
        }

        return self::disassembleOpArray($reflection, $zendFunction->op_array);
    }

    /**
     * @return array{0: ReflectionFunctionAbstract, 1: \Communism_FFI\zend_function}
     */
    private static function resolveCallable(callable $function): array
    {
        try {
            if ($function instanceof \Closure) {
                throw new InvalidArgumentException('Closures cannot be disassembled through lookup tables yet.');
            }

            if (is_string($function)) {
                if (str_contains($function, '::')) {
                    [$class, $method] = explode('::', $function, 2);
                    $reflection = new ReflectionMethod($class, $method);
                    $zendFunction = __Underlying__::lookupMethod($reflection->getDeclaringClass()->getName(), $reflection->getName());
                } else {
                    $reflection = new ReflectionFunction($function);
                    $zendFunction = __Underlying__::lookupFunction($reflection->getName());
                }
            } elseif (is_array($function)) {
                [$target, $method] = $function;
                $reflection = new ReflectionMethod($target, $method);
                $zendFunction = __Underlying__::lookupMethod($reflection->getDeclaringClass()->getName(), $reflection->getName());
            } elseif (is_object($function)) {
                $reflection = new ReflectionMethod($function, '__invoke');
                $zendFunction = __Underlying__::lookupMethod($reflection->getDeclaringClass()->getName(), $reflection->getName());
            } else {
                throw new InvalidArgumentException('Unsupported callable shape.');
            }
        } catch (ReflectionException $exception) {
            throw new InvalidArgumentException($exception->getMessage(), previous: $exception);
        }

        if ($zendFunction === null) {
            throw new RuntimeException(sprintf('Unable to locate runtime metadata for %s', self::describeReflection($reflection)));
        }

        return [$reflection, $zendFunction];
    }

    /**
     * @param ReflectionFunctionAbstract $reflection
     * @param \Communism_FFI\zend_op_array $opArray
     * @return string
     */
    private static function disassembleOpArray(ReflectionFunctionAbstract $reflection, object $opArray): string
    {
        $lines = [];
        $lines[] = sprintf(
            '%s:',
            self::describeReflection($reflection),
        );
        $lines[] = sprintf(
            '     ; (lines=%d, args=%d, vars=%d, tmps=%d)',
            $opArray->last,
            $opArray->num_args,
            $opArray->last_var,
            $opArray->T,
        );
        if ($opArray->filename !== null) {
            $lines[] = sprintf(
                '     ; %s:%d-%d',
                self::zendStringToString($opArray->filename),
                $opArray->line_start,
                $opArray->line_end,
            );
        }

        for ($i = 0; $i < $opArray->last; $i++) {
            // @phpstan-ignore offsetAccess.notFound
            $lines[] = sprintf('%04d %s', $i, self::formatOpcode($opArray->opcodes[$i]));
        }

        return implode(PHP_EOL, $lines) . PHP_EOL;
    }

    /**
     * @param \Communism_FFI\zend_op $opline
     * @return string
     */
    private static function formatOpcode(object $opline): string
    {
        $opcodeName = __Underlying__::opcodeName($opline->opcode);
        if ($opcodeName === '') {
            $opcodeName = sprintf('OP_%d', $opline->opcode);
        } elseif (str_contains($opcodeName, 'ZEND_')) {
            $opcodeName = substr($opcodeName, 5);
        }

        $parts = [];
        if ($opline->result_type !== self::TYPE_UNUSED) {
            $parts[] = self::formatOperand($opline->result_type, $opline->result);
            $parts[] = '=';
        }
        $parts[] = $opcodeName;

        $operand1 = self::formatOperand($opline->op1_type, $opline->op1);
        $operand2 = self::formatOperand($opline->op2_type, $opline->op2);

        if ($operand1 !== null) {
            $parts[] = $operand1;
        }

        if ($operand2 !== null) {
            $parts[] = ',';
            $parts[] = $operand2;
        }

        if ($opline->extended_value !== 0) {
            $parts[] = sprintf('[ext=%d]', $opline->extended_value);
        }

        return implode(' ', $parts);
    }

    /**
     * @param \Communism_FFI\znode_op $operand
     */
    private static function formatOperand(int $type, object $operand): ?string
    {
        return match ($type) {
            self::TYPE_UNUSED => ($operand->num === 0 ? null : sprintf('NUM(%d)', $operand->num)),
            self::TYPE_CONST => sprintf('CONST#%d', $operand->constant),
            self::TYPE_TMP_VAR => sprintf('TMP@%d', $operand->var),
            self::TYPE_VAR => sprintf('VAR@%d', $operand->var),
            self::TYPE_CV => sprintf('CV@%d', $operand->var),
            default => sprintf('TYPE(%d)@%d', $type, $operand->num),
        };
    }

    private static function describeReflection(ReflectionFunctionAbstract $reflection): string
    {
        if ($reflection instanceof ReflectionMethod) {
            return sprintf('%s::%s', $reflection->getDeclaringClass()->getName(), $reflection->getName());
        }

        return $reflection->getName();
    }

    /**
     * @param \Communism_FFI\zend_string $string
     */
    private static function zendStringToString(object $string): string
    {
        $length = $string->len;
        if ($length <= 0) {
            return '';
        }

        $ffi = __Underlying__::ffi();

        return \FFI::string($ffi->cast('char *', $string->val), $length);
    }
}
