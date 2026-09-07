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
 * Consumer: Users                                                            *
 * Purpose: High-level wrapper to perform powerful reflection on properties   *
 *============================================================================*/

declare(strict_types=1);

namespace Communism\Reflect;

use InvalidArgumentException;
use LogicException;
use Zendful\PropertyHandle;
use Zendful\Zendful;

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
        $propInfo = Zendful::property($className, $this->getName());

        return $propInfo->withVisibility(match ($visibility) {
            Visibility::Public => 'public',
            Visibility::Protected => 'protected',
            Visibility::Private => 'private',
        }, $callback);
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

    public function setPublic(bool $do = true): void
    {
        $className = $this->ensureDeclaringClassInitialized();
        $this->withPropertyInfo($className, $this->getName(), function (PropertyHandle $propInfo) use ($do): void {
            $do ? $propInfo->setVisibility('public') : $propInfo->clearVisibility();
        });
    }

    public function setProtected(bool $do = true): void
    {
        $className = $this->ensureDeclaringClassInitialized();
        $this->withPropertyInfo($className, $this->getName(), function (PropertyHandle $propInfo) use ($do): void {
            $do ? $propInfo->setVisibility('protected') : $propInfo->clearVisibility();
        });
    }

    public function setPrivate(bool $do = true): void
    {
        $className = $this->ensureDeclaringClassInitialized();
        $this->withPropertyInfo($className, $this->getName(), function (PropertyHandle $propInfo) use ($do): void {
            $do ? $propInfo->setVisibility('private') : $propInfo->clearVisibility();
        });
    }

    public function setReadonly(bool $do = true): void
    {
        $className = $this->ensureDeclaringClassInitialized();
        $property = $this->getName();
        $this->withPropertyInfo($className, $property, function (PropertyHandle $propInfo) use ($do): void {
            $propInfo->setReadonly($do);
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

        $this->withPropertyInfo($className, $this->getName(), function (PropertyHandle $propInfo) use ($do): void {
            if (!$propInfo->hasHooks()) {
                return;
            }

            $propInfo->setSetVisibility('public', $do);
        });
    }

    public function setProtectedSet(bool $do = true): void
    {
        $className = $this->ensureDeclaringClassInitialized();
        if ($this->reflection->isStatic()) {
            return;
        }

        $this->withPropertyInfo($className, $this->getName(), function (PropertyHandle $propInfo) use ($do): void {
            if (!$propInfo->hasHooks()) {
                return;
            }

            $propInfo->setSetVisibility('protected', $do);
        });
    }

    public function setPrivateSet(bool $do = true): void
    {
        $className = $this->ensureDeclaringClassInitialized();
        if ($this->reflection->isStatic()) {
            return;
        }

        $this->withPropertyInfo($className, $this->getName(), function (PropertyHandle $propInfo) use ($do): void {
            if (!$propInfo->hasHooks()) {
                return;
            }

            $propInfo->setSetVisibility('private', $do);
        });
    }

    /**
     * @param class-string $cls
     * @param callable(PropertyHandle): void $cb
     */
    private function withPropertyInfo(string $cls, string $property, callable $cb): void
    {
        $propInfo = Zendful::property($cls, $property);
        if ($propInfo->exists()) {
            try {
                $cb($propInfo);
            } finally {
                Zendful::class($cls)->disableJit();
            }
        }
    }

    /**
     * @return class-string
     */
    private function ensureDeclaringClassInitialized(): string
    {
        $className = $this->reflection->getDeclaringClass()->getName();
        $class = Zendful::class($className);
        if (!$class->staticsInitialized()) {
            $class->initializeStatics();
        }

        return $className;
    }

    private function writeStaticValue(mixed $value): void
    {
        $this->reflection->setValue(null, $value);
    }

    private function writeValueOnInstance(object $instance, mixed $value): void
    {
        $this->reflection->setValue($instance, $value);
    }
}
