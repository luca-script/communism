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
 * File: Assembler.php                                                        *
 * Consumer: Internal                                                         *
 * Purpose: Source file for Assembler.php.                                    *
 *============================================================================*/

declare(strict_types=1);

namespace Communism\Internals\Needle;

use Communism\Internals\Zend;
use FFI;
use RuntimeException;
use Throwable;

use function count;
use function is_bool;
use function is_float;
use function is_int;
use function is_null;
use function is_string;
use function max;
use function spl_object_id;
use function strtolower;

/**
 * Rebuilds the runtime storage owned by a zend_op_array.
 *
 * This is deliberately an internal Needle API. PHP's pass_two() places the
 * literal pool directly after the opcode array on 64-bit builds, so changing
 * either list in place is not sufficient: every relative constant address
 * must be calculated against the new allocation.
 */
final class Assembler
{
    /** @phpstan-param \Communism_FFI\zend_op_array $opArray */
    public static function write(MethodBody $body, object $opArray): void
    {
        $ffi = Zend::ffi();
        $oldOpcodes = $opArray->opcodes;
        if ($oldOpcodes === null) {
            throw new RuntimeException('The runtime op array has no opcode storage');
        }

        $oldInstructionCount = $opArray->last;
        $oldLiteralCount = $opArray->last_literal;
        $opArray->T = $body->temporaryCount;
        $opArray->cache_size = $body->cacheSize;
        if ($body->cacheSize > 0) {
            $opArray->fn_flags |= Zend::ZEND_ACC_HEAP_RT_CACHE;
        }
        $oldLiterals = $opArray->literals;
        if ($oldLiteralCount > 0 && $oldLiterals === null) {
            throw new RuntimeException('The runtime op array has no literal storage');
        }

        [$literalSlots, $newLiteralValues] = self::planLiterals($body, $oldLiteralCount);
        if ($body->count() === $oldInstructionCount && $newLiteralValues === []) {
            self::writeExistingStorage($body, $oldOpcodes, $oldLiterals);
            return;
        }

        $newLiteralCount = $oldLiteralCount + count($newLiteralValues);
        $opcodeSize = FFI::sizeof($ffi->new('zend_op'));
        $zvalSize = FFI::sizeof($ffi->new('zval'));
        $opcodeBytes = $opcodeSize * $body->count();
        $alignedOpcodeBytes = self::align($opcodeBytes, 16);

        $opcodeAllocation = null;
        $literalAllocation = null;
        $newStrings = [];
        $installed = false;

        try {
            if (PHP_INT_SIZE === 4) {
                $opcodeAllocation = $ffi->_emalloc(max(1, $opcodeBytes));
                $opcodeBase = $ffi->cast('char *', $opcodeAllocation);
                $newOpcodes = $ffi->cast('zend_op *', $opcodeBase);

                if ($newLiteralCount > 0) {
                    $literalAllocation = $ffi->_emalloc($zvalSize * $newLiteralCount);
                    $newLiterals = $ffi->cast('zval *', $ffi->cast('char *', $literalAllocation));
                } else {
                    $newLiterals = null;
                }
            } else {
                $opcodeAllocation = $ffi->_emalloc($alignedOpcodeBytes + ($zvalSize * $newLiteralCount));
                $opcodeBase = $ffi->cast('char *', $opcodeAllocation);
                $newOpcodes = $ffi->cast('zend_op *', $opcodeBase);
                $newLiterals = $newLiteralCount === 0
                    ? null
                    : $ffi->cast('zval *', $ffi->cast(
                        'char *',
                        $ffi->cast('uintptr_t', $opcodeBase)->cdata + $alignedOpcodeBytes,
                    ));
            }

            $newStrings = self::copyLiterals($oldLiterals, $oldLiteralCount, $newLiterals);
            foreach ($newLiteralValues as $slot => $value) {
                if ($newLiterals === null) {
                    throw new RuntimeException('A literal was planned without literal storage');
                }

                $string = self::writeLiteral($newLiterals[$slot], $value);
                if ($string !== null) {
                    $newStrings[] = $string;
                }
            }

            foreach ($body->instructions() as $index => $instruction) {
                $opline = $newOpcodes[$index];
                $opline->opcode = $instruction->opcode;
                $opline->op1_type = $instruction->operand1->type;
                $opline->op2_type = $instruction->operand2->type;
                $opline->result_type = $instruction->result->type;
                $opline->extended_value = $instruction->extendedValue;
                $opline->lineno = $instruction->line;
                self::writeOperand($opline->result, $instruction->result, $opline, $newLiterals, $literalSlots);
                self::writeOperand($opline->op1, $instruction->operand1, $opline, $newLiterals, $literalSlots);
                self::writeOperand($opline->op2, $instruction->operand2, $opline, $newLiterals, $literalSlots);
            }
            foreach ($body->instructions() as $index => $_instruction) {
                Zend::refreshOpcodeHandler($newOpcodes[$index]);
            }

            // The old pool is transferred, not duplicated. Marking its zvals
            // undefined makes the old block safe to release without dropping
            // references that now belong to the replacement pool.
            self::forgetLiterals($oldLiterals, $oldLiteralCount);

            $opArray->opcodes = $newOpcodes;
            $opArray->last = $body->count();
            $opArray->last_literal = $newLiteralCount;
            $opArray->literals = $newLiterals;
            self::relocateMetadata($opArray, $body, $oldInstructionCount);
            $installed = true;

            if (PHP_INT_SIZE === 4 && $oldLiterals !== null) {
                $ffi->_efree($oldLiterals);
            }
            $ffi->_efree($oldOpcodes);
        } catch (Throwable $exception) {
            if (!$installed) {
                foreach ($newStrings as $string) {
                    $ffi->_efree($string);
                }
                if ($literalAllocation !== null) {
                    $ffi->_efree($literalAllocation);
                }
                if ($opcodeAllocation !== null) {
                    $ffi->_efree($opcodeAllocation);
                }
            }

            throw $exception;
        }
    }

    /**
     * @return array{0: array<int, int>, 1: array<int, mixed>}
     */
    private static function planLiterals(MethodBody $body, int $oldLiteralCount): array
    {
        $slots = [];
        $values = [];
        $nextSlot = $oldLiteralCount;

        foreach ($body->instructions() as $instruction) {
            foreach ([$instruction->result, $instruction->operand1, $instruction->operand2] as $operandIndex => $operand) {
                if ($operand->kind !== Operand::CONSTANT) {
                    continue;
                }

                if ($operand->literalIndex !== null) {
                    if ($operand->literalIndex < 0 || $operand->literalIndex >= $oldLiteralCount) {
                        throw new RuntimeException('A constant operand points outside its original literal pool');
                    }

                    continue;
                }

                $id = spl_object_id($operand);
                if (isset($slots[$id])) {
                    continue;
                }

                self::assertEncodableLiteral($operand->value);
                $slots[$id] = $nextSlot;
                $values[$nextSlot] = $operand->value;
                $nextSlot++;

                if (self::needsNamePair($instruction, $operandIndex) && is_string($operand->value)) {
                    self::assertEncodableLiteral(strtolower($operand->value));
                    $values[$nextSlot] = strtolower($operand->value);
                    $nextSlot++;
                }
            }
        }

        return [$slots, $values];
    }

    private static function needsNamePair(Instruction $instruction, int $operandIndex): bool
    {
        return match ($instruction->name) {
            'NEW' => $operandIndex === 1,
            'INIT_FCALL', 'INIT_FCALL_BY_NAME', 'INIT_NS_FCALL_BY_NAME' => $operandIndex === 2,
            'INIT_METHOD_CALL' => $operandIndex === 2,
            'INIT_STATIC_METHOD_CALL' => in_array($operandIndex, [1, 2], true),
            default => false,
        };
    }

    /**
     * @phpstan-param \Communism_FFI\zend_op_pointer $opcodes
     * @phpstan-param \Communism_FFI\zval_pointer|null $literals
     */
    private static function writeExistingStorage(MethodBody $body, object $opcodes, ?object $literals): void
    {
        foreach ($body->instructions() as $index => $instruction) {
            $opline = $opcodes[$index];
            $opline->opcode = $instruction->opcode;
            $opline->op1_type = $instruction->operand1->type;
            $opline->op2_type = $instruction->operand2->type;
            $opline->result_type = $instruction->result->type;
            $opline->extended_value = $instruction->extendedValue;
            $opline->lineno = $instruction->line;
            self::writeExistingOperand($opline->result, $instruction->result, $literals);
            self::writeExistingOperand($opline->op1, $instruction->operand1, $literals);
            self::writeExistingOperand($opline->op2, $instruction->operand2, $literals);
        }
        foreach ($body->instructions() as $index => $_instruction) {
            Zend::refreshOpcodeHandler($opcodes[$index]);
        }
    }

    /**
     * @phpstan-param \Communism_FFI\znode_op $raw
     * @phpstan-param \Communism_FFI\zval_pointer|null $literals
     */
    private static function writeExistingOperand(object $raw, Operand $operand, ?object $literals): void
    {
        if ($operand->kind === Operand::CONSTANT) {
            if ($operand->rawValue === null || $literals === null) {
                throw new RuntimeException('An existing constant operand has no runtime address');
            }

            if (PHP_INT_SIZE === 4) {
                $slot = $operand->literalIndex;
                if ($slot === null) {
                    throw new RuntimeException('An existing constant operand has no literal slot');
                }

                $ffi = Zend::ffi();
                $raw->zv = $ffi->cast(
                    'zval *',
                    $ffi->cast('char *', $ffi->cast('uintptr_t', $literals)->cdata + $slot * FFI::sizeof($ffi->new('zval'))),
                );
            } else {
                $raw->constant = $operand->rawValue;
            }

            return;
        }

        $value = $operand->rawValue ?? $operand->value;
        if (!is_int($value)) {
            throw new RuntimeException('An opcode operand must contain an integer runtime value');
        }

        match ($operand->type) {
            2, 4, 8 => $raw->var = $value,
            default => $raw->num = $value,
        };
    }

    /**
     * @phpstan-param \Communism_FFI\zval_pointer|null $oldLiterals
     * @phpstan-param \Communism_FFI\zval_pointer|null $newLiterals
     */
    /**
     * @return list<object>
     * @phpstan-param \Communism_FFI\zval_pointer|null $oldLiterals
     * @phpstan-param \Communism_FFI\zval_pointer|null $newLiterals
     */
    private static function copyLiterals(?object $oldLiterals, int $count, ?object $newLiterals): array
    {
        if ($count === 0) {
            return [];
        }
        if ($oldLiterals === null || $newLiterals === null) {
            throw new RuntimeException('Cannot copy a missing literal pool');
        }

        $ffi = Zend::ffi();
        $strings = [];
        for ($index = 0; $index < $count; $index++) {
            $source = $oldLiterals[$index];
            $target = $newLiterals[$index];
            if ($source->u1->v->type === 6 && $source->value->ptr !== null) {
                $sourceString = $ffi->cast('zend_string *', $source->value->ptr);
                $value = FFI::string($ffi->cast('char *', $sourceString->val), $sourceString->len);
                $string = $ffi->zend_strpprintf(max(1, $sourceString->len + 1), '%s', $value);
                $string->h = $sourceString->h;
                $target->value->ptr = $string;
                $strings[] = $string;
            } else {
                $target->value->lval = $source->value->lval;
            }
            $target->u1->type_info = $source->u1->type_info;
            $target->u2->extra = $source->u2->extra;
        }

        return $strings;
    }

    /** @phpstan-param \Communism_FFI\zval_pointer|null $literals */
    private static function forgetLiterals(?object $literals, int $count): void
    {
        if ($literals === null) {
            return;
        }

        for ($index = 0; $index < $count; $index++) {
            $literals[$index]->u1->type_info = 0;
        }
    }

    /** @phpstan-param \Communism_FFI\zval $literal */
    private static function writeLiteral(object $literal, mixed $value): ?object
    {
        if (is_null($value)) {
            $literal->value->lval = 0;
            $literal->u1->type_info = 1;
            $literal->u2->extra = 0;
            return null;
        }
        if (is_bool($value)) {
            $literal->value->lval = 0;
            $literal->u1->type_info = $value ? 3 : 2;
            $literal->u2->extra = 0;
            return null;
        }
        if (is_int($value)) {
            $literal->value->lval = $value;
            $literal->u1->type_info = 4;
            $literal->u2->extra = 0;
            return null;
        }
        if (is_float($value)) {
            $literal->value->dval = $value;
            $literal->u1->type_info = 5;
            $literal->u2->extra = 0;
            return null;
        }
        if (is_string($value)) {
            $ffi = Zend::ffi();
            $string = $ffi->zend_strpprintf(max(1, strlen($value) + 1), '%s', $value);
            $string->h = $ffi->zend_hash_func($value, strlen($value));
            $literal->value->ptr = $string;
            $literal->u1->type_info = 6;
            $literal->u2->extra = 0;
            return $string;
        }

        throw new RuntimeException('Only scalar constants can be encoded into a rewritten literal pool');
    }

    /** @param mixed $value */
    private static function assertEncodableLiteral(mixed $value): void
    {
        if (!is_null($value) && !is_bool($value) && !is_int($value) && !is_float($value) && !is_string($value)) {
            throw new RuntimeException('Only scalar constants can be encoded into a rewritten literal pool');
        }
    }

    /**
     * @param array<int, int> $literalSlots
     * @phpstan-param object|null $literals
     * @phpstan-param \Communism_FFI\znode_op $raw
     */
    private static function writeOperand(
        object $raw,
        Operand $operand,
        object $opline,
        ?object $literals,
        array $literalSlots,
    ): void {
        if ($operand->kind === Operand::CONSTANT) {
            $slot = $operand->literalIndex ?? ($literalSlots[spl_object_id($operand)] ?? null);
            if ($slot === null || $literals === null) {
                throw new RuntimeException('A constant operand has no literal slot');
            }

            if (PHP_INT_SIZE === 4) {
                $ffi = Zend::ffi();
                $literalAddress = $ffi->cast('uintptr_t', $literals)->cdata;
                $raw->zv = $ffi->cast(
                    'zval *',
                    $ffi->cast('char *', $literalAddress + (FFI::sizeof($ffi->new('zval')) * $slot)),
                );
                return;
            }

            $ffi = Zend::ffi();
            $oplineAddress = $ffi->cast('uintptr_t', FFI::addr($opline))->cdata;
            $literalAddress = $ffi->cast('uintptr_t', $literals)->cdata;
            $raw->constant = $literalAddress + (FFI::sizeof($ffi->new('zval')) * $slot) - $oplineAddress;
            return;
        }

        $value = $operand->rawValue ?? $operand->value;
        if (!is_int($value)) {
            throw new RuntimeException('An opcode operand must contain an integer runtime value');
        }

        match ($operand->type) {
            2, 4, 8 => $raw->var = $value,
            default => $raw->num = $value,
        };
    }

    private static function align(int $size, int $alignment): int
    {
        return ($size + $alignment - 1) & (~($alignment - 1));
    }

    /** @phpstan-param \Communism_FFI\zend_op_array $opArray */
    private static function relocateMetadata(object $opArray, MethodBody $body, int $oldInstructionCount): void
    {
        $newInstructionCount = $body->count();
        if ($oldInstructionCount === $newInstructionCount) {
            return;
        }

        $indexMap = [];
        foreach ($body->instructions() as $newIndex => $instruction) {
            if ($instruction->originalIndex !== null) {
                $indexMap[$instruction->originalIndex] = $newIndex;
            }
        }

        $relocate = static function (int $oldIndex) use ($indexMap, $newInstructionCount): int {
            if (isset($indexMap[$oldIndex])) {
                return $indexMap[$oldIndex];
            }

            $next = $newInstructionCount;
            foreach ($indexMap as $originalIndex => $newIndex) {
                if ($originalIndex > $oldIndex && $newIndex < $next) {
                    $next = $newIndex;
                }
            }

            return $next;
        };

        if ($opArray->live_range !== null) {
            for ($index = 0; $index < $opArray->last_live_range; $index++) {
                $range = $opArray->live_range[$index];
                $range->start = $relocate($range->start);
                $range->end = $relocate($range->end);
            }
        }

        if ($opArray->try_catch_array !== null) {
            for ($index = 0; $index < $opArray->last_try_catch; $index++) {
                $tryCatch = $opArray->try_catch_array[$index];
                $tryCatch->try_op = $relocate($tryCatch->try_op);
                $tryCatch->catch_op = $relocate($tryCatch->catch_op);
                $tryCatch->finally_op = $relocate($tryCatch->finally_op);
                $tryCatch->finally_end = $relocate($tryCatch->finally_end);
            }
        }
    }
}
