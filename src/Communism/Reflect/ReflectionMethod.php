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

use Communism\Internals\Zend;

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
        $methodInfo = Zend::lookupMethod($this->reflection->getDeclaringClass()->getName(), $this->getName());

        if ($methodInfo === null) {
            return $callback();
        }

        $originalFlags = $methodInfo->fn_flags;
        $methodInfo->fn_flags = $this->setVisibilityBits($originalFlags, $visibility->value, Zend::ZEND_ACC_PPP_MASK, true);
        Zend::disableJitForMethod($this->reflection->getDeclaringClass()->getName(), $this->getName());
        Zend::blacklistCurrentCallers();

        try {
            return $callback();
        } finally {
            $methodInfo->fn_flags = $originalFlags;
        }
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
        Zend::swapMethods(
            $this->reflection->getDeclaringClass()->getName(),
            $this->getName(),
            $replacement->reflection->getDeclaringClass()->getName(),
            $replacement->getName(),
        );
    }

    public function setFlag(int $flag, bool $do = true): void
    {
        $this->withMethodEntry($this->reflection->getDeclaringClass()->getName(), $this->getName(), function ($func) use ($flag, $do): void {
            $func->fn_flags = $this->setBits($func->fn_flags, $flag, $do);
        });
    }

    public function setPublic(bool $do = true): void
    {
        $this->withMethodEntry($this->reflection->getDeclaringClass()->getName(), $this->getName(), function ($func) use ($do): void {
            $func->fn_flags = $this->setVisibilityBits($func->fn_flags, Zend::ZEND_ACC_PUBLIC, Zend::ZEND_ACC_PPP_MASK, $do);
        });
    }

    public function setProtected(bool $do = true): void
    {
        $this->withMethodEntry($this->reflection->getDeclaringClass()->getName(), $this->getName(), function ($func) use ($do): void {
            $func->fn_flags = $this->setVisibilityBits($func->fn_flags, Zend::ZEND_ACC_PROTECTED, Zend::ZEND_ACC_PPP_MASK, $do);
        });
    }

    public function setPrivate(bool $do = true): void
    {
        $this->withMethodEntry($this->reflection->getDeclaringClass()->getName(), $this->getName(), function ($func) use ($do): void {
            $func->fn_flags = $this->setVisibilityBits($func->fn_flags, Zend::ZEND_ACC_PRIVATE, Zend::ZEND_ACC_PPP_MASK, $do);
        });
    }

    public function setStatic(bool $do = true): void
    {
        $this->setFlag(Zend::ZEND_ACC_STATIC, $do);
    }

    /**
     * @param class-string $cls
     * @param non-empty-string $method
     * @param callable(\Communism_FFI\zend_function): void $cb
     */
    private function withMethodEntry(string $cls, string $method, callable $cb): void
    {
        $func = Zend::lookupMethod($cls, $method);
        if ($func !== null) {
            try {
                $cb($func);
            } finally {
                Zend::disableJitForMethod($cls, $method);
                Zend::blacklistCurrentCallers();
            }
        }
    }

    private function setBits(int $value, int $flag, bool $do): int
    {
        return $do ? ($value | $flag) : ($value & ~$flag);
    }

    private function setVisibilityBits(int $value, int $visibility, int $mask, bool $do): int
    {
        if ($do) {
            return ($value & ~$mask) | $visibility;
        }

        return $value & ~$mask;
    }
}
