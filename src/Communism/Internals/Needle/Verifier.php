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
 * File: Verifier.php                                                         *
 * Consumer: Internal                                                         *
 * Purpose: Independent validation of detached method bodies before assembly. *
 *============================================================================*/

declare(strict_types=1);

namespace Communism\Internals\Needle;

use RuntimeException;

use function is_int;
use function str_contains;

/** Validates detached bytecode metadata before the assembler touches Zend. */
final class Verifier
{
    public static function verify(MethodBody $body): void
    {
        if ($body->temporaryCount < 0 || $body->cacheSize < 0) {
            throw new RuntimeException('A method body has negative allocation metadata');
        }

        foreach ($body->instructions() as $index => $instruction) {
            if ($instruction->opcode < 0 || $instruction->name === '' || str_contains($instruction->name, "\0")) {
                throw new RuntimeException(sprintf('Instruction %d has invalid opcode metadata', $index));
            }
            if ($instruction->extendedValue < 0 || $instruction->line < 0) {
                throw new RuntimeException(sprintf('Instruction %d has negative metadata', $index));
            }
            if ($instruction->originalIndex !== null && $instruction->originalIndex < 0) {
                throw new RuntimeException(sprintf('Instruction %d has a negative original index', $index));
            }

            self::verifyOperand($instruction->result, $index, 'result');
            self::verifyOperand($instruction->operand1, $index, 'operand1');
            self::verifyOperand($instruction->operand2, $index, 'operand2');
        }
    }

    private static function verifyOperand(Operand $operand, int $instruction, string $position): void
    {
        $expectedType = match ($operand->kind) {
            Operand::UNUSED => 0,
            Operand::CONSTANT => 1,
            Operand::TEMPORARY => 2,
            Operand::VARIABLE => 4,
            Operand::CV => 8,
            Operand::RAW => null,
            default => throw new RuntimeException(sprintf(
                'Instruction %d has an unknown %s operand kind %s',
                $instruction,
                $position,
                $operand->kind,
            )),
        };
        if ($expectedType !== null && $operand->type !== $expectedType) {
            throw new RuntimeException(sprintf(
                'Instruction %d has mismatched %s operand type',
                $instruction,
                $position,
            ));
        }
        if (in_array($operand->kind, [Operand::UNUSED, Operand::TEMPORARY, Operand::VARIABLE, Operand::CV], true)
            && !is_int($operand->value)
        ) {
            throw new RuntimeException(sprintf(
                'Instruction %d has a non-integer %s operand value',
                $instruction,
                $position,
            ));
        }
        if ($operand->kind !== Operand::CONSTANT && $operand->literalIndex !== null) {
            throw new RuntimeException(sprintf(
                'Instruction %d has a literal index on non-constant %s operand',
                $instruction,
                $position,
            ));
        }
    }
}
