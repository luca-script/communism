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
 * File: Desc.php                                                             *
 * Consumer: Users                                                            *
 * Purpose: Source file for Desc.php.                                         *
 *============================================================================*/

declare(strict_types=1);

namespace Communism\Mixin;

use Attribute;

/** A PHP-shaped explicit selector signature for invocation targets. */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class Desc
{
    /** @param list<string> $args */
    public function __construct(
        public readonly string $value,
        public readonly ?string $owner = null,
        public readonly array $args = [],
        public readonly string $returnType = 'void',
        public readonly string $id = '',
    ) {
        if ($value === '' || preg_match('/\s/', $value) === 1) {
            throw new \InvalidArgumentException('Desc value must be a non-empty selector');
        }
        foreach ($args as $argument) {
            if ($argument === '' || preg_match('/\s/', $argument) === 1) {
                throw new \InvalidArgumentException('Desc argument types must be non-empty PHP names');
            }
        }
        if ($returnType === '') {
            throw new \InvalidArgumentException('Desc return type must not be empty');
        }
    }

    public function selector(): string
    {
        if ($this->owner === null) {
            return $this->value;
        }

        return $this->owner . '::' . $this->value;
    }

    /** @return array{parameters: list<string>, return: string} */
    public function signature(): array
    {
        return ['parameters' => $this->args, 'return' => $this->returnType];
    }
}
