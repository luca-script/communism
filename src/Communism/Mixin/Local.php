<?php

declare(strict_types=1);

namespace Communism\Mixin;

use Attribute;

/** Forces a callback parameter to capture a named target local variable. */
#[Attribute(Attribute::TARGET_PARAMETER)]
final class Local
{
    public function __construct(public readonly ?string $name = null)
    {
        if ($name === '') {
            throw new \InvalidArgumentException('Local name must not be empty');
        }
    }
}
