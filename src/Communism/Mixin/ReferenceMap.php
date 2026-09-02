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
 * File: ReferenceMap.php                                                     *
 * Consumer: Users                                                            *
 * Purpose: Reusable PHP symbol remapping for Mixin selectors.                *
 *============================================================================*/

declare(strict_types=1);

namespace Communism\Mixin;

use InvalidArgumentException;

/** Maps logical PHP selector names to their transformed runtime names. */
final readonly class ReferenceMap
{
    /**
     * @param array<string, string> $methods
     * @param array<string, string> $fields
     */
    public function __construct(
        public array $methods = [],
        public array $fields = [],
    ) {
        self::validate($methods, 'method');
        self::validate($fields, 'field');
    }

    public function method(string $selector): string
    {
        return $this->methods[$selector] ?? $selector;
    }

    public function field(string $selector): string
    {
        return $this->fields[$selector] ?? $selector;
    }

    /** @param string|array<mixed>|Desc $target
     * @return string|array<mixed>
     */
    public function invocation(string|array|Desc $target): string|array
    {
        if ($target instanceof Desc) {
            return [$this->method($target->selector()), ['signature' => $target->signature()]];
        }
        if (is_string($target)) {
            return $this->method($target);
        }
        if (!isset($target[0]) || !is_string($target[0])) {
            return $target;
        }

        $mapped = $target;
        $mapped[0] = $this->method($target[0]);
        if (isset($mapped[1]) && is_array($mapped[1]) && array_key_exists('aliases', $mapped[1]) && is_array($mapped[1]['aliases'])) {
            $mapped[1]['aliases'] = array_map(
                fn(mixed $alias): mixed => is_string($alias) ? $this->method($alias) : $alias,
                $mapped[1]['aliases'],
            );
        }

        return $mapped;
    }

    /** @param string|array<mixed> $target
     * @return string|array<mixed>
     */
    public function fieldTarget(string|array $target): string|array
    {
        if (is_string($target)) {
            return $this->field($target);
        }
        if (!isset($target[0]) || !is_string($target[0])) {
            return $target;
        }

        $mapped = $target;
        $mapped[0] = $this->field($target[0]);
        if (isset($mapped[1]) && is_array($mapped[1]) && array_key_exists('aliases', $mapped[1]) && is_array($mapped[1]['aliases'])) {
            $mapped[1]['aliases'] = array_map(
                fn(mixed $alias): mixed => is_string($alias) ? $this->field($alias) : $alias,
                $mapped[1]['aliases'],
            );
        }

        return $mapped;
    }

    /** @param array<string, string> $map */
    private static function validate(array $map, string $kind): void
    {
        foreach ($map as $from => $to) {
            if ($from === '' || $to === '' || preg_match('/\s/', $from) === 1 || preg_match('/\s/', $to) === 1) {
                throw new InvalidArgumentException(sprintf('ReferenceMap %s selectors must be non-empty names without whitespace', $kind));
            }
        }
    }
}
