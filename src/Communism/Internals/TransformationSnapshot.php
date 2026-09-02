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
 * File: TransformationSnapshot.php                                           *
 * Consumer: Internal                                                         *
 * Purpose: Persisted before/after transformed bytecode snapshots.            *
 *============================================================================*/

declare(strict_types=1);

namespace Communism\Internals;

/** Immutable disassembly snapshot for one successful mixin transformation. */
final readonly class TransformationSnapshot
{
    /**
     * @param array<string, string> $before
     * @param array<string, string> $after
     */
    public function __construct(
        public string $class,
        public string $mixin,
        public array $before,
        public array $after,
    ) {}

    /** @return list<string> */
    public function changedMethods(): array
    {
        $methods = array_unique([...array_keys($this->before), ...array_keys($this->after)]);
        $changed = [];
        foreach ($methods as $method) {
            if (($this->before[$method] ?? null) !== ($this->after[$method] ?? null)) {
                $changed[] = $method;
            }
        }

        sort($changed);

        return $changed;
    }

    /**
     * @return list<array{method: string, before: string|null, after: string|null}>
     */
    public function diff(): array
    {
        $diff = [];
        foreach ($this->changedMethods() as $method) {
            $diff[] = [
                'method' => $method,
                'before' => $this->before[$method] ?? null,
                'after' => $this->after[$method] ?? null,
            ];
        }

        return $diff;
    }
}
