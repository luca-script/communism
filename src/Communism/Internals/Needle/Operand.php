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
 * File: Operand.php                                                          *
 * Consumer: Internal                                                         *
 * Purpose: Source file for Operand.php.                                      *
 *============================================================================*/

declare(strict_types=1);

namespace Communism\Internals\Needle;

final readonly class Operand
{
    public const string UNUSED = 'unused';
    public const string CONSTANT = 'constant';
    public const string TEMPORARY = 'temporary';
    public const string VARIABLE = 'variable';
    public const string CV = 'cv';
    public const string RAW = 'raw';

    private function __construct(
        public string $kind,
        public mixed $value,
        public int $type,
        public ?int $rawValue,
        public ?int $literalIndex = null,
    ) {}

    public static function unused(int $value = 0): self
    {
        return new self(self::UNUSED, $value, 0, $value);
    }

    public static function constant(mixed $value, int $rawOffset, ?int $literalIndex = null): self
    {
        return new self(self::CONSTANT, $value, 1, $rawOffset, $literalIndex);
    }

    public static function temporary(int $value): self
    {
        return new self(self::TEMPORARY, $value, 2, $value);
    }

    public static function variable(int $value): self
    {
        return new self(self::VARIABLE, $value, 4, $value);
    }

    public static function cv(int $value): self
    {
        return new self(self::CV, $value, 8, $value);
    }

    public static function raw(int $type, int $value): self
    {
        return new self(self::RAW, $value, $type, $value);
    }

    public function isUnused(): bool
    {
        return $this->kind === self::UNUSED;
    }

    public function withValue(mixed $value): self
    {
        return new self($this->kind, $value, $this->type, null);
    }
}
