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
 * File: CallbackInfoReturnable.php                                           *
 * Consumer: Users                                                            *
 * Purpose: Virtual returnable callback state for injection handlers.         *
 *============================================================================*/

declare(strict_types=1);

namespace Communism\Mixin;

use LogicException;

/** @template TReturn */
final class CallbackInfoReturnable extends CallbackInfo
{
    public function __construct()
    {
        parent::__construct();
    }

    /** @return TReturn */
    public function getReturnValue(): mixed
    {
        throw new LogicException('CallbackInfoReturnable is virtual and cannot be used at runtime');
    }

    /** @param TReturn $returnValue */
    public function setReturnValue(mixed $returnValue): void
    {
        throw new LogicException($returnValue === null
            ? 'CallbackInfoReturnable is virtual and cannot be used at runtime'
            : 'CallbackInfoReturnable is virtual and cannot accept a runtime value');
    }
}
