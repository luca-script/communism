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
 * File: ModifyArg.php                                                        *
 * Consumer: Users                                                            *
 * Purpose: Source file for ModifyArg.php.                                    *
 *============================================================================*/

declare(strict_types=1);

namespace Communism\Mixin;

use Attribute;

/** Mixin-shaped declaration for replacing one invocation argument. */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class ModifyArg
{
    public readonly string $method;
    /** @var list<non-empty-string> */
    public readonly array $targets;

    /** @param string|array<mixed> $method */
    public function __construct(
        string|array $method,
        public readonly At $at,
        public readonly int $index = 0,
        public readonly ?Slice $slice = null,
    ) {
        $this->targets = TargetMethods::normalize($method);
        $this->method = $this->targets[0];
    }
}
