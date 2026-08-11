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
 * File: Instruction.php                                                      *
 * Purpose: Source file for Instruction.php.                                  *
 *============================================================================*/

declare(strict_types=1);

namespace Communism\Internals\Needle;

final readonly class Instruction
{
    public function __construct(
        public int $opcode,
        public string $name,
        public Operand $result,
        public Operand $operand1,
        public Operand $operand2,
        public int $extendedValue = 0,
        public int $line = 0,
        public ?object $handler = null,
        public ?int $originalIndex = null,
    ) {}

    public function withOpcode(int $opcode, string $name, ?object $handler = null): self
    {
        return new self($opcode, $name, $this->result, $this->operand1, $this->operand2, $this->extendedValue, $this->line, $handler ?? $this->handler, $this->originalIndex);
    }

    public function withOperands(Operand $operand1, Operand $operand2, ?Operand $result = null): self
    {
        return new self($this->opcode, $this->name, $result ?? $this->result, $operand1, $operand2, $this->extendedValue, $this->line, $this->handler, $this->originalIndex);
    }
}
