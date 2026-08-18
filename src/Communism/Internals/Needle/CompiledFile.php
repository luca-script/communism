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
 * File: CompiledFile.php                                                     *
 * Consumer: Internal                                                         *
 * Purpose: Snapshot of a compile-only Zend file op array.                    *
 *============================================================================*/

declare(strict_types=1);

namespace Communism\Internals\Needle;

/**
 * The useful, detached part of a file compiled without executing it.
 *
 * All objects in this value are snapshots. Runtime pointers are read before
 * their compiler-owned storage is destroyed and never escape this class.
 */
final readonly class CompiledFile
{
    /**
     * @param list<string> $declaredClasses
     * @param list<CompiledClass> $classes
     * @param list<CompiledMethod> $functions
     */
    public function __construct(
        public MethodBody $body,
        public array $declaredClasses,
        public array $classes = [],
        public array $functions = [],
    ) {}

    public function class(string $name): ?CompiledClass
    {
        $name = strtolower($name);
        foreach ($this->classes as $class) {
            if (strtolower($class->name) === $name) {
                return $class;
            }
        }

        return null;
    }
}
