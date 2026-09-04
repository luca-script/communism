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
 * File: ModifyVariable.php                                                   *
 * Consumer: Users                                                            *
 * Purpose: Source file for ModifyVariable.php.                               *
 *============================================================================*/

declare(strict_types=1);

namespace Communism\Mixin;

use Attribute;

/** Mixin-shaped declaration for replacing a local-variable value. */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class ModifyVariable
{
    public readonly string $method;
    /** @var list<non-empty-string> */
    public readonly array $targets;

    /** @param string|array<mixed> $method */
    public function __construct(
        string|array $method,
        public readonly At $at,
        public readonly ?string $name = null,
        public readonly int $ordinal = -1,
        public readonly ?int $require = null,
        public readonly ?int $expect = null,
        public readonly ?int $allow = null,
        public readonly ?int $index = null,
        public readonly bool $argsOnly = false,
        public readonly bool $print = false,
        public readonly ?Slice $slice = null,
    ) {
        $this->targets = TargetMethods::normalize($method);
        $this->method = $this->targets[0];
        if ($index !== null && $index < 0) {
            throw new \InvalidArgumentException('ModifyVariable index must be non-negative');
        }
        foreach (['require' => $require, 'expect' => $expect, 'allow' => $allow] as $constraint => $count) {
            if ($count !== null && $count < 0) {
                throw new \InvalidArgumentException(sprintf('ModifyVariable %s must be non-negative', $constraint));
            }
        }
        if ($print && ($require !== null || $expect !== null || $allow !== null)) {
            throw new \InvalidArgumentException('ModifyVariable print mode cannot use match-count constraints');
        }
    }
}
