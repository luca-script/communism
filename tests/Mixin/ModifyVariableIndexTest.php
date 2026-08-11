<?php

declare(strict_types=1);

use Communism\Mixin\At;
use Communism\Mixin\Mixin;
use Communism\Mixin\ModifyVariable;
use Communism\Reflect\ReflectionClass;

#[Mixin(ModifyVariableIndexedTarget::class)]
final class ModifyVariableIndexedMixin
{
    private function __construct() {}
    #[ModifyVariable('value', new At('STORE'), index: 2)]
    public function changeSecondLocal(int $value): int
    {
        return $value + 10;
    }
}


final class ModifyVariableIndexedTarget
{
    public function value(int $value): int
    {
        $first = $value;
        $second = $first * 2;

        return $second;
    }
}

it('selects a ModifyVariable store by declaration-order local index', function (): void {
    (new ReflectionClass(ModifyVariableIndexedTarget::class))->inject(ModifyVariableIndexedMixin::class);

    expect((new ModifyVariableIndexedTarget())->value(3))->toBe(16);
});

#[Mixin(ModifyVariableFirstIndexedTarget::class)]
final class ModifyVariableFirstIndexedMixin
{
    private function __construct() {}
    #[ModifyVariable('value', new At('STORE'), index: 1)]
    public function changeFirstLocal(int $value): int
    {
        return $value + 10;
    }
}


final class ModifyVariableFirstIndexedTarget
{
    public function value(int $value): int
    {
        $first = $value;
        $second = $first * 2;

        return $second;
    }
}

it('distinguishes the first local from later ModifyVariable stores', function (): void {
    (new ReflectionClass(ModifyVariableFirstIndexedTarget::class))->inject(ModifyVariableFirstIndexedMixin::class);

    expect((new ModifyVariableFirstIndexedTarget())->value(3))->toBe(26);
});

#[Mixin(ModifyVariableOutOfRangeTarget::class)]
final class ModifyVariableOutOfRangeMixin
{
    private function __construct() {}
    #[ModifyVariable('value', new At('STORE'), index: 99)]
    public function changeMissingLocal(int $value): int
    {
        return $value + 10;
    }
}


final class ModifyVariableOutOfRangeTarget
{
    public function value(int $value): int
    {
        $local = $value;

        return $local;
    }
}

it('rejects an out-of-range ModifyVariable index before mutation', function (): void {
    expect(function (): void {
        (new ReflectionClass(ModifyVariableOutOfRangeTarget::class))->inject(ModifyVariableOutOfRangeMixin::class);
    })->toThrow(InvalidArgumentException::class, 'did not match');

    expect((new ModifyVariableOutOfRangeTarget())->value(3))->toBe(3);
});

it('rejects a negative ModifyVariable index at declaration time', function (): void {
    expect(function (): void {
        new ModifyVariable('value', new At('STORE'), index: -1);
    })->toThrow(InvalidArgumentException::class, 'index must be non-negative');
});
