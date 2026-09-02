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
 * File: InjectionException.php                                               *
 * Consumer: Users                                                            *
 * Purpose: Source file for InjectionException.php.                           *
 *============================================================================*/

declare(strict_types=1);

namespace Communism\Mixin;

use InvalidArgumentException;

/** Structured context for an injection resolution failure. */
final class InjectionException extends InvalidArgumentException
{
    public function __construct(
        public readonly string $point,
        public readonly string $targetMethod,
        public readonly int $matched,
        public readonly ?int $minimum = null,
        public readonly ?int $maximum = null,
        public readonly ?int $expected = null,
        public readonly ?string $mixinClass = null,
        public readonly ?string $handlerMethod = null,
        public readonly ?string $resolvedInstruction = null,
        public readonly ?string $slice = null,
        public readonly ?string $selector = null,
        string $message = '',
    ) {
        parent::__construct($message);
    }
}
