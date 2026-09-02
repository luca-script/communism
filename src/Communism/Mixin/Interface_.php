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
 * File: Interface_.php                                                       *
 * Consumer: Users                                                            *
 * Purpose: Source file for Interface_.php.                                   *
 *============================================================================*/

declare(strict_types=1);

namespace Communism\Mixin;

use Attribute;

/** Describes a PHP interface method mapping for an Implements_ declaration. */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final class Interface_
{
    /** @param class-string $interface */
    public function __construct(
        /** @var class-string */
        public readonly string $interface,
        public readonly string $prefix,
        public readonly bool $unique = false,
    ) {
        if (!interface_exists($interface)) {
            throw new \InvalidArgumentException(sprintf('Interface_ requires a declared interface, got %s', $interface));
        }
        if ($prefix === '' || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $prefix) !== 1) {
            throw new \InvalidArgumentException('Interface_ prefix must be a non-empty PHP identifier prefix');
        }
    }
}
