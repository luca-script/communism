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

use Communism\Internals\Zend;
use InvalidArgumentException;
use ReflectionException;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use RuntimeException;

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
        [$reflection, $zendFunction] = self::resolve($function);
        if ($zendFunction->type !== Zend::ZEND_USER_FUNCTION || $zendFunction->op_array->opcodes === null) {
            throw new InvalidArgumentException(sprintf('%s has no userland bytecode', self::describe($reflection)));
        }

        return self::decompileOpArray($zendFunction->op_array, self::describe($reflection));
    }

    /**
     * Decompile an op array returned by the Zend compiler before it has been
     * installed in a function or class table.
     *
     * @phpstan-param \Communism_FFI\zend_op_array $opArray
     * @param object $opArray A zend_op_array pointer or value exposed by FFI.
     */
    public static function decompileOpArray(object $opArray, string $name): MethodBody
    {
        $opcodes = $opArray->opcodes;
        if ($opcodes === null) {
            throw new RuntimeException('The runtime op array has no opcode storage');
        }

        $literals = $opArray->literals;
        $variableNames = self::variableNames($opArray);
        $instructions = [];
        for ($index = 0; $index < $opArray->last; $index++) {
            $opline = $opcodes[$index];
            $instructions[] = new Instruction(
                $opline->opcode,
                self::opcodeName($opline->opcode),
                self::operand($opline->result_type, $opline->result, $opline, $literals),
                self::operand($opline->op1_type, $opline->op1, $opline, $literals),
                self::operand($opline->op2_type, $opline->op2, $opline, $literals),
                $opline->extended_value,
                $opline->lineno,
                $opline->handler,
                $index,
            );
        }

        $filename = $opArray->filename === null ? null : self::zendString($opArray->filename);

        return new MethodBody(
            $name,
            $filename,
            $opArray->line_start,
            $opArray->line_end,
            $instructions,
            $variableNames,
            $opArray->T,
            $opArray->cache_size,
        );
    }

    /**
     * @phpstan-param \Communism_FFI\zend_op_array $opArray
     * @return array<int, string>
     */
    private static function variableNames(object $opArray): array
    {
        $names = [];
        if ($opArray->last_var <= 0 || $opArray->vars === null) {
            return $names;
        }

        $ffi = Zend::ffi();
        $zvalSize = \FFI::sizeof($ffi->new('zval'));
        $callFrameSlot = 5;
        for ($index = 0; $index < $opArray->last_var; $index++) {
            $names[($index + $callFrameSlot) * $zvalSize] = self::zendString($opArray->vars[$index]);
        }

        return $names;
    }

    /** @return array{0: ReflectionFunctionAbstract, 1: \Communism_FFI\zend_function} */
    private static function resolve(callable|string $function): array
    {
        try {
            if (is_string($function)) {
                if (str_contains($function, '::')) {
                    [$class, $method] = explode('::', $function, 2);
                    $reflection = new ReflectionMethod($class, $method);
                    $zendFunction = Zend::lookupMethod($reflection->getDeclaringClass()->getName(), $reflection->getName());
                } else {
                    $reflection = new ReflectionFunction($function);
                    $zendFunction = Zend::lookupFunction($reflection->getName());
                }
            } elseif (is_array($function)) {
                [$target, $method] = $function;
                $reflection = new ReflectionMethod($target, $method);
                $zendFunction = Zend::lookupMethod($reflection->getDeclaringClass()->getName(), $reflection->getName());
            } elseif (is_object($function)) {
                $reflection = new ReflectionMethod($function, '__invoke');
                $zendFunction = Zend::lookupMethod($reflection->getDeclaringClass()->getName(), $reflection->getName());
            } else {
                throw new InvalidArgumentException('Unsupported callable shape');
            }
        } catch (ReflectionException $exception) {
            throw new InvalidArgumentException($exception->getMessage(), previous: $exception);
        }

        if ($zendFunction === null) {
            throw new RuntimeException(sprintf('Unable to locate runtime metadata for %s', self::describe($reflection)));
        }

        return [$reflection, $zendFunction];
    }

    /**
     * @phpstan-param \Communism_FFI\znode_op $operand
     * @phpstan-param \Communism_FFI\zend_op $opline
     * @phpstan-param object|null $literals
     */
    private static function operand(int $type, object $operand, object $opline, ?object $literals): Operand
    {
        return match ($type) {
            0 => Operand::unused($operand->num),
            1 => self::constantOperand($opline, $operand, $literals),
            2 => Operand::temporary($operand->var),
            4 => Operand::variable($operand->var),
            8 => Operand::cv($operand->var),
            default => Operand::raw($type, $operand->num),
        };
    }

    private static function opcodeName(int $opcode): string
    {
        $name = Zend::opcodeName($opcode);

        return str_contains($name, 'ZEND_') ? substr($name, 5) : $name;
    }

    private static function constant(object $opline, int $offset): mixed
    {
        $ffi = Zend::ffi();
        $address = $ffi->cast('char *', \FFI::addr($opline));
        $zval = $ffi->cast('zval *', $ffi->cast('char *', $ffi->cast('uintptr_t', $address)->cdata + $offset))[0];
        $type = $zval->u1->v->type;

        return match ($type) {
            1 => null,
            2 => false,
            3 => true,
            4 => $zval->value->lval,
            5 => $zval->value->dval,
            6 => self::zendString($ffi->cast('zend_string *', $zval->value->ptr)),
            7 => self::packedArray($zval),
            default => sprintf('TYPE(%d)', $type),
        };
    }

    /** @phpstan-param \Communism_FFI\znode_op $operand */
    private static function constantOperand(object $opline, object $operand, ?object $literals): Operand
    {
        if (PHP_INT_SIZE === 4) {
            if ($operand->zv === null) {
                throw new RuntimeException('A constant operand has no absolute zval address');
            }

            $zval = $operand->zv[0];
            $rawAddress = Zend::ffi()->cast('uintptr_t', $operand->zv)->cdata;

            return Operand::constant(
                self::constantValue($zval),
                $rawAddress,
                self::literalIndexFromAddress($rawAddress, $literals),
            );
        }

        return Operand::constant(
            self::constant($opline, $operand->constant),
            $operand->constant,
            self::literalIndex($opline, $operand->constant, $literals),
        );
    }

    /** @phpstan-param \Communism_FFI\zval $zval */
    /** @phpstan-param \Communism_FFI\zval $zval */
    private static function constantValue(object $zval): mixed
    {
        $type = $zval->u1->v->type;

        return match ($type) {
            1 => null,
            2 => false,
            3 => true,
            4 => $zval->value->lval,
            5 => $zval->value->dval,
            6 => self::zendString(Zend::ffi()->cast('zend_string *', $zval->value->ptr)),
            7 => self::packedArray($zval),
            default => sprintf('TYPE(%d)', $type),
        };
    }

    /**
     * @phpstan-param \Communism_FFI\zval $zval
     * @return list<mixed>
     */
    private static function packedArray(object $zval): array
    {
        $pointer = $zval->value->ptr;
        if ($pointer === null) {
            return [];
        }

        $array = Zend::ffi()->cast('HashTable *', $pointer);
        $values = [];
        for ($index = 0; $index < $array->nNumOfElements; $index++) {
            $values[] = self::constantValue($array->arPacked[$index]);
        }

        return $values;
    }

    private static function literalIndex(object $opline, int $offset, ?object $literals): int
    {
        if ($literals === null) {
            throw new RuntimeException('A constant operand has no literal pool');
        }

        $ffi = Zend::ffi();
        $oplineAddress = $ffi->cast('uintptr_t', \FFI::addr($opline))->cdata;
        $literalAddress = $ffi->cast('uintptr_t', $literals)->cdata;
        $zvalSize = \FFI::sizeof($ffi->new('zval'));
        $distance = $oplineAddress + $offset - $literalAddress;

        return self::literalIndexFromDistance($distance, $zvalSize);
    }

    private static function literalIndexFromAddress(int $address, ?object $literals): int
    {
        if ($literals === null) {
            throw new RuntimeException('A constant operand has no literal pool');
        }

        $ffi = Zend::ffi();
        $literalAddress = $ffi->cast('uintptr_t', $literals)->cdata;
        $zvalSize = \FFI::sizeof($ffi->new('zval'));

        return self::literalIndexFromDistance($address - $literalAddress, $zvalSize);
    }

    private static function literalIndexFromDistance(int $distance, int $zvalSize): int
    {

        if ($distance < 0 || $distance % $zvalSize !== 0) {
            throw new RuntimeException('A constant operand does not point into its literal pool');
        }

        return intdiv($distance, $zvalSize);
    }

    /** @phpstan-param \Communism_FFI\zend_string $string */
    private static function zendString(object $string): string
    {
        return \FFI::string(Zend::ffi()->cast('char *', $string->val), $string->len);
    }

    private static function describe(ReflectionFunctionAbstract $reflection): string
    {
        if ($reflection instanceof ReflectionMethod) {
            return $reflection->getDeclaringClass()->getName() . '::' . $reflection->getName();
        }

        return $reflection->getName();
    }
}
