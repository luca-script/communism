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
 * File: FunctionHandle.php                                                   *
 * Consumer: Internal                                                         *
 * Purpose: Source file for FunctionHandle.php.                               *
 *============================================================================*/

declare(strict_types=1);

namespace Zendful;

use InvalidArgumentException;

/**
 * Opaque reference to a function in PHP's global function table.
 *
 * Consumers can pass this handle to Zendful operations without receiving a
 * pointer or other representation of the Zend runtime.
 */
// Native alias is exercised by the separate native-backend probe.
// @codeCoverageIgnoreStart
if (class_exists('Zendful\\Native\\FunctionHandle', false)) {
    class_alias('Zendful\\Native\\FunctionHandle', FunctionHandle::class);
    // @codeCoverageIgnoreEnd
} else {
    final class FunctionHandle
    {
        public function __construct(private readonly string $name)
        {
            if ($name === '' || str_contains($name, "\0")) {
                throw new InvalidArgumentException('A function name must not be empty.');
            }
        }

        public function name(): string
        {
            return $this->name;
        }

        public function exists(): bool
        {
            return Internals\Executor::functionExists($this);
        }

        public function isUserDefined(): bool
        {
            return Internals\Executor::isUserDefined($this);
        }

        public function hasBytecode(): bool
        {
            return Internals\Executor::hasBytecode($this);
        }

        public function disableJit(): void
        {
            Internals\Executor::disableJitForFunction($this);
        }

        public function opArray(): OpArrayHandle
        {
            return new OpArrayHandle($this);
        }

        public function swapWith(self $other): void
        {
            Internals\Executor::swapFunctions($this, $other);
        }
    }
}
