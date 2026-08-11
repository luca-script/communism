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
 * File: Group.php                                                            *
 * Purpose: Source file for Group.php.                                        *
 *============================================================================*/

declare(strict_types=1);

namespace Communism\Mixin;

use Attribute;

/** Groups injection handlers under shared minimum and maximum match counts. */
#[Attribute(Attribute::TARGET_METHOD)]
final class Group
{
    public function __construct(
        public readonly string $name,
        public readonly int $min = 0,
        public readonly int $max = PHP_INT_MAX,
    ) {
        if ($name === '') {
            throw new \InvalidArgumentException('An injection group name must not be empty');
        }
        if ($min < 0 || $max < 0 || $max < $min) {
            throw new \InvalidArgumentException('An injection group requires 0 <= min <= max');
        }
    }
}
