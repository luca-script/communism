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
 * File: ReflectionClass.php                                                  *
 * Purpose: High-level wrapper to perform powerful reflection on classes      *
 *============================================================================*/

declare(strict_types=1);

namespace Communism;

use function in_array;

/**
 * Reflection-style wrapper around a class.
 *
 * @api
 *
 * @template T of object
 */
final readonly class ReflectionClass
{
    /** @var \ReflectionClass<T> */
    private \ReflectionClass $reflection;

    /**
     * @param class-string<T>|T $class
     */
    public function __construct(object|string $class)
    {
        $this->reflection = new \ReflectionClass($class);
    }

    public function setFlag(int $flag, bool $do = true): void
    {
        $this->withClassEntry($this->getName(), function ($clazz) use ($flag, $do): void {
            $clazz->ce_flags = $this->setBits($clazz->ce_flags, $flag, $do);
        });
    }

    /**
     * @return class-string<T>
     */
    public function getName(): string
    {
        return $this->reflection->getName();
    }

    public function isTrait(): bool
    {
        return $this->reflection->isTrait();
    }

    public function isInterface(): bool
    {
        return $this->reflection->isInterface();
    }

    public function isEnum(): bool
    {
        return $this->reflection->isEnum();
    }

    public function isReadOnly(): bool
    {
        return $this->reflection->isReadOnly();
    }

    public function isFinal(): bool
    {
        return $this->reflection->isFinal();
    }

    public function isAbstract(): bool
    {
        return $this->reflection->isAbstract();
    }

    public function setFinal(bool $do = true): void
    {
        $this->setFlag(__Underlying__::ZEND_ACC_FINAL, $do);
    }

    public function setReadonly(bool $do = true): void
    {
        $this->setFlag(__Underlying__::ZEND_ACC_READONLY_CLASS, $do);
    }

    public function setAbstract(bool $do = true): void
    {
        $this->setFlag(__Underlying__::ZEND_ACC_ABSTRACT, $do);
    }

    public function setTrait(bool $do = true): void
    {
        if ($do) {
            $this->setClassKind($this->getName(), __Underlying__::ZEND_ACC_TRAIT);
        } else {
            $this->setClassKind($this->getName(), 0);
        }
    }

    public function setEnum(bool $do = true): void
    {
        if ($do) {
            $this->setClassKind($this->getName(), __Underlying__::ZEND_ACC_ENUM);
        } else {
            $this->setClassKind($this->getName(), 0);
        }
    }

    public function setInterface(bool $do = true): void
    {
        if ($do) {
            $this->setClassKind($this->getName(), __Underlying__::ZEND_ACC_INTERFACE);
        } else {
            $this->setClassKind($this->getName(), 0);
        }
    }

    public function setAnonymous(bool $do = true): void
    {
        $this->setFlag(__Underlying__::ZEND_ACC_ANON_CLASS, $do);
    }

    /**
     * @template TResult
     *
     * @param callable():TResult $callback
     *
     * @return TResult
     */
    public function withExtensible(callable $callback): mixed
    {
        $className = $this->getName();
        $clazz = __Underlying__::lookupClass($className);
        $originalFlags = $clazz->ce_flags;
        $wasFinal = ($originalFlags & __Underlying__::ZEND_ACC_FINAL) !== 0;
        $clazz->ce_flags = $originalFlags & ~__Underlying__::ZEND_ACC_FINAL;
        __Underlying__::disableJitForClass($className);
        __Underlying__::blacklistCurrentCallers();

        try {
            return $callback();
        } finally {
            if ($wasFinal) {
                $clazz->ce_flags |= __Underlying__::ZEND_ACC_FINAL;
            }
        }
    }

    public function propertiesReadonly(bool $do = true): void
    {
        $propertyNames = [];
        foreach ($this->reflection->getProperties() as $reflectionProperty) {
            $propertyNames[$reflectionProperty->getName()] = true;
        }

        if ($this->reflection->isTrait()) {
            $traitName = $this->reflection->getName();

            foreach (get_declared_classes() as $declaredClass) {
                $classRef = new \ReflectionClass($declaredClass);
                if ($classRef->isInternal()) {
                    continue;
                }

                if ($classRef->isTrait()) {
                    continue;
                }

                if (!in_array($traitName, $classRef->getTraitNames(), true)) {
                    continue;
                }

                foreach (array_keys($propertyNames) as $propertyName) {
                    $this->propertyReadonlyOnClass($declaredClass, $propertyName, $do);
                }
            }

            return;
        }

        foreach (array_keys($propertyNames) as $propertyName) {
            $this->propertyReadonlyOnClass($this->getName(), $propertyName, $do);
        }

        $this->setReadonly($do);
    }

    /**
     * @param non-empty-string $name
     */
    public function getProperty(string $name): ReflectionProperty
    {
        return new ReflectionProperty($this->getName(), $name);
    }

    /**
     * @param non-empty-string $name
     */
    public function getMethod(string $name): ReflectionMethod
    {
        return new ReflectionMethod($this->getName(), $name);
    }

    /**
     * @return list<ReflectionProperty>
     */
    public function getProperties(): array
    {
        $properties = [];
        foreach ($this->reflection->getProperties() as $reflectionProperty) {
            $properties[] = new ReflectionProperty($reflectionProperty->getDeclaringClass()->getName(), $reflectionProperty->getName());
        }

        return $properties;
    }

    /**
     * @return list<ReflectionMethod>
     */
    public function getMethods(): array
    {
        $methods = [];
        foreach ($this->reflection->getMethods() as $reflectionMethod) {
            $methods[] = new ReflectionMethod($reflectionMethod->getDeclaringClass()->getName(), $reflectionMethod->getName());
        }

        return $methods;
    }

    /**
     * @param class-string $cls
     * @param callable(\Communism_FFI\zend_class_entry): void $cb
     */
    private function withClassEntry(string $cls, callable $cb): void
    {
        try {
            $cb(__Underlying__::lookupClass($cls));
        } finally {
            __Underlying__::disableJitForClass($cls);
            __Underlying__::blacklistCurrentCallers();
        }
    }

    private function setBits(int $value, int $flag, bool $do): int
    {
        return $do ? ($value | $flag) : ($value & ~$flag);
    }

    /**
     * @param class-string     $cls
     * @param non-empty-string $property
     */
    private function propertyReadonlyOnClass(string $cls, string $property, bool $do): void
    {
        $propInfo = __Underlying__::lookupPropertyInfo($cls, $property);
        if ($propInfo === null) {
            return;
        }

        if ($do) {
            $propInfo->flags |= __Underlying__::ZEND_ACC_READONLY;
            if (($propInfo->flags & (__Underlying__::ZEND_ACC_PUBLIC | __Underlying__::ZEND_ACC_READONLY | __Underlying__::ZEND_ACC_PPP_SET_MASK)) === (__Underlying__::ZEND_ACC_PUBLIC | __Underlying__::ZEND_ACC_READONLY)) {
                $propInfo->flags |= __Underlying__::ZEND_ACC_PROTECTED_SET;
            }
        } else {
            $propInfo->flags &= ~__Underlying__::ZEND_ACC_READONLY;
            $propInfo->flags &= ~__Underlying__::ZEND_ACC_PPP_SET_MASK;
        }

        __Underlying__::disableJitForClass($cls);
        __Underlying__::blacklistCurrentCallers();
    }

    /**
     * @param class-string $cls
     */
    private function setClassKind(string $cls, int $kind): void
    {
        $this->withClassEntry($cls, function ($clazz) use ($kind): void {
            $clazz->ce_flags = ($clazz->ce_flags & ~(
                __Underlying__::ZEND_ACC_FINAL
                | __Underlying__::ZEND_ACC_INTERFACE
                | __Underlying__::ZEND_ACC_ENUM
                | __Underlying__::ZEND_ACC_TRAIT
            )) | $kind;
        });
    }
}
