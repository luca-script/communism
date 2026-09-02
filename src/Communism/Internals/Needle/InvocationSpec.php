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
 * File: InvocationSpec.php                                                   *
 * Consumer: Internal                                                         *
 * Purpose: Source file for InvocationSpec.php.                               *
 *============================================================================*/

declare(strict_types=1);

namespace Communism\Internals\Needle;

use Communism\Mixin\Desc;
use InvalidArgumentException;
use ReflectionFunctionAbstract;
use ReflectionNamedType;

use function ctype_digit;
use function is_array;
use function is_int;
use function is_string;
use function preg_match;
use function preg_quote;
use function sprintf;
use function str_contains;
use function str_starts_with;

/**
 * Normalized form of an invocation target specification.
 *
 * This is intentionally a small value object. It does not know anything
 * about zend_op; resolving an invocation remains the responsibility of
 * the injection-point resolver, which inspects surrounding SEND/DO instructions.
 */
final readonly class InvocationSpec
{
    public const string FUNCTION = 'function';
    public const string STATIC = 'static';
    public const string MEMBER = 'member';

    /**
     * @param list<int>|null $numArgs [min, max, step]
     */
    private function __construct(
        public string $kind,
        public ?string $class,
        public string $name,
        /** @var list<int>|null */
        public ?array $numArgs,
        /** @var array{parameters: list<string>, return: string}|null */
        public ?array $signature,
        /** @var list<string> */
        public array $aliases = [],
        public int $minMatches = 0,
        public ?int $maxMatches = null,
    ) {}

    /**
     * @param string|array<mixed>|Desc $spec
     */
    public static function parse(string|array|Desc $spec): self
    {
        if ($spec instanceof Desc) {
            return self::fromBase($spec->selector(), null, $spec->signature(), []);
        }
        if (is_string($spec)) {
            return self::fromBase($spec, null, null);
        }

        if ($spec === [] || !is_string($spec[0] ?? null)) {
            throw new InvalidArgumentException('An invocation spec must start with a base string');
        }

        $base = $spec[0];
        $numArgs = null;
        $signature = null;
        $aliases = [];
        $extensions = array_slice($spec, 1);
        if (count($extensions) === 1 && is_array($extensions[0]) && array_is_list($extensions[0])) {
            $extensions = $extensions[0];
        }

        foreach ($extensions as $extension) {
            if (!is_array($extension) || count($extension) !== 1 || (!array_key_exists('numargs', $extension) && !array_key_exists('signature', $extension) && !array_key_exists('aliases', $extension))) {
                throw new InvalidArgumentException('Unknown invocation-spec extension; expected ["numargs" => INTEGER_RANGE], ["signature" => SIGNATURE], or ["aliases" => NAME_LIST]');
            }
            if (array_key_exists('numargs', $extension)) {
                if ($numArgs !== null) {
                    throw new InvalidArgumentException('An invocation spec may contain numargs only once');
                }
                $numArgs = self::parseRange($extension['numargs']);
                continue;
            }
            if (array_key_exists('aliases', $extension)) {
                if (!is_array($extension['aliases']) || !array_is_list($extension['aliases']) || $extension['aliases'] === []) {
                    throw new InvalidArgumentException('Invocation ALIASES must be a non-empty list of names');
                }
                $parsedAliases = [];
                foreach ($extension['aliases'] as $alias) {
                    if (!is_string($alias) || $alias === '' || preg_match('/\s/', $alias) === 1) {
                        throw new InvalidArgumentException('Invocation ALIASES must contain non-empty names');
                    }
                    $parsedAliases[] = $alias;
                }
                $aliases = $parsedAliases;
                continue;
            }
            if ($signature !== null) {
                throw new InvalidArgumentException('An invocation spec may contain signature only once');
            }
            if (!is_array($extension['signature'])) {
                throw new InvalidArgumentException('Invocation SIGNATURE must be an array');
            }
            $signature = self::parseSignature($extension['signature']);
        }

        return self::fromBase($base, $numArgs, $signature, $aliases);
    }

    /**
     * @param array<mixed> $signature
     * @return array{parameters: list<string>, return: string}
     */
    private static function parseSignature(array $signature): array
    {
        if (!array_key_exists('parameters', $signature) || !array_key_exists('return', $signature)
            || !is_array($signature['parameters']) || !array_is_list($signature['parameters'])
            || !is_string($signature['return'])
        ) {
            throw new InvalidArgumentException('Invocation SIGNATURE must contain a parameter list and return type');
        }
        $parameters = [];
        foreach ($signature['parameters'] as $parameter) {
            if (!is_string($parameter)) {
                throw new InvalidArgumentException('Invocation SIGNATURE contains an invalid parameter type');
            }
            if ($parameter !== '*' && preg_match('/^\??[A-Za-z_][A-Za-z0-9_\\\\\/]*$/', $parameter) !== 1) {
                throw new InvalidArgumentException('Invocation SIGNATURE contains an invalid parameter type');
            }
            $parameters[] = $parameter;
        }
        $return = $signature['return'];
        if ($return !== '*' && preg_match('/^\??[A-Za-z_][A-Za-z0-9_\\\\\/]*$/', $return) !== 1) {
            throw new InvalidArgumentException('Invocation SIGNATURE contains an invalid return type');
        }

        return ['parameters' => $parameters, 'return' => $return];
    }

    /**
     * @param mixed $range
     * @return list<int>
     */
    private static function parseRange(mixed $range): array
    {
        if (is_int($range) && $range >= 0) {
            return [$range, $range, 1];
        }

        if (!is_array($range) || count($range) < 2 || count($range) > 3) {
            throw new InvalidArgumentException('INTEGER_RANGE must be an integer or [MIN, MAX, STEP]');
        }

        $values = array_values($range);
        $min = self::rangeInteger($values[0] ?? null);
        $max = self::rangeInteger($values[1] ?? null);
        $step = self::rangeInteger($values[2] ?? 1);
        if ($min > $max) {
            throw new InvalidArgumentException('INTEGER_RANGE must have MIN <= MAX and STEP > 0');
        }

        return [$min, $max, $step];
    }

    private static function rangeInteger(mixed $value): int
    {
        if (!is_int($value) || $value < 0) {
            throw new InvalidArgumentException('INTEGER_RANGE values must be non-negative integers');
        }

        return $value;
    }

    /**
     * @param string $base
     * @param list<int>|null $numArgs
     * @param array{parameters: list<string>, return: string}|null $signature
     * @param list<string> $aliases
     */
    private static function fromBase(string $base, ?array $numArgs, ?array $signature, array $aliases = []): self
    {
        if ($base === '' || preg_match('/\s/', $base) === 1) {
            throw new InvalidArgumentException(sprintf('Invalid invocation base %s', var_export($base, true)));
        }

        [$base, $minMatches, $maxMatches] = self::parseQuantifier($base);

        if (str_starts_with($base, '->')) {
            $name = substr($base, 2);
            self::assertName($name, $base);

            return new self(self::MEMBER, null, $name, $numArgs, $signature, $aliases, $minMatches, $maxMatches);
        }

        if (str_starts_with($base, '::')) {
            $name = substr($base, 2);
            self::assertName($name, $base);

            return new self(self::STATIC, null, $name, $numArgs, $signature, $aliases, $minMatches, $maxMatches);
        }

        if (str_contains($base, '::')) {
            [$class, $name] = explode('::', $base, 2);
            self::assertName($class, $base);
            self::assertName($name, $base);

            return new self(self::STATIC, $class, $name, $numArgs, $signature, $aliases, $minMatches, $maxMatches);
        }

        self::assertName($base, $base);

        return new self(self::FUNCTION, null, $base, $numArgs, $signature, $aliases, $minMatches, $maxMatches);
    }

    /** @return array{0: string, 1: int, 2: int|null} */
    private static function parseQuantifier(string $base): array
    {
        if (preg_match('/^(.*)\{(\d+)(?:,(\d*))?\}$/', $base, $matches) !== 1) {
            return [$base, 0, null];
        }

        $name = $matches[1];
        $min = (int) $matches[2];
        if (!isset($matches[3])) {
            return [$name, $min, $min];
        }

        $max = $matches[3] === '' ? null : (int) $matches[3];
        if ($max !== null && $min > $max) {
            throw new InvalidArgumentException('Invocation selector quantifier must have MIN <= MAX');
        }

        return [$name, $min, $max];
    }

    public function acceptsMatchCount(int $count): bool
    {
        return $count >= $this->minMatches && ($this->maxMatches === null || $count <= $this->maxMatches);
    }

    public function hasMinimumMatches(int $count): bool
    {
        return $count >= $this->minMatches;
    }

    private static function assertName(string $name, string $base): void
    {
        if ($name === '' || ctype_digit($name)) {
            throw new InvalidArgumentException(sprintf('Invalid invocation base %s', var_export($base, true)));
        }
    }

    public function acceptsArgumentCount(int $count): bool
    {
        if ($this->numArgs === null) {
            return true;
        }

        [$min, $max, $step] = $this->numArgs;

        return $count >= $min && $count <= $max && (($count - $min) % $step) === 0;
    }

    public function acceptsSignature(ReflectionFunctionAbstract $reflection): bool
    {
        if ($this->signature === null) {
            return true;
        }
        $expectedParameters = $this->signature['parameters'];
        $parameters = $reflection->getParameters();
        if (count($expectedParameters) !== count($parameters)) {
            return false;
        }
        foreach ($expectedParameters as $index => $expected) {
            $type = $parameters[$index]->getType();
            $expected = str_replace('/', '\\', $expected);
            if (!$type instanceof ReflectionNamedType || ($expected !== '*' && ltrim($expected, '?') !== $type->getName())) {
                return false;
            }
        }
        $return = $reflection->getReturnType();

        return $return instanceof ReflectionNamedType
            && ($this->signature['return'] === '*' || str_replace('/', '\\', ltrim($this->signature['return'], '?')) === $return->getName());
    }

    public static function matchesName(string $value, string $pattern): bool
    {
        if ($pattern === '*') {
            return true;
        }

        $expression = '/^' . str_replace('\\*', '.*', preg_quote($pattern, '/')) . '$/iD';

        return preg_match($expression, $value) === 1;
    }
}
