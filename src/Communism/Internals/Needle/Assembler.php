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
 * Purpose: Plan safe bytecode rewrites for Zendful assembly.                 *
 *============================================================================*/

declare(strict_types=1);

namespace Communism\Internals\Needle;

use RuntimeException;
use Zendful\AssemblyPlanHandle;
use Zendful\OpArrayHandle;

use function in_array;
use function is_int;
use function is_string;
use function spl_object_id;
use function strtolower;

/** Builds detached assembly plans; Zendful performs all unsafe mutation. */
final class Assembler
{
    public static function write(MethodBody $body, OpArrayHandle $opArray): void
    {
        Verifier::verify($body);
        [$literalSlots, $literalValues] = self::planLiterals($body, $opArray->literalCount());
        $instructions = [];
        foreach ($body->instructions() as $instruction) {
            $instructions[] = [
                'opcode' => $instruction->opcode,
                'extendedValue' => $instruction->extendedValue,
                'line' => $instruction->line,
                'originalIndex' => $instruction->originalIndex,
                'result' => self::operandPlan($instruction->result, $literalSlots),
                'operand1' => self::operandPlan($instruction->operand1, $literalSlots),
                'operand2' => self::operandPlan($instruction->operand2, $literalSlots),
            ];
        }

        $opArray->assemble(
            new AssemblyPlanHandle($body->temporaryCount, $body->cacheSize, $instructions, $literalValues),
        );
    }

    /**
     * @param array<int, int> $literalSlots
     * @return array{type: int, kind: string, value: mixed, rawValue: int|null, literalSlot: int|null}
     */
    private static function operandPlan(Operand $operand, array $literalSlots): array
    {
        return [
            'type' => $operand->type,
            'kind' => $operand->kind,
            'value' => $operand->value,
            'rawValue' => $operand->rawValue,
            'literalSlot' => $operand->literalIndex ?? ($literalSlots[spl_object_id($operand)] ?? null),
        ];
    }

    /** @return array{0: array<int, int>, 1: array<int, mixed>} */
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

    private static function assertEncodableLiteral(mixed $value): void
    {
        if ($value !== null && !is_bool($value) && !is_int($value) && !is_float($value) && !is_string($value)) {
            throw new RuntimeException('Only scalar constants can be encoded into a rewritten literal pool');
        }
    }
}
