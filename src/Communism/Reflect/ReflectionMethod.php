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
 * File: ReflectionMethod.php                                                 *
 * Consumer: Users                                                            *
 * Purpose: High-level wrapper to perform powerful reflection on methods      *
 *============================================================================*/

declare(strict_types=1);

namespace Communism\Reflect;

use Zendful\MethodHandle;
use Zendful\Zendful;

/**
 * Reflection-style wrapper around a method.
 */
final readonly class ReflectionMethod
{
    private \ReflectionMethod $reflection;

    public function __construct(object|string $class, string $method)
    {
        $this->reflection = new \ReflectionMethod($class, $method);
    }

    /**
     * @template T
     *
     * @param callable():T $callback
     * @param-immediately-invoked-callable $callback
     *
     * @return T
     */
    public function withVisibility(Visibility $visibility, callable $callback): mixed
    {
        $methodInfo = Zendful::method($this->reflection->getDeclaringClass()->getName(), $this->getName());

        if (!$methodInfo->exists()) {
            // @codeCoverageIgnoreStart
            return $callback();
            // @codeCoverageIgnoreEnd
        }

        return $methodInfo->withVisibility(match ($visibility) {
            Visibility::Public => 'public',
            Visibility::Protected => 'protected',
            Visibility::Private => 'private',
        }, $callback);
    }

    /**
     * @template T
     *
     * @param callable():T $callback
     * @param-immediately-invoked-callable $callback
     *
     * @return T
     */
    public function withPublic(callable $callback): mixed
    {
        return $this->withVisibility(Visibility::Public, $callback);
    }

    /**
     * @template T
     *
     * @param callable():T $callback
     * @param-immediately-invoked-callable $callback
     *
     * @return T
     */
    public function withProtected(callable $callback): mixed
    {
        return $this->withVisibility(Visibility::Protected, $callback);
    }

    /**
     * @template T
     *
     * @param callable():T $callback
     * @param-immediately-invoked-callable $callback
     *
     * @return T
     */
    public function withPrivate(callable $callback): mixed
    {
        return $this->withVisibility(Visibility::Private, $callback);
    }

    /**
     * @return non-empty-string
     */
    public function getName(): string
    {
        return $this->reflection->getName();
    }

    /**
     * @return ReflectionClass<object>
     */
    public function getDeclaringClass(): ReflectionClass
    {
        return new ReflectionClass($this->reflection->getDeclaringClass()->getName());
    }

    public function isStatic(): bool
    {
        return $this->reflection->isStatic();
    }

    /**
     * Replace this method with the implementation of a method declared by a
     * derived class.
     */
    public function swap(ReflectionMethod $replacement): void
    {
        Zendful::method($this->reflection->getDeclaringClass()->getName(), $this->getName())->swapWith(
            Zendful::method($replacement->reflection->getDeclaringClass()->getName(), $replacement->getName()),
        );
    }

    public function setPublic(bool $do = true): void
    {
        $this->withMethodEntry($this->reflection->getDeclaringClass()->getName(), $this->getName(), function (MethodHandle $func) use ($do): void {
            $do ? $func->setVisibility('public') : $func->clearVisibility();
        });
    }

    public function setProtected(bool $do = true): void
    {
        $this->withMethodEntry($this->reflection->getDeclaringClass()->getName(), $this->getName(), function (MethodHandle $func) use ($do): void {
            $do ? $func->setVisibility('protected') : $func->clearVisibility();
        });
    }

    public function setPrivate(bool $do = true): void
    {
        $this->withMethodEntry($this->reflection->getDeclaringClass()->getName(), $this->getName(), function (MethodHandle $func) use ($do): void {
            $do ? $func->setVisibility('private') : $func->clearVisibility();
        });
    }

    public function setStatic(bool $do = true): void
    {
        $this->withMethodEntry($this->reflection->getDeclaringClass()->getName(), $this->getName(), function (MethodHandle $func) use ($do): void {
            $func->setStatic($do);
        });
    }

    /**
     * @param class-string $cls
     * @param non-empty-string $method
     * @param callable(MethodHandle): void $cb
     */
    private function withMethodEntry(string $cls, string $method, callable $cb): void
    {
        $func = Zendful::method($cls, $method);
        if ($func->exists()) {
            try {
                $cb($func);
            } finally {
                Zendful::method($cls, $method)->disableJit();
            }
        }
    }

}
