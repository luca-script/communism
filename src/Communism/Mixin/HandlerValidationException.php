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
 * File: HandlerValidationException.php                                       *
 * Consumer: Users                                                            *
 * Purpose: Structured diagnostics for handler preparation failures.          *
 *============================================================================*/

declare(strict_types=1);

namespace Communism\Mixin;

use InvalidArgumentException;
use Throwable;

/** Describes a handler validation failure at a resolved injection span. */
final class HandlerValidationException extends InvalidArgumentException
{
    public function __construct(
        public readonly string $handlerMethod,
        public readonly string $targetMethod,
        public readonly string $point,
        public readonly int $start,
        public readonly int $end,
        public readonly string $selector,
        Throwable $previous,
        string $message = '',
    ) {
        parent::__construct($message !== '' ? $message : $previous->getMessage(), 0, $previous);
    }
}
