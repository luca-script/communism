<?php

declare(strict_types=1);

use Communism\Mixin\At;
use Communism\Mixin\Mixin;
use Communism\Mixin\ModifyArg;
use Communism\Reflect\ReflectionClass;

function modifyArgTarget(int $value): int
{
    return $value * 2;
}

#[Mixin(ModifyArgTarget::class)]
final class ModifyArgMixin
{
    private function __construct() {}
    #[ModifyArg('value', new At('INVOKE', 'modifyArgTarget'), 0)]
    public function changeArgument(int $value): int
    {
        return $value + 3;
    }
}


final class ModifyArgTarget
{
    public function value(int $value): int
    {
        return modifyArgTarget($value);
    }
}

it('executes a Mixin-shaped ModifyArg handler at an invocation', function (): void {
    (new ReflectionClass(ModifyArgTarget::class))->inject(ModifyArgMixin::class);

    expect((new ModifyArgTarget())->value(4))->toBe(14);
});

function modifyArgPair(int $first, int $second): int
{
    return $first * 10 + $second;
}

#[Mixin(ModifyArgPairTarget::class)]
final class ModifyArgPairMixin
{
    private function __construct() {}
    #[ModifyArg('value', new At('INVOKE', 'modifyArgPair'), 1)]
    public function changePairSecond(int $value): int
    {
        return $value + 10;
    }
}


final class ModifyArgPairTarget
{
    public function value(int $first, int $second): int
    {
        return modifyArgPair($first, $second);
    }
}

it('changes only the selected ModifyArg ordinal', function (): void {
    (new ReflectionClass(ModifyArgPairTarget::class))->inject(ModifyArgPairMixin::class);

    expect((new ModifyArgPairTarget())->value(2, 3))->toBe(33);
});

#[Mixin(InvalidModifyArgTarget::class)]
final class InvalidModifyArgMixin
{
    private function __construct() {}
    #[ModifyArg('value', new At('INVOKE', 'modifyArgTarget'), 1)]
    public function missingArgument(int $value): int
    {
        return $value;
    }
}

final class InvalidModifyArgTarget
{
    public function value(int $value): int
    {
        return modifyArgTarget($value);
    }
}


it('rejects ModifyArg indexes that the invocation does not provide', function (): void {
    expect(function (): void {
        (new ReflectionClass(InvalidModifyArgTarget::class))->inject(InvalidModifyArgMixin::class);
    })->toThrow(InvalidArgumentException::class);

    expect((new InvalidModifyArgTarget())->value(4))->toBe(8);
});
