<?php

declare(strict_types=1);

namespace CompileFixture;

function combine(int $left, int $right): int
{
    return $left + $right;
}

final class FirstTarget
{
    public function greet(string $name): string
    {
        return 'Hello ' . $name;
    }
}

final class SecondTarget
{
    public function subtract(int $left, int $right): int
    {
        return $left - $right;
    }
}

throw new \RuntimeException('Compile-target fixture was executed');
