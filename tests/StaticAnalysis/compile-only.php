<?php

declare(strict_types=1);

final class CompileOnlyTarget
{
    public function run(string $value): string
    {
        return strtoupper($value);
    }
}

throw new RuntimeException('Compile-only fixture was executed');
