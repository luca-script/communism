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
 * File: CallbackInfo.php                                                     *
 * Purpose: Virtual callback state exposed to injection handlers.             *
 *============================================================================*/

declare(strict_types=1);

namespace Communism\Mixin;

use LogicException;

/**
 * Virtual Mixin-style callback state.
 *
 * Needle lowers this type away when an injection is assembled. It cannot be
 * instantiated; target methods never receive a callback object at runtime.
 */
class CallbackInfo
{
    public function __construct()
    {
        throw new LogicException('CallbackInfo is virtual and cannot be used at runtime');
    }

    public function getId(): string
    {
        throw self::virtualError();
    }

    public function isCancellable(): bool
    {
        throw self::virtualError();
    }

    public function isCancelled(): bool
    {
        throw self::virtualError();
    }

    public function cancel(?string $reason = null): void
    {
        throw new LogicException($reason === null
            ? 'CallbackInfo is virtual and cannot be used at runtime'
            : 'CallbackInfo is virtual: ' . $reason);
    }

    public function __toString(): string
    {
        throw self::virtualError();
    }

    private static function virtualError(): LogicException
    {
        return new LogicException('CallbackInfo is virtual and cannot be used at runtime');
    }
}
