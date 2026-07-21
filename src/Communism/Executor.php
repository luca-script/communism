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
 * File: Executor.php                                                         *
 * Purpose: Allows modification of PHP executor globals                       *
 *============================================================================*/

declare(strict_types=1);

namespace Communism;

/**
 * Reflection-style wrapper around executor-level state.
 */
final class Executor
{
    /**
     * Swaps two functions from the perspective of the global function table.
     *
     * @param callable-string $functionA
     * @param callable-string $functionB
     * @return void
     */
    public static function swapFunctions(string $functionA, string $functionB): void
    {
        __Underlying__::swapFunctions($functionA, $functionB);
    }
}
