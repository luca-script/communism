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
 * File: Runtime.php                                                          *
 * Consumer: Users                                                            *
 * Purpose: Source file for Runtime.php.                                      *
 *============================================================================*/

declare(strict_types=1);

namespace Communism\Mixin;

use Communism\Internals\Zend;
use InvalidArgumentException;
use ReflectionClass;

use function class_exists;
use function get_declared_classes;
use function get_debug_type;
use function is_a;
use function is_string;
use function sprintf;

/** Installs and applies manifest-defined mixin transformations. */
final class Runtime
{
    private static ?self $installed = null;

    /** @var array<class-string<Manifest>, Manifest> */
    private array $single = [];

    /** @var list<Manifest> */
    private array $manifests = [];

    private function __construct() {}

    public static function installOrGet(): self
    {
        return self::$installed ??= new self();
    }

    /** @param string|Manifest $manifest */
    public function add(string|Manifest $manifest): self
    {
        if (is_string($manifest) && isset($this->single[$manifest])) {
            return $this;
        }
        $instance = $this->resolve($manifest);
        $this->apply($instance);
        if ($instance::isSingle()) {
            $this->single[$instance::class] = $instance;
        }
        $this->manifests[] = $instance;

        return $this;
    }

    /** @return list<Manifest> */
    public function manifests(): array
    {
        return $this->manifests;
    }

    /** @param string|Manifest $manifest */
    private function resolve(string|Manifest $manifest): Manifest
    {
        if ($manifest instanceof Manifest) {
            if ($manifest::isSingle()) {
                throw new InvalidArgumentException(sprintf(
                    'Manifest instance %s requires static isSingle() to return false',
                    $manifest::class,
                ));
            }

            return $manifest;
        }

        if (!class_exists($manifest) || !is_a($manifest, Manifest::class, true)) {
            throw new InvalidArgumentException(sprintf('Manifest %s must be a declared Manifest subclass', $manifest));
        }
        if (isset($this->single[$manifest])) {
            return $this->single[$manifest];
        }
        $reflection = new ReflectionClass($manifest);
        $constructor = $reflection->getConstructor();
        if ($reflection->isAbstract() || (
            $constructor !== null
            && (!$constructor->isPublic() || $constructor->getNumberOfRequiredParameters() !== 0)
        )) {
            throw new InvalidArgumentException(sprintf('Manifest %s must be instantiable without required constructor arguments', $manifest));
        }

        /** @var Manifest $instance */
        $instance = $reflection->newInstance();

        return $instance;
    }

    private function apply(Manifest $manifest): void
    {
        $transforms = $manifest->getTransforms();
        if (!is_array($transforms) || !array_is_list($transforms)) {
            throw new InvalidArgumentException(sprintf('Manifest %s::getTransforms() must return a list', $manifest::class));
        }

        foreach ($transforms as $mixin) {
            if (!is_string($mixin) || !class_exists($mixin)) {
                if ($manifest->isRequired()) {
                    throw new InvalidArgumentException(sprintf('Manifest %s references missing mixin of type %s', $manifest::class, get_debug_type($mixin)));
                }
                continue;
            }
            $attributes = (new ReflectionClass($mixin))->getAttributes(Mixin::class);
            if ($attributes === []) {
                throw new InvalidArgumentException(sprintf('Manifest transform %s must have at least one #[Mixin] declaration', $mixin));
            }
            $selectors = (new ReflectionClass($mixin))->getAttributes(Applies::class);
            $targets = [];
            foreach ($attributes as $attribute) {
                $targets = [...$targets, ...$attribute->newInstance()->targets];
            }
            foreach ($selectors as $selector) {
                $targets = [...$targets, ...get_declared_classes()];
            }
            $targets = array_values(array_unique($targets));
            foreach ($targets as $target) {
                $candidates = $target === '*' ? get_declared_classes() : [$target];
                foreach ($candidates as $candidate) {
                    if (!class_exists($candidate, false) || !$this->mixinAllows($attributes, $selectors, $candidate)
                        || !$manifest->shouldTransform($candidate, $mixin)
                    ) {
                        continue;
                    }
                    Zend::injectMixinMethods($candidate, $mixin);
                }
                if ($target !== '*' && !class_exists($target, false) && $manifest->isRequired()) {
                    throw new InvalidArgumentException(sprintf('Manifest %s target %s is not declared', $manifest::class, $target));
                }
            }
        }
    }

    /** @param list<\ReflectionAttribute<Mixin>> $mixins
     * @param list<\ReflectionAttribute<Applies>> $selectors
     */
    private function mixinAllows(array $mixins, array $selectors, string $candidate): bool
    {
        foreach ($mixins as $mixin) {
            if ($mixin->newInstance()->allows($candidate)) {
                return true;
            }
        }
        foreach ($selectors as $selector) {
            if ($selector->newInstance()->matches($candidate)) {
                return true;
            }
        }

        return false;
    }
}
