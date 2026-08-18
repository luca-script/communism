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
 * File: AccessorInvokerRuntime.php                                           *
 * Consumer: Internal                                                         *
 * Purpose: Runtime implementation for generated Mixin accessors/invokers.    *
 *============================================================================*/

declare(strict_types=1);

namespace Communism\Internals;

use InvalidArgumentException;
use LogicException;

/** @internal Implementation detail for generated accessor/invoker bodies. */
final class AccessorInvokerRuntime
{
    /** @var array<string, array{property: string, setter: bool}> */
    private static array $accessors = [];

    /** @var array<string, string> */
    private static array $invokers = [];

    public static function registerAccessor(string $class, string $method, string $property, bool $setter): void
    {
        self::$accessors[self::key($class, $method)] = ['property' => $property, 'setter' => $setter];
    }

    public static function registerInvoker(string $class, string $method, string $target): void
    {
        self::$invokers[self::key($class, $method)] = $target;
    }

    /** @param array<mixed> $arguments */
    public static function access(?object $object, array $arguments): mixed
    {
        [$class, $method] = self::caller();
        $key = self::key($class, $method);
        $accessor = self::$accessors[$key] ?? throw new LogicException(sprintf('No generated accessor is registered for %s::%s', $class, $method));
        $property = $accessor['property'];
        $reflection = new \ReflectionProperty($class, $property);

        if ($arguments !== []) {
            if (!$accessor['setter'] || count($arguments) !== 1) {
                throw new InvalidArgumentException(sprintf('Getter %s::%s does not accept a value', $class, $method));
            }
            $reflection->setValue($reflection->isStatic() ? null : $object, $arguments[0]);

            return null;
        }

        if ($reflection->isStatic()) {
            return $reflection->getValue();
        }
        if ($object === null) {
            throw new LogicException(sprintf('Instance accessor %s::%s was invoked statically', $class, $method));
        }

        return $reflection->getValue($object);
    }

    /** @param array<mixed> $arguments */
    public static function invoke(?object $object, array $arguments): mixed
    {
        [$class, $method] = self::caller();
        $key = self::key($class, $method);
        $target = self::$invokers[$key] ?? throw new LogicException(sprintf('No generated invoker is registered for %s::%s', $class, $method));
        $reflection = new \ReflectionMethod($class, $target);

        if ($reflection->isStatic()) {
            return $reflection->invokeArgs(null, $arguments);
        }
        if ($object === null) {
            throw new LogicException(sprintf('Instance invoker %s::%s was invoked statically', $class, $method));
        }

        return $reflection->invokeArgs($object, $arguments);
    }

    /** @return array{0: class-string, 1: non-empty-string} */
    private static function caller(): array
    {
        $frame = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3)[2] ?? null;
        if ($frame === null || !isset($frame['class'])) {
            throw new LogicException('A generated accessor or invoker must be called as a class method');
        }

        $method = $frame['function'];
        if ($method === '') {
            // @codeCoverageIgnoreStart
            throw new LogicException('A generated accessor or invoker must be called as a class method');
            // @codeCoverageIgnoreEnd
        }

        return [$frame['class'], $method];
    }

    /** @return non-empty-string */
    private static function key(string $class, string $method): string
    {
        return strtolower($class . '::' . $method);
    }
}
