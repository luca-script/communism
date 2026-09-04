<?php

declare(strict_types=1);

namespace Communism\Mixin;

use Attribute;
use InvalidArgumentException;

/** Selects a callback argument from a target parameter by name or ordinal. */
#[Attribute(Attribute::TARGET_PARAMETER)]
final class Parameter
{
    public function __construct(
        public readonly ?int $ordinal = null,
        public readonly ?string $name = null,
    ) {
        if ($ordinal !== null && $ordinal < 0) {
            throw new InvalidArgumentException('Parameter ordinal must be non-negative');
        }
        if ($name === '') {
            throw new InvalidArgumentException('Parameter name must not be empty');
        }
        if ($ordinal !== null && $name !== null) {
            throw new InvalidArgumentException('Parameter may specify an ordinal or name, not both');
        }
    }
}
