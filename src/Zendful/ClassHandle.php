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
 * File: ClassHandle.php                                                      *
 * Consumer: Internal                                                         *
 * Purpose: Validated handle for Zend class metadata.                         *
 *============================================================================*/

declare(strict_types=1);

namespace Zendful;

// Native alias is exercised by the separate native-backend probe.
// @codeCoverageIgnoreStart
if (class_exists('Zendful\\Native\\ClassHandle', false)) {
    class_alias('Zendful\\Native\\ClassHandle', ClassHandle::class);
    // @codeCoverageIgnoreEnd
} else {
    final class ClassHandle
    {
        public function __construct(private readonly string $className)
        {
            if ($className === '' || str_contains($className, "\0")) {
                throw new \InvalidArgumentException('Class handles require a non-empty class name');
            }
        }

        public function name(): string
        {
            return $this->className;
        }

        public function exists(): bool
        {
            return Internals\Executor::classExists($this);
        }

        public function disableJit(): void
        {
            Internals\Executor::disableJitForClass($this);
        }

        public function setFinal(bool $enabled = true): void
        {
            Internals\Executor::setClassFinal($this, $enabled);
        }

        public function isImmutable(): bool
        {
            return Internals\Executor::classIsImmutable($this);
        }

        public function setReadonly(bool $enabled = true): void
        {
            Internals\Executor::setClassReadonly($this, $enabled);
        }

        public function setAbstract(bool $enabled = true): void
        {
            Internals\Executor::setClassAbstract($this, $enabled);
        }

        public function setKind(?string $kind): void
        {
            Internals\Executor::setClassKind($this, $kind);
        }

        public function setAnonymous(bool $enabled = true): void
        {
            Internals\Executor::setClassAnonymous($this, $enabled);
        }

        public function staticsInitialized(): bool
        {
            return Internals\Executor::classStaticsInitialized($this);
        }

        public function initializeStatics(): void
        {
            Internals\Executor::initializeClassStatics($this);
        }

        public function implementInterface(self $interface): void
        {
            Internals\Executor::implementInterface($this, $interface);
        }
    }
}
