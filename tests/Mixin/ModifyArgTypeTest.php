<?php

declare(strict_types=1);

use Communism\Mixin\At;
use Communism\Mixin\Coerce;
use Communism\Mixin\Mixin;
use Communism\Mixin\ModifyArg;
use Communism\Reflect\ReflectionClass;

function modifyArgTypedFloat(float $value): float
{
    return $value * 2;
}

#[Mixin(ModifyArgCoerceTarget::class)]
final class ModifyArgCoerceMixin
{
    private function __construct() {}
    #[Coerce]
    #[ModifyArg('value', new At('INVOKE', 'modifyArgTypedFloat'), 0)]
    public function coerceArgument(int $value): int
    {
        return $value + 1;
    }
}


final class ModifyArgCoerceTarget
{
    public function value(float $value): float
    {
        return modifyArgTypedFloat($value);
    }
}

it('allows compatible numeric ModifyArg coercion with #[Coerce]', function (): void {
    (new ReflectionClass(ModifyArgCoerceTarget::class))->inject(ModifyArgCoerceMixin::class);

    expect((new ModifyArgCoerceTarget())->value(2.5))->toBe(7.0);
});

function modifyArgTypedInt(int $value): int
{
    return $value * 2;
}

#[Mixin(ModifyArgParameterMismatchTarget::class)]
final class ModifyArgParameterMismatchMixin
{
    private function __construct() {}
    #[ModifyArg('value', new At('INVOKE', 'modifyArgTypedInt'), 0)]
    public function wrongParameter(string $value): int
    {
        return 1;
    }
}


final class ModifyArgParameterMismatchTarget
{
    public function value(int $value): int
    {
        return modifyArgTypedInt($value);
    }
}

it('rejects an incompatible ModifyArg parameter before mutation', function (): void {
    expect(function (): void {
        (new ReflectionClass(ModifyArgParameterMismatchTarget::class))->inject(ModifyArgParameterMismatchMixin::class);
    })->toThrow(InvalidArgumentException::class, 'expects string');

    expect((new ModifyArgParameterMismatchTarget())->value(4))->toBe(8);
});

#[Mixin(ModifyArgReturnMismatchTarget::class)]
final class ModifyArgReturnMismatchMixin
{
    private function __construct() {}
    #[ModifyArg('value', new At('INVOKE', 'modifyArgTypedInt'), 0)]
    public function wrongReturn(int $value): string
    {
        return (string) $value;
    }
}


final class ModifyArgReturnMismatchTarget
{
    public function value(int $value): int
    {
        return modifyArgTypedInt($value);
    }
}

it('rejects an incompatible ModifyArg return before mutation', function (): void {
    expect(function (): void {
        (new ReflectionClass(ModifyArgReturnMismatchTarget::class))->inject(ModifyArgReturnMismatchMixin::class);
    })->toThrow(InvalidArgumentException::class, 'returns string');

    expect((new ModifyArgReturnMismatchTarget())->value(4))->toBe(8);
});
