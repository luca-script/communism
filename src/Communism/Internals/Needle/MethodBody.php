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
 * File: MethodBody.php                                                       *
 * Consumer: Internal                                                         *
 * Purpose: Source file for MethodBody.php.                                   *
 *============================================================================*/

declare(strict_types=1);

namespace Communism\Internals\Needle;

use OutOfBoundsException;

final class MethodBody
{
    /** @var list<Instruction> */
    private array $instructions;

    /**
     * @param list<Instruction> $instructions
     * @param array<int, string> $variableNames
     */
    public function __construct(
        public readonly string $name,
        public readonly ?string $filename,
        public readonly int $lineStart,
        public readonly int $lineEnd,
        array $instructions,
        private readonly array $variableNames = [],
        public readonly int $temporaryCount = 0,
        public readonly int $cacheSize = 0,
    ) {
        $this->instructions = $instructions;
    }

    /** @return list<Instruction> */
    public function instructions(): array
    {
        return $this->instructions;
    }

    public function count(): int
    {
        return count($this->instructions);
    }

    public function instruction(int $index): Instruction
    {
        if (!isset($this->instructions[$index])) {
            throw new OutOfBoundsException(sprintf('Instruction %d does not exist', $index));
        }

        return $this->instructions[$index];
    }

    public function variableName(Operand $operand): ?string
    {
        if ($operand->kind !== Operand::CV || !is_int($operand->value)) {
            return null;
        }

        return $this->variableNames[$operand->value] ?? null;
    }

    /** @return Operand|null */
    public function variableOperand(string $name): ?Operand
    {
        foreach ($this->variableNames as $value => $variableName) {
            if ($variableName === $name) {
                return Operand::cv($value);
            }
        }

        return null;
    }

    /** Returns the declaration-order local index for a CV operand. */
    public function variableIndex(Operand $operand): ?int
    {
        if ($operand->kind !== Operand::CV || !is_int($operand->value)) {
            return null;
        }

        $index = 0;
        foreach ($this->variableNames as $value => $_name) {
            if ($value === $operand->value) {
                return $index;
            }
            $index++;
        }

        return null;
    }

    public function withTemporaryCount(int $temporaryCount): self
    {
        return new self($this->name, $this->filename, $this->lineStart, $this->lineEnd, $this->instructions, $this->variableNames, $temporaryCount, $this->cacheSize);
    }

    public function withCacheSize(int $cacheSize): self
    {
        return new self($this->name, $this->filename, $this->lineStart, $this->lineEnd, $this->instructions, $this->variableNames, $this->temporaryCount, $cacheSize);
    }

    /** @param list<Instruction> $instructions */
    public function withInstructions(array $instructions): self
    {
        return new self($this->name, $this->filename, $this->lineStart, $this->lineEnd, $instructions, $this->variableNames, $this->temporaryCount, $this->cacheSize);
    }

    public function replace(int $index, Instruction $instruction): self
    {
        $copy = $this->instructions;
        if (!isset($copy[$index])) {
            throw new OutOfBoundsException(sprintf('Instruction %d does not exist', $index));
        }

        $copy[$index] = $instruction;

        return $this->copy($copy);
    }

    public function insertBefore(int $index, Instruction $instruction): self
    {
        $copy = $this->instructions;
        array_splice($copy, $index, 0, [$instruction]);

        return $this->copy($copy);
    }

    public function insertAfter(int $index, Instruction $instruction): self
    {
        return $this->insertBefore($index + 1, $instruction);
    }

    public function remove(int $index): self
    {
        $copy = $this->instructions;
        if (!isset($copy[$index])) {
            throw new OutOfBoundsException(sprintf('Instruction %d does not exist', $index));
        }

        array_splice($copy, $index, 1);

        return $this->copy($copy);
    }

    /** @param list<Instruction> $instructions */
    public function replaceRange(int $start, int $length, array $instructions): self
    {
        if ($start < 0 || $length < 0 || $start + $length > $this->count()) {
            throw new OutOfBoundsException(sprintf('Instruction range %d..%d does not exist', $start, $start + $length));
        }

        $copy = $this->instructions;
        array_splice($copy, $start, $length, $instructions);

        return $this->copy($copy);
    }

    /** @param list<Instruction> $instructions */
    private function copy(array $instructions): self
    {
        return new self($this->name, $this->filename, $this->lineStart, $this->lineEnd, $instructions, $this->variableNames, $this->temporaryCount, $this->cacheSize);
    }
}
