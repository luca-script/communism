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
 * File: OpcodeHandle.php                                                     *
 * Consumer: Internal                                                         *
 * Purpose: Source file for OpcodeHandle.php.                                 *
 *============================================================================*/

declare(strict_types=1);

namespace Zendful;

use InvalidArgumentException;

// Native alias is exercised by the separate native-backend probe.
// @codeCoverageIgnoreStart
if (class_exists('Zendful\\Native\\OpcodeHandle', false)) {
    class_alias('Zendful\\Native\\OpcodeHandle', OpcodeHandle::class);
    // @codeCoverageIgnoreEnd
} else {
    final class OpcodeHandle
    {
        public static function id(string $opcode): int
        {
            if ($opcode === '' || str_contains($opcode, "\0")) {
                throw new InvalidArgumentException('Opcode names must not be empty or contain NUL bytes.');
            }
            return Internals\Executor::opcodeId($opcode);
        }

        /** @param array{opcode: int, name: string, extendedValue: int, line: int, result: OperandHandle, operand1: OperandHandle, operand2: OperandHandle} $opcode */
        public function __construct(private readonly array $opcode)
        {
            self::validate($opcode);
        }

        /** @param array<int|string, mixed> $opcode */
        private static function validate(array $opcode): void
        {
            if (!array_key_exists('opcode', $opcode)
                || !array_key_exists('name', $opcode)
                || !array_key_exists('extendedValue', $opcode)
                || !array_key_exists('line', $opcode)
                || !array_key_exists('result', $opcode)
                || !array_key_exists('operand1', $opcode)
                || !array_key_exists('operand2', $opcode)
                || !is_int($opcode['opcode'])
                || $opcode['opcode'] < 0
                || !is_string($opcode['name'])
                || $opcode['name'] === ''
                || str_contains($opcode['name'], "\0")
                || !is_int($opcode['extendedValue'])
                || $opcode['extendedValue'] < 0
                || !is_int($opcode['line'])
                || $opcode['line'] < 0
                || !$opcode['result'] instanceof OperandHandle
                || !$opcode['operand1'] instanceof OperandHandle
                || !$opcode['operand2'] instanceof OperandHandle) {
                throw new InvalidArgumentException('Opcode metadata has an invalid shape.');
            }
        }

        public function opcode(): int
        {
            return $this->opcode['opcode'];
        }

        public function name(): string
        {
            return $this->opcode['name'];
        }

        public function extendedValue(): int
        {
            return $this->opcode['extendedValue'];
        }

        public function line(): int
        {
            return $this->opcode['line'];
        }

        public function result(): OperandHandle
        {
            return $this->opcode['result'];
        }

        public function operand1(): OperandHandle
        {
            return $this->opcode['operand1'];
        }

        public function operand2(): OperandHandle
        {
            return $this->opcode['operand2'];
        }
    }
}
