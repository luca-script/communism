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
 * File: MethodHandle.php                                                     *
 * Consumer: Internal                                                         *
 * Purpose: Source file for MethodHandle.php.                                 *
 *============================================================================*/

declare(strict_types=1);

namespace Zendful;

use InvalidArgumentException;

// Native alias is exercised by the separate native-backend probe.
// @codeCoverageIgnoreStart
if (class_exists('Zendful\\Native\\MethodHandle', false)) {
    class_alias('Zendful\\Native\\MethodHandle', MethodHandle::class);
    // @codeCoverageIgnoreEnd
} else {
    final class MethodHandle
    {
        public function __construct(
            private readonly string $className,
            private readonly string $methodName,
        ) {
            if ($className === '' || $methodName === '' || str_contains($className, "\0") || str_contains($methodName, "\0")) {
                throw new InvalidArgumentException('A method handle requires a class and method name.');
            }
        }

        public function className(): string
        {
            return $this->className;
        }

        public function methodName(): string
        {
            return $this->methodName;
        }

        public function exists(): bool
        {
            return Internals\Executor::methodExists($this);
        }

        public function isUserDefined(): bool
        {
            return Internals\Executor::isUserDefinedMethod($this);
        }

        public function hasBytecode(): bool
        {
            return Internals\Executor::methodHasBytecode($this);
        }

        public function disableJit(): void
        {
            Internals\Executor::disableJitForMethod($this);
        }

        public function setVisibility(string $visibility): void
        {
            Internals\Executor::setMethodVisibility($this, $visibility);
        }

        public function clearVisibility(): void
        {
            Internals\Executor::clearMethodVisibility($this);
        }

        public function setStatic(bool $enabled = true): void
        {
            Internals\Executor::setMethodStatic($this, $enabled);
        }

        public function setFinal(bool $enabled = true): void
        {
            Internals\Executor::setMethodFinal($this, $enabled);
        }

        public function withVisibility(string $visibility, callable $callback): mixed
        {
            return Internals\Executor::withMethodVisibility($this, $visibility, $callback);
        }

        public function swapWith(self $other): void
        {
            Internals\Executor::swapMethods($this, $other);
        }

        public function installInto(ClassHandle $class, string $name, bool $final = false): void
        {
            if ($name === '' || str_contains($name, "\0")) {
                throw new InvalidArgumentException('An installed method requires a name.');
            }

            Internals\Executor::installMethod($this, $class, $name, $final);
        }

        public function renameTo(string $name): void
        {
            if ($name === '' || str_contains($name, "\0")) {
                throw new InvalidArgumentException('A method requires a name.');
            }

            Internals\Executor::renameMethod($this, $name);
        }

        public function installGeneratedInto(ClassHandle $class, self $template, string $name): void
        {
            if ($name === '' || str_contains($name, "\0")) {
                throw new InvalidArgumentException('A generated method requires a name.');
            }

            Internals\Executor::installGeneratedMethod($this, $template, $class, $name);
        }

        public function opArray(): OpArrayHandle
        {
            return new OpArrayHandle($this);
        }
    }
}
