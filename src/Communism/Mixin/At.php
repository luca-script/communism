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
 * File: At.php                                                               *
 * Consumer: Users                                                            *
 * Purpose: Source file for At.php.                                           *
 *============================================================================*/

declare(strict_types=1);

namespace Communism\Mixin;

use Attribute;

use function sprintf;
use function strtolower;
use function strtoupper;

/** Describes a Mixin-style injection point. */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class At
{
    public function __construct(
        public readonly string $value,
        public readonly mixed $target = '',
        public readonly int $ordinal = -1,
        public readonly string $shift = 'BEFORE',
        public readonly int $by = 0,
        public readonly string $action = '',
        public readonly string $opcode = '',
    ) {
        if ($value === '') {
            throw new \InvalidArgumentException('An injection point value must not be empty');
        }
        if ($ordinal < -1 || $by < 0) {
            throw new \InvalidArgumentException('At ordinal and shift distance must be non-negative or -1');
        }
        if (!in_array(strtoupper($shift), ['BEFORE', 'AFTER', 'BY'], true)) {
            throw new \InvalidArgumentException('At shift must be BEFORE, AFTER, or BY');
        }
        if (strtoupper($shift) === 'BY' && $by === 0) {
            throw new \InvalidArgumentException('At BY shifting requires a positive distance');
        }
        if ($by > 32) {
            throw new \InvalidArgumentException('At shift distance must not exceed 32 instructions');
        }
    }

    /** Returns the normalized Mixin injection-point kind. */
    public function type(): string
    {
        return strtoupper($this->value);
    }

    /** Returns the internal action represented by this injection point. */
    public function action(): string
    {
        if ($this->action !== '') {
            return strtolower($this->action);
        }

        return match ($this->type()) {
            'HEAD' => 'before',
            'INVOKE' => strtoupper($this->shift) === 'AFTER' ? 'after' : 'before',
            'TAIL', 'RETURN', 'NEW', 'JUMP', 'CONSTRUCTOR_HEAD' => 'before',
            'INVOKE_ASSIGN' => 'after',
            default => 'replace',
        };
    }

    public function description(): string
    {
        $description = $this->type();
        if ($this->target !== '') {
            $description .= sprintf(' target %s', is_string($this->target) ? $this->target : 'custom invocation');
        }
        if ($this->ordinal >= 0) {
            $description .= sprintf(' argument %d', $this->ordinal);
        }
        if ($this->opcode !== '') {
            $description .= sprintf(' opcode %s', strtoupper($this->opcode));
        }

        return $description;
    }
}
