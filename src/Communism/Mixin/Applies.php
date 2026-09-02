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
 * File: Applies.php                                                          *
 * Consumer: Users                                                            *
 * Purpose: Source file for Applies.php.                                      *
 *============================================================================*/

declare(strict_types=1);

namespace Communism\Mixin;

use Attribute;
use InvalidArgumentException;

use function preg_match;
use function preg_quote;
use function sprintf;
use function strcasecmp;
use function strtoupper;
use function array_slice;
use function array_values;

/** Adds an exact, regular-expression, or glob target selector to a mixin. */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final class Applies
{
    public readonly string $mode;
    /** @var array<int|string, string> */
    public readonly array $patterns;

    public function __construct(string ...$values)
    {
        if ($values === []) {
            throw new InvalidArgumentException('Applies requires at least one selector');
        }
        $mode = strtoupper($values[0]);
        $this->mode = in_array($mode, ['REGEX', 'GLOB'], true) ? $mode : 'EXACT';
        if ($this->mode === 'EXACT') {
            $patterns = [];
            foreach ($values as $value) {
                $patterns[] = $value;
            }
            $this->patterns = $patterns;
        } else {
            $patterns = [];
            foreach (array_slice($values, 1) as $value) {
                $patterns[] = $value;
            }
            $this->patterns = $patterns;
        }
        if ($this->patterns === [] || in_array('', $this->patterns, true)) {
            throw new InvalidArgumentException('Applies requires a class name or EXACT, REGEX, or GLOB selector');
        }
        if ($this->mode === 'REGEX') {
            foreach ($this->patterns as $pattern) {
                if (!self::validRegex($pattern)) {
                    throw new InvalidArgumentException(sprintf('Applies REGEX selector is invalid: %s', $pattern));
                }
            }
        }
    }

    public function matches(string $class): bool
    {
        foreach ($this->patterns as $pattern) {
            if (match ($this->mode) {
                'EXACT' => strcasecmp($class, $pattern) === 0,
                'REGEX' => preg_match('~' . $pattern . '~i', $class) === 1,
                'GLOB' => preg_match(
                    '~^' . str_replace(['\\*', '\\?'], ['.*', '.'], preg_quote($pattern, '~')) . '$~i',
                    $class,
                ) === 1,
            }) {
                return true;
            }
        }

        return false;
    }

    private static function validRegex(string $pattern): bool
    {
        $valid = true;
        set_error_handler(static function (int $severity, string $message, string $file, int $line) use (&$valid): bool {
            $valid = false;
            return true;
        });
        preg_match('~' . $pattern . '~i', '');
        restore_error_handler();

        return $valid;
    }
}
