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
 * File: ModifyConstant.php                                                   *
 * Purpose: Source file for ModifyConstant.php.                               *
 *============================================================================*/

declare(strict_types=1);

namespace Communism\Mixin;

use Attribute;

/** Mixin-shaped declaration for replacing a matched literal. */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class ModifyConstant
{
    /** @param At $at */
    public function __construct(
        public readonly string $method,
        public readonly At $at,
        public readonly mixed $constant = null,
        public readonly ?string $type = null,
    ) {
        if ($type !== null && !in_array($type, ['int', 'long', 'float', 'double', 'string', 'null', 'bool', 'array', 'object', 'class'], true)) {
            throw new \InvalidArgumentException('Unsupported constant type discriminator');
        }
    }
}
