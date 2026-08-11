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
 * File: Args.php                                                             *
 * Consumer: Users                                                            *
 * Purpose: Virtual Mixin argument list exposed to ModifyArgs handlers.       *
 *============================================================================*/

declare(strict_types=1);

namespace Communism\Mixin;

use ArrayAccess;
use Countable;
use LogicException;

/**
 * Virtual Mixin-style invocation argument list.
 *
 * Needle lowers this object away when a ModifyArgs handler is injected. It is
 * The class only exists for handler type declarations. It must never be
 * instantiated; injected handlers are lowered to ordinary opcodes first.
 *
 * @implements ArrayAccess<int, mixed>
 */
final class Args implements ArrayAccess, Countable
{
    public function __construct()
    {
        throw self::virtualError();
    }

    public function get(int $index): mixed
    {
        throw self::virtualError();
    }

    public function set(int $index, mixed $value): void
    {
        throw self::virtualError();
    }

    /** @return int<0, max> */
    public function getCount(): int
    {
        throw self::virtualError();
    }

    /** @param array<int, mixed> $values */
    public function setAll(array $values): void
    {
        throw self::virtualError();
    }

    /** @return int<0, max> */
    public function count(): int
    {
        throw self::virtualError();
    }

    public function offsetExists(mixed $offset): bool
    {
        throw self::virtualError();
    }

    public function offsetGet(mixed $offset): mixed
    {
        throw self::virtualError();
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw self::virtualError();
    }

    public function offsetUnset(mixed $offset): void
    {
        throw self::virtualError();
    }

    private static function virtualError(): LogicException
    {
        return new LogicException('Args is virtual and cannot be used at runtime');
    }
}
