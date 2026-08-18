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
 * File: PropertyHandle.php                                                   *
 * Consumer: Internal                                                         *
 * Purpose: Validated handle for Zend property metadata.                      *
 *============================================================================*/

declare(strict_types=1);

namespace Zendful;

use InvalidArgumentException;

// Native alias is exercised by the separate native-backend probe.
// @codeCoverageIgnoreStart
if (class_exists('Zendful\\Native\\PropertyHandle', false)) {
    class_alias('Zendful\\Native\\PropertyHandle', PropertyHandle::class);
    // @codeCoverageIgnoreEnd
} else {
    final class PropertyHandle
    {
        public function __construct(
            private readonly string $className,
            private readonly string $propertyName,
        ) {
            if ($className === '' || $propertyName === '' || str_contains($className, "\0") || str_contains($propertyName, "\0")) {
                throw new \InvalidArgumentException('Property handles require non-empty names');
            }
        }

        public function className(): string
        {
            return $this->className;
        }

        public function propertyName(): string
        {
            return $this->propertyName;
        }

        public function exists(): bool
        {
            return Internals\Executor::propertyExists($this);
        }

        public function hasHooks(): bool
        {
            return Internals\Executor::propertyHasHooks($this);
        }

        public function setVisibility(string $visibility): void
        {
            Internals\Executor::setPropertyVisibility($this, $visibility);
        }

        public function clearVisibility(): void
        {
            Internals\Executor::clearPropertyVisibility($this);
        }

        public function setReadonly(bool $enabled = true): void
        {
            Internals\Executor::setPropertyReadonly($this, $enabled);
        }

        public function setSetVisibility(string $visibility, bool $enabled = true): void
        {
            Internals\Executor::setPropertySetVisibility($this, $visibility, $enabled);
        }

        public function withVisibility(string $visibility, callable $callback): mixed
        {
            return Internals\Executor::withPropertyVisibility($this, $visibility, $callback);
        }
    }
}
