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
 * File: MixinConfiguration.php                                               *
 * Consumer: Users                                                            *
 * Purpose: Declarative mixin application configuration.                      *
 *============================================================================*/

declare(strict_types=1);

namespace Communism\Mixin;

use InvalidArgumentException;

use function in_array;
use function strcasecmp;
use function version_compare;

/** Immutable, PHP-native equivalent of a Mixin configuration entry. */
final readonly class MixinConfiguration
{
    /**
     * @param string $mixin
     * @param list<string> $targets
     */
    public function __construct(
        public string $mixin,
        public array $targets = [],
        public int $priority = 1000,
        public bool $required = true,
        public ?string $compatibilityVersion = null,
        public ?string $environment = null,
    ) {
        if ($mixin === '') {
            throw new InvalidArgumentException('A mixin configuration requires a mixin class');
        }
        foreach ($targets as $target) {
            if ($target === '') {
                throw new InvalidArgumentException('Mixin configuration targets must be non-empty class names');
            }
        }
        if ($compatibilityVersion !== null && @version_compare($compatibilityVersion, '0.0.0', '<')) {
            throw new InvalidArgumentException('Mixin compatibility version must be a valid version');
        }
    }

    public function appliesTo(string $target, ?string $environment = null): bool
    {
        if ($this->environment !== null && $this->environment !== $environment) {
            return false;
        }
        if ($this->compatibilityVersion !== null && version_compare(PHP_VERSION, $this->compatibilityVersion, '<')) {
            return false;
        }

        if ($this->targets === []) {
            return true;
        }
        foreach ($this->targets as $candidate) {
            if ($candidate === '*' || strcasecmp($candidate, $target) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<mixed> $configurations
     * @return list<MixinConfiguration>
     */
    public static function ordered(array $configurations): array
    {
        /** @var list<array{0: MixinConfiguration, 1: int}> $indexed */
        $indexed = [];
        foreach ($configurations as $index => $configuration) {
            if (!$configuration instanceof self) {
                throw new InvalidArgumentException('Mixin configurations must contain MixinConfiguration objects');
            }
            $indexed[] = [$configuration, $index];
        }
        usort($indexed, static function (array $left, array $right): int {
            $priority = $left[0]->priority <=> $right[0]->priority;

            return $priority !== 0 ? $priority : $left[1] <=> $right[1];
        });

        return array_map(static fn(array $entry): self => $entry[0], $indexed);
    }
}
