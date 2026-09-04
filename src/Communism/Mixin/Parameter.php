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
 * File: Parameter.php                                                        *
 * Consumer: Users                                                            *
 * Purpose: Source file for Parameter.php.                                    *
 *============================================================================*/

declare(strict_types=1);

namespace Communism\Mixin;

use Attribute;
use InvalidArgumentException;

/** Selects a callback argument from a target parameter by name or ordinal. */
#[Attribute(Attribute::TARGET_PARAMETER)]
final class Parameter
{
    public function __construct(
        public readonly ?int $ordinal = null,
        public readonly ?string $name = null,
    ) {
        if ($ordinal !== null && $ordinal < 0) {
            throw new InvalidArgumentException('Parameter ordinal must be non-negative');
        }
        if ($name === '') {
            throw new InvalidArgumentException('Parameter name must not be empty');
        }
        if ($ordinal !== null && $name !== null) {
            throw new InvalidArgumentException('Parameter may specify an ordinal or name, not both');
        }
    }
}
