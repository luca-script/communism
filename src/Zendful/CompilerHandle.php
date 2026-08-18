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
 * :: Zendful :: "When PHP doesn't provide it, we do!" ::                     *
 *----------------------------------------------------------------------------*
 * File: CompilerHandle.php                                                   *
 * Consumer: Internal                                                         *
 * Purpose: Backend-neutral compiler entry point.                             *
 *============================================================================*/

declare(strict_types=1);

namespace Zendful;

// Native alias is exercised by the separate native-backend probe.
// @codeCoverageIgnoreStart
if (class_exists('Zendful\\Native\\CompilerHandle', false)) {
    class_alias('Zendful\\Native\\CompilerHandle', CompilerHandle::class);
    // @codeCoverageIgnoreEnd
} else {
    /** Backend-neutral compiler handle. */
    final class CompilerHandle
    {
        public static function compileFile(string $filename): CompiledFileHandle
        {
            if ($filename === '' || str_contains($filename, "\0")) {
                throw new \InvalidArgumentException('Compiler filenames must not be empty or contain NUL bytes.');
            }
            return Internals\Compiler::compileFile($filename);
        }
    }
}
