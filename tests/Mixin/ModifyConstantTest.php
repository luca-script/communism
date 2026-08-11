<?php

declare(strict_types=1);

use Communism\Mixin\At;
use Communism\Mixin\Mixin;
use Communism\Mixin\ModifyConstant;
use Communism\Reflect\ReflectionClass;

#[Mixin(ModifyConstantTarget::class)]
final class ModifyConstantMixin
{
    private function __construct() {}
    #[ModifyConstant('value', new At('CONSTANT'), 5)]
    public function changeFive(int $constant): int
    {
        return $constant + 2;
    }
}


final class ModifyConstantTarget
{
    public function value(int $value): int
    {
        return $value + 5;
    }
}

it('executes a Mixin-shaped ModifyConstant handler for an exact literal', function (): void {
    (new ReflectionClass(ModifyConstantTarget::class))->inject(ModifyConstantMixin::class);

    expect((new ModifyConstantTarget())->value(3))->toBe(10);
});

#[Mixin(ModifyConstantAnyTarget::class)]
final class ModifyConstantAnyMixin
{
    private function __construct() {}
    #[ModifyConstant('value', new At('CONSTANT'))]
    public function changeConstants(int $constant): int
    {
        return $constant + 1;
    }
}


final class ModifyConstantAnyTarget
{
    public function value(int $value): int
    {
        return $value + 2 + 3;
    }
}

it('executes a Mixin-shaped ModifyConstant handler for every literal', function (): void {
    (new ReflectionClass(ModifyConstantAnyTarget::class))->inject(ModifyConstantAnyMixin::class);

    expect((new ModifyConstantAnyTarget())->value(1))->toBe(8);
});

#[Mixin(ModifyConstantTypeTarget::class)]
final class ModifyConstantTypeMixin
{
    private function __construct() {}
    #[ModifyConstant('value', new At('CONSTANT'), type: 'string')]
    public function replaceString(string $constant): string
    {
        return 'changed';
    }
}


final class ModifyConstantTypeTarget
{
    public function value(string $prefix): string
    {
        return $prefix . 'needle';
    }
}

it('executes a typed ModifyConstant handler without matching other literals', function (): void {
    (new ReflectionClass(ModifyConstantTypeTarget::class))->inject(ModifyConstantTypeMixin::class);

    expect((new ModifyConstantTypeTarget())->value('prefix-'))->toBe('prefix-changed');
});

#[Mixin(ModifyConstantOrdinalTarget::class)]
final class ModifyConstantOrdinalMixin
{
    private function __construct() {}
    #[ModifyConstant('value', new At('CONSTANT', ordinal: 1))]
    public function changeSecond(int $constant): int
    {
        return $constant + 10;
    }
}


final class ModifyConstantOrdinalTarget
{
    public function value(int $value): int
    {
        return $value + 2 + 3;
    }
}

it('executes only the selected ModifyConstant ordinal', function (): void {
    (new ReflectionClass(ModifyConstantOrdinalTarget::class))->inject(ModifyConstantOrdinalMixin::class);

    expect((new ModifyConstantOrdinalTarget())->value(1))->toBe(16);
});

#[Mixin(ModifyConstantIntTarget::class)]
final class ModifyConstantIntMixin
{
    private function __construct() {}
    #[ModifyConstant('value', new At('CONSTANT'), 2, 'int')]
    public function replaceInt(int $constant): int
    {
        return $constant + 10;
    }
}


final class ModifyConstantIntTarget
{
    public function value(int $value): int
    {
        return $value + 2;
    }
}

it('matches an integer ModifyConstant by type', function (): void {
    (new ReflectionClass(ModifyConstantIntTarget::class))->inject(ModifyConstantIntMixin::class);

    expect((new ModifyConstantIntTarget())->value(1))->toBe(13);
});

it('rejects an unsupported ModifyConstant type discriminator', function (): void {
    expect(fn(): ModifyConstant => new ModifyConstant('value', new At('CONSTANT'), null, 'decimal'))
        ->toThrow(InvalidArgumentException::class, 'Unsupported constant type discriminator');
});
