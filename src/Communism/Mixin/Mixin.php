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
 * File: Mixin.php                                                            *
 * Purpose: Source file for Mixin.php.                                        *
 *============================================================================*/

declare(strict_types=1);

namespace Communism\Mixin;

use Attribute;

/**
 * Declares the classes to which a mixin class may be applied.
 *
 * This is the sole target declaration for a Mixin class.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class Mixin
{
    /** @var list<string> */
    public readonly array $targets;

    /** @param string ...$targets */
    public function __construct(string ...$targets)
    {
        if ($targets === []) {
            throw new \InvalidArgumentException('A mixin must declare at least one target');
        }

        $this->targets = array_values($targets);
    }

    /** @param class-string $class */
    public function allows(string $class): bool
    {
        return in_array('*', $this->targets, true) || in_array($class, $this->targets, true);
    }
}
