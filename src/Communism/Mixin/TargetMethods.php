<?php

/*============================================================================*
 * SPDX-License-Identifier: 0BSD                                              *
 * SPDX-FileCopyrightText: 2026 Luca Mollema                                  *
 * Copyright (C) 2026 Luca Mollema                                            *
 *                                                                            *
 * Permission to use, copy, modify, and/or distribute this software for any   *
 * purpose with or without fee is hereby granted, provided that the above     *
 * copyright notice and this permission notice appear in all copies.          *
 *                                                                            *
 * THE SOFTWARE IS PROVIDED “AS IS” AND THE AUTHOR DISCLAIMS ALL WARRANTIES   *
 * WITH REGARD TO THIS SOFTWARE INCLUDING ALL IMPLIED WARRANTIES OF           *
 * MERCHANTABILITY AND FITNESS. IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR    *
 * ANY SPECIAL, DIRECT, INDIRECT, OR CONSEQUENTIAL DAMAGES OR ANY              *
 * OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE,      *
 * ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR      *
 * OTHER DEALINGS IN THE SOFTWARE.                                             *
 *============================================================================*
 * :: Communism :: "In comrade PHP, all are public" ::                        *
 *----------------------------------------------------------------------------*
 * File: TargetMethods.php                                                     *
 * Consumer: Users                                                             *
 * Purpose: Shared validation for multi-target injector declarations.          *
 *============================================================================*/

declare(strict_types=1);

namespace Communism\Mixin;

use InvalidArgumentException;

/** Normalizes one or more PHP method names used by injector annotations. */
final class TargetMethods
{
    /** @param string|array<mixed> $methods
     * @return list<non-empty-string>
     */
    public static function normalize(string|array $methods): array
    {
        $targets = is_string($methods) ? [$methods] : $methods;
        if ($targets === [] || (!is_string($methods) && !array_is_list($targets))) {
            throw new InvalidArgumentException('An injection target method must be a non-empty list');
        }

        $normalized = [];
        foreach ($targets as $target) {
            if (!is_string($target) || $target === '') {
                throw new InvalidArgumentException('An injection target method must be a non-empty string');
            }
            $normalized[] = $target;
        }

        return $normalized;
    }
}
