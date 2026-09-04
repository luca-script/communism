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
 * Consumer: Users                                                            *
 * Purpose: Source file for ModifyConstant.php.                               *
 *============================================================================*/

declare(strict_types=1);

namespace Communism\Mixin;

use Attribute;

/** Mixin-shaped declaration for replacing a matched literal. */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class ModifyConstant
{
    public readonly string $method;
    /** @var list<non-empty-string> */
    public readonly array $targets;

    /** @param string|array<mixed> $method */
    public function __construct(
        string|array $method,
        public readonly At $at,
        public readonly mixed $constant = null,
        public readonly ?string $type = null,
        public readonly bool $nullValue = false,
        public readonly ?Slice $slice = null,
    ) {
        $this->targets = TargetMethods::normalize($method);
        $this->method = $this->targets[0];
        if ($type !== null && !in_array($type, ['int', 'long', 'float', 'double', 'string', 'null', 'bool', 'array', 'object', 'class'], true)) {
            throw new \InvalidArgumentException('Unsupported constant type discriminator');
        }
        if ($nullValue && $type !== null && $type !== 'null') {
            throw new \InvalidArgumentException('ModifyConstant nullValue requires the null type discriminator');
        }
        if ($nullValue && $constant !== null) {
            throw new \InvalidArgumentException('ModifyConstant nullValue cannot be combined with a non-null constant');
        }
    }
}
