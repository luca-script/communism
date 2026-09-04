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
 * File: Inject.php                                                           *
 * Consumer: Users                                                            *
 * Purpose: Source file for Inject.php.                                       *
 *============================================================================*/

declare(strict_types=1);

namespace Communism\Mixin;

use Attribute;
use Communism\Internals\Needle\Matcher;

/**
 * Declares an injection point for a method supplied by an injected mixin class.
 *
 * The method carrying this attribute is the injector method. The first
 * argument names the method in the target class and the second argument is a
 * Mixin-style At injection point.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class Inject
{
    public readonly string $method;
    public readonly At $at;
    /** @var list<non-empty-string> */
    public readonly array $targets;

    /**
     * @param string|array<mixed> $method
     * @param At $at
     */
    public function __construct(
        string|array $method,
        At $at,
        public readonly bool $cancellable = true,
        public readonly ?int $require = null,
        public readonly ?int $expect = null,
        public readonly ?int $allow = null,
        public readonly ?Slice $slice = null,
        public readonly ?int $argumentIndex = null,
        public readonly ?Group $group = null,
        public readonly ?string $constantType = null,
        public readonly string $mode = 'inject',
        public readonly LocalCapture $locals = LocalCapture::CAPTURE_FAILHARD,
        public readonly ?int $variableIndex = null,
        public readonly ?string $variableType = null,
        public readonly bool $nullValue = false,
        public readonly bool $variableArgsOnly = false,
        public readonly string $id = '',
    ) {
        $targets = is_string($method) ? [$method] : $method;
        if ($targets === [] || (!is_string($method) && !array_is_list($targets))) {
            throw new \InvalidArgumentException('An injection target method must not be empty');
        }
        $normalized = [];
        foreach ($targets as $target) {
            if (!is_string($target) || $target === '') {
                throw new \InvalidArgumentException('An injection target method must not be empty');
            }
            $normalized[] = $target;
        }
        $this->method = $normalized[0];
        $this->targets = $normalized;

        if ($require !== null && $require < 0) {
            throw new \InvalidArgumentException('Inject require must be non-negative');
        }
        if ($expect !== null && $expect < 0) {
            throw new \InvalidArgumentException('Inject expect must be non-negative');
        }
        if ($allow !== null && $allow < 0) {
            throw new \InvalidArgumentException('Inject allow must be non-negative');
        }
        if ($argumentIndex !== null && $argumentIndex < 0) {
            throw new \InvalidArgumentException('Inject argument index must be non-negative');
        }
        if ($argumentIndex !== null && $at->type() !== 'INVOKE') {
            throw new \InvalidArgumentException('An Inject argument index requires an INVOKE point');
        }
        if ($variableIndex !== null && $variableIndex < 0) {
            throw new \InvalidArgumentException('An Inject variable index must be non-negative');
        }
        if ($variableIndex !== null && !in_array($at->type(), ['STORE', 'LOAD'], true)) {
            throw new \InvalidArgumentException('An Inject variable index requires a STORE or LOAD point');
        }
        if ($variableType !== null && !in_array($at->type(), ['STORE', 'LOAD'], true)) {
            throw new \InvalidArgumentException('An Inject variable type requires a STORE or LOAD point');
        }
        if ($constantType !== null && !in_array($constantType, ['int', 'long', 'float', 'double', 'string', 'null', 'bool', 'array', 'object', 'class'], true)) {
            throw new \InvalidArgumentException('Unsupported constant type discriminator');
        }
        if ($nullValue && $constantType !== null && $constantType !== 'null') {
            throw new \InvalidArgumentException('Inject nullValue requires the null constant type discriminator');
        }
        if ($nullValue && $at->type() !== 'CONSTANT') {
            throw new \InvalidArgumentException('Inject nullValue requires a CONSTANT point');
        }
        if (!in_array($mode, ['inject', 'args'], true)) {
            throw new \InvalidArgumentException('Unsupported injection mode');
        }

        $this->at = $at;
        Matcher::validateAt($at);
    }
}
