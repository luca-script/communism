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
 * File: AssemblyPlanHandle.php                                               *
 * Consumer: Internal                                                         *
 * Purpose: Typed, detached input for op-array assembly.                      *
 *============================================================================*/

declare(strict_types=1);

namespace Zendful;

use InvalidArgumentException;

// Native alias is exercised by the separate native-backend probe.
// @codeCoverageIgnoreStart
if (class_exists('Zendful\\Native\\AssemblyPlanHandle', false)) {
    class_alias('Zendful\\Native\\AssemblyPlanHandle', AssemblyPlanHandle::class);
    // @codeCoverageIgnoreEnd
} else {
    /** @internal */
    final readonly class AssemblyPlanHandle
    {
        /**
         * @param list<array{opcode: int, extendedValue: int, line: int, originalIndex: int|null, result: array{type: int, kind: string, value: mixed, rawValue: int|null, literalSlot: int|null}, operand1: array{type: int, kind: string, value: mixed, rawValue: int|null, literalSlot: int|null}, operand2: array{type: int, kind: string, value: mixed, rawValue: int|null, literalSlot: int|null}}> $instructions
         * @param array<int, mixed> $literalValues
         */
        public function __construct(
            public int $temporaryCount,
            public int $cacheSize,
            public array $instructions,
            public array $literalValues,
        ) {
            if ($temporaryCount < 0 || $cacheSize < 0) {
                throw new InvalidArgumentException('Assembly sizes must not be negative.');
            }
            self::validate($instructions, $literalValues);
        }

        /**
         * @param array<int|string, mixed> $instructions
         * @param array<int|string, mixed> $literalValues
         */
        private static function validate(array $instructions, array $literalValues): void
        {
            if (!array_is_list($instructions)) {
                throw new InvalidArgumentException('Assembly instructions must be a list.');
            }
            foreach ($instructions as $instruction) {
                if (!is_array($instruction)
                    || !array_key_exists('opcode', $instruction)
                    || !array_key_exists('extendedValue', $instruction)
                    || !array_key_exists('line', $instruction)
                    || !array_key_exists('originalIndex', $instruction)
                    || !array_key_exists('result', $instruction)
                    || !array_key_exists('operand1', $instruction)
                    || !array_key_exists('operand2', $instruction)
                    || !is_array($instruction['result'])
                    || !is_array($instruction['operand1'])
                    || !is_array($instruction['operand2'])) {
                    throw new InvalidArgumentException('Assembly instructions have an invalid shape.');
                }

                if (!is_int($instruction['opcode'])
                    || !is_int($instruction['extendedValue'])
                    || !is_int($instruction['line'])
                    || ($instruction['originalIndex'] !== null && !is_int($instruction['originalIndex']))) {
                    throw new InvalidArgumentException('Assembly instruction values have an invalid shape.');
                }

                foreach (['result', 'operand1', 'operand2'] as $operandName) {
                    $operand = $instruction[$operandName];
                    if (!is_array($operand)
                        || !array_key_exists('type', $operand)
                        || !array_key_exists('kind', $operand)
                        || !array_key_exists('value', $operand)
                        || !array_key_exists('rawValue', $operand)
                        || !array_key_exists('literalSlot', $operand)
                        || !is_int($operand['type'])
                        || !is_string($operand['kind'])
                        || !in_array($operand['kind'], ['unused', 'constant', 'temporary', 'variable', 'cv', 'raw'], true)
                        || ($operand['rawValue'] !== null && !is_int($operand['rawValue']))
                        || ($operand['literalSlot'] !== null
                            && (!is_int($operand['literalSlot']) || $operand['literalSlot'] < 0))) {
                        throw new InvalidArgumentException('Assembly operand values have an invalid shape.');
                    }
                }
            }
            foreach ($literalValues as $slot => $_value) {
                if (!is_int($slot) || $slot < 0) {
                    throw new InvalidArgumentException('Assembly literal slots must be non-negative integers.');
                }
                if ($_value !== null && !is_scalar($_value)) {
                    throw new InvalidArgumentException('Assembly literals must be scalar values.');
                }
                if (is_string($_value) && str_contains($_value, "\0")) {
                    throw new InvalidArgumentException('Assembly literals must not contain NUL bytes.');
                }
            }
        }
    }
}
