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
 * Consumer: Users                                                            *
 * Purpose: High-level wrapper to perform powerful reflection on classes      *
 *============================================================================*/

declare(strict_types=1);

namespace Communism\Reflect;

use Communism\Internals\Zend;
use Zendful\ClassHandle;
use Zendful\PropertyHandle;
use Zendful\Zendful;

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
        Zendful::class($this->getName())->setFinal($do);
    }

    public function setReadonly(bool $do = true): void
    {
        Zendful::class($this->getName())->setReadonly($do);
    }

    public function setAbstract(bool $do = true): void
    {
        Zendful::class($this->getName())->setAbstract($do);
    }

    public function setTrait(bool $do = true): void
    {
        if ($do) {
            Zendful::class($this->getName())->setKind('trait');
        } else {
            Zendful::class($this->getName())->setKind(null);
        }
    }

    public function setEnum(bool $do = true): void
    {
        if ($do) {
            Zendful::class($this->getName())->setKind('enum');
        } else {
            Zendful::class($this->getName())->setKind(null);
        }
    }

    public function setInterface(bool $do = true): void
    {
        if ($do) {
            Zendful::class($this->getName())->setKind('interface');
        } else {
            Zendful::class($this->getName())->setKind(null);
        }
    }

    public function setAnonymous(bool $do = true): void
    {
        Zendful::class($this->getName())->setAnonymous($do);
    }

    /**
     * @template TResult
     *
     * @param callable():TResult $callback
     * @param-immediately-invoked-callable $callback
     *
     * @return TResult
     */
    public function withExtensible(callable $callback): mixed
    {
        $className = $this->getName();
        $clazz = Zendful::class($className);
        $wasFinal = $this->reflection->isFinal();
        $clazz->setFinal(false);
        Zendful::class($className)->disableJit();

        try {
            return $callback();
        } finally {
            if ($wasFinal) {
                $clazz->setFinal(true);
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
                    // @codeCoverageIgnoreStart
                    continue;
                    // @codeCoverageIgnoreEnd
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
     * Inject methods from a non-instantiable mixin class into this class.
     *
     * @param class-string $mixin
     * @param list<non-empty-string>|null $methods
     */
    public function inject(string $mixin, ?array $methods = null): void
    {
        Zend::injectMixinMethods($this->getName(), $mixin, $methods);
    }

    /**
     * @param class-string     $cls
     * @param non-empty-string $property
     */
    private function propertyReadonlyOnClass(string $cls, string $property, bool $do): void
    {
        if (!property_exists($cls, $property)) {
            return;
        }

        $propInfo = Zendful::property($cls, $property);
        $propInfo->setReadonly($do);

        Zendful::class($cls)->disableJit();
    }

}
