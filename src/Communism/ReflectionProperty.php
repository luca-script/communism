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
 * File: ReflectionProperty.php                                               *
 * Purpose: High-level wrapper to perform powerful reflection on properties   *
 *============================================================================*/

declare(strict_types=1);

namespace Communism;

use InvalidArgumentException;
use LogicException;

use function sprintf;

/**
 * Reflection-style wrapper around a property.
 */
final readonly class ReflectionProperty
{
    private \ReflectionProperty $reflection;

    /**
     * @param object|class-string $class
     */
    public function __construct(object|string $class, string $property)
    {
        $this->reflection = new \ReflectionProperty($class, $property);
    }

    /**
     * @template T
     *
     * @param callable():T $callback
     *
     * @return T
     */
    public function withVisibility(Visibility $visibility, callable $callback): mixed
    {
        $className = $this->ensureDeclaringClassInitialized();
        $propInfo = __Underlying__::lookupPropertyInfo($className, $this->getName());

        if ($propInfo === null) {
            return $callback();
        }

        $originalFlags = $propInfo->flags;
        $propInfo->flags = $this->setVisibilityBits($originalFlags, $visibility->value, __Underlying__::ZEND_ACC_PPP_MASK, true);
        __Underlying__::disableJitForClass($className);
        __Underlying__::blacklistCurrentCallers();

        try {
            return $callback();
        } finally {
            $propInfo->flags = $originalFlags;
        }
    }

    /**
     * @template T
     *
     * @param callable():T $callback
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
     *
     * @return T
     */
    public function withPrivate(callable $callback): mixed
    {
        return $this->withVisibility(Visibility::Private, $callback);
    }

    /**
     * @return string
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

    public function isReadOnly(): bool
    {
        return $this->reflection->isReadOnly();
    }

    public function setFlag(int $flag, bool $do = true): void
    {
        $className = $this->ensureDeclaringClassInitialized();
        $this->withPropertyInfo($className, $this->getName(), function ($propInfo) use ($flag, $do): void {
            $propInfo->flags = $this->setBits($propInfo->flags, $flag, $do);
        });
    }

    public function setPublic(bool $do = true): void
    {
        $className = $this->ensureDeclaringClassInitialized();
        $this->withPropertyInfo($className, $this->getName(), function ($propInfo) use ($do): void {
            $propInfo->flags = $this->setVisibilityBits($propInfo->flags, __Underlying__::ZEND_ACC_PUBLIC, __Underlying__::ZEND_ACC_PPP_MASK, $do);
        });
    }

    public function setProtected(bool $do = true): void
    {
        $className = $this->ensureDeclaringClassInitialized();
        $this->withPropertyInfo($className, $this->getName(), function ($propInfo) use ($do): void {
            $propInfo->flags = $this->setVisibilityBits($propInfo->flags, __Underlying__::ZEND_ACC_PROTECTED, __Underlying__::ZEND_ACC_PPP_MASK, $do);
        });
    }

    public function setPrivate(bool $do = true): void
    {
        $className = $this->ensureDeclaringClassInitialized();
        $this->withPropertyInfo($className, $this->getName(), function ($propInfo) use ($do): void {
            $propInfo->flags = $this->setVisibilityBits($propInfo->flags, __Underlying__::ZEND_ACC_PRIVATE, __Underlying__::ZEND_ACC_PPP_MASK, $do);
        });
    }

    public function setReadonly(bool $do = true): void
    {
        $className = $this->ensureDeclaringClassInitialized();
        $property = $this->getName();
        $this->withPropertyInfo($className, $property, function ($propInfo) use ($do): void {
            if ($do) {
                $propInfo->flags |= __Underlying__::ZEND_ACC_READONLY;
                if (($propInfo->flags & (__Underlying__::ZEND_ACC_PUBLIC | __Underlying__::ZEND_ACC_READONLY | __Underlying__::ZEND_ACC_PPP_SET_MASK)) === (__Underlying__::ZEND_ACC_PUBLIC | __Underlying__::ZEND_ACC_READONLY)) {
                    $propInfo->flags |= __Underlying__::ZEND_ACC_PROTECTED_SET;
                }
            } else {
                $propInfo->flags &= ~__Underlying__::ZEND_ACC_READONLY;
                $propInfo->flags &= ~__Underlying__::ZEND_ACC_PPP_SET_MASK;
            }
        });
    }

    public function setStaticValue(mixed $value): void
    {
        if (!$this->reflection->isStatic()) {
            throw new LogicException(sprintf('Property %s::$%s is not static', $this->reflection->getDeclaringClass()->getName(), $this->getName()));
        }

        $this->writeStaticValue($value);
    }

    public function setValueOnInstance(object $instance, mixed $value): void
    {
        if ($this->reflection->isStatic()) {
            throw new LogicException(sprintf('Property %s::$%s is static', $this->reflection->getDeclaringClass()->getName(), $this->getName()));
        }

        if (!$instance instanceof ($this->reflection->getDeclaringClass()->getName())) {
            throw new InvalidArgumentException(sprintf('Instance must be of type %s', $this->reflection->getDeclaringClass()->getName()));
        }

        $this->writeValueOnInstance($instance, $value);
    }

    public function setPublicSet(bool $do = true): void
    {
        $className = $this->ensureDeclaringClassInitialized();
        if ($this->reflection->isStatic()) {
            return;
        }

        $this->withPropertyInfo($className, $this->getName(), function ($propInfo) use ($do): void {
            if ($propInfo->hooks === null) {
                return;
            }

            $propInfo->flags = $this->setVisibilityBits($propInfo->flags, __Underlying__::ZEND_ACC_PUBLIC_SET, __Underlying__::ZEND_ACC_PPP_SET_MASK, $do);
        });
    }

    public function setProtectedSet(bool $do = true): void
    {
        $className = $this->ensureDeclaringClassInitialized();
        if ($this->reflection->isStatic()) {
            return;
        }

        $this->withPropertyInfo($className, $this->getName(), function ($propInfo) use ($do): void {
            if ($propInfo->hooks === null) {
                return;
            }

            $propInfo->flags = $this->setVisibilityBits($propInfo->flags, __Underlying__::ZEND_ACC_PROTECTED_SET, __Underlying__::ZEND_ACC_PPP_SET_MASK, $do);
        });
    }

    public function setPrivateSet(bool $do = true): void
    {
        $className = $this->ensureDeclaringClassInitialized();
        if ($this->reflection->isStatic()) {
            return;
        }

        $this->withPropertyInfo($className, $this->getName(), function ($propInfo) use ($do): void {
            if ($propInfo->hooks === null) {
                return;
            }

            $propInfo->flags = $this->setVisibilityBits($propInfo->flags, __Underlying__::ZEND_ACC_PRIVATE_SET, __Underlying__::ZEND_ACC_PPP_SET_MASK, $do);
        });
    }

    /**
     * @param class-string $cls
     * @param callable(\Communism_FFI\zend_property_info): void $cb
     */
    private function withPropertyInfo(string $cls, string $property, callable $cb): void
    {
        $propInfo = __Underlying__::lookupPropertyInfo($cls, $property);
        if ($propInfo !== null) {
            try {
                $cb($propInfo);
            } finally {
                __Underlying__::disableJitForClass($cls);
                __Underlying__::blacklistCurrentCallers();
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

    /**
     * @return class-string
     */
    private function ensureDeclaringClassInitialized(): string
    {
        $className = $this->reflection->getDeclaringClass()->getName();
        if (!__Underlying__::classStaticsInitialized($className)) {
            __Underlying__::initClassStatics($className);
        }

        return $className;
    }

    private function writeStaticValue(mixed $value): void
    {
        $this->reflection->setValue($value);
    }

    private function writeValueOnInstance(object $instance, mixed $value): void
    {
        $this->reflection->setValue($instance, $value);
    }
}
