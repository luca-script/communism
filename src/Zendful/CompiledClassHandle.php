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
 * File: CompiledClassHandle.php                                              *
 * Consumer: Internal                                                         *
 * Purpose: Safe handle for a detached compiled class.                        *
 *============================================================================*/

declare(strict_types=1);

namespace Zendful;

use InvalidArgumentException;

use function strtolower;

/** @internal */
// Native alias is exercised by the separate native-backend probe.
// @codeCoverageIgnoreStart
if (class_exists('Zendful\\Native\\CompiledClassHandle', false)) {
    class_alias('Zendful\\Native\\CompiledClassHandle', CompiledClassHandle::class);
    // @codeCoverageIgnoreEnd
} else {
    final readonly class CompiledClassHandle
    {
        /** @param array<string, CompiledMethodHandle> $methods */
        public function __construct(
            public string $name,
            private array $methods,
        ) {
            if ($name === '' || str_contains($name, "\0")) {
                throw new InvalidArgumentException('Compiled class names must not be empty or contain NUL bytes.');
            }

            self::validateMethods($methods);
        }

        /** @param array<int|string, mixed> $methods */
        private static function validateMethods(array $methods): void
        {
            foreach ($methods as $name => $method) {
                if (!is_string($name) || $name === '' || str_contains($name, "\0") || !$method instanceof CompiledMethodHandle) {
                    throw new InvalidArgumentException('Compiled class methods must use valid named method handles.');
                }
            }
        }

        public function method(string $name): ?CompiledMethodHandle
        {
            if ($name === '' || str_contains($name, "\0")) {
                throw new InvalidArgumentException('Compiled method names must not be empty or contain NUL bytes.');
            }

            return $this->methods[strtolower($name)] ?? null;
        }

        /** @return array<string, CompiledMethodHandle> */
        public function methods(): array
        {
            return $this->methods;
        }
    }
}
