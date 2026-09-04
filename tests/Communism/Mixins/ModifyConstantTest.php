<?php

declare(strict_types=1);

use Communism\Mixin\At;
use Communism\Mixin\Coerce;
use Communism\Mixin\Mixin;
use Communism\Mixin\ModifyConstant;
use Communism\Internals\Needle\Decompiler;
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

#[Mixin(ModifyConstantCoerceTarget::class)]
final class ModifyConstantCoerceMixin
{
    private function __construct() {}

    #[Coerce]
    #[ModifyConstant('value', new At('CONSTANT'), 5)]
    public function replaceConstant(int $constant): float
    {
        return $constant + 0.5;
    }
}

final class ModifyConstantCoerceTarget
{
    public function value(int $value): int
    {
        return $value + 5;
    }
}

it('casts a coerced ModifyConstant result to the literal type', function (): void {
    (new ReflectionClass(ModifyConstantCoerceTarget::class))->inject(ModifyConstantCoerceMixin::class);

    expect((new ModifyConstantCoerceTarget())->value(3))->toBe(10)
        ->and(array_filter(
            Decompiler::decompile(ModifyConstantCoerceTarget::class . '::value')->instructions(),
            static fn($instruction): bool => $instruction->name === 'CAST' && $instruction->extendedValue === 4,
        ))->not->toBeEmpty();
});

#[Mixin(ModifyConstantParameterCoerceTarget::class)]
final class ModifyConstantParameterCoerceMixin
{
    private function __construct() {}

    #[ModifyConstant('value', new At('CONSTANT'), 5)]
    public function replaceConstant(#[Coerce] float $constant): float
    {
        return $constant + 0.5;
    }
}

final class ModifyConstantParameterCoerceTarget
{
    public function value(int $value): int
    {
        return $value + 5;
    }
}

it('coerces a ModifyConstant parameter and replacement result', function (): void {
    (new ReflectionClass(ModifyConstantParameterCoerceTarget::class))->inject(ModifyConstantParameterCoerceMixin::class);
    $casts = array_filter(
        Decompiler::decompile(ModifyConstantParameterCoerceTarget::class . '::value')->instructions(),
        static fn($instruction): bool => $instruction->name === 'CAST',
    );

    expect((new ModifyConstantParameterCoerceTarget())->value(3))->toBe(8)
        ->and(array_filter($casts, static fn($instruction): bool => $instruction->extendedValue === 5))->not->toBeEmpty()
        ->and(array_filter($casts, static fn($instruction): bool => $instruction->extendedValue === 4))->not->toBeEmpty();
});

#[Mixin(ModifyConstantRejectParameterTarget::class)]
final class ModifyConstantRejectParameterMixin
{
    private function __construct() {}

    #[ModifyConstant('value', new At('CONSTANT'), 5)]
    public function rejects(string $constant): string
    {
        return $constant;
    }
}

final class ModifyConstantRejectParameterTarget
{
    public function value(int $value): int
    {
        return $value + 5;
    }
}

it('rejects an incompatible ModifyConstant parameter before mutation', function (): void {
    expect(static fn() => (new ReflectionClass(ModifyConstantRejectParameterTarget::class))->inject(ModifyConstantRejectParameterMixin::class))
        ->toThrow(InvalidArgumentException::class, 'expects string, but the target literal provides int');

    expect((new ModifyConstantRejectParameterTarget())->value(3))->toBe(8);
});

#[Mixin(ModifyConstantStringParameterTarget::class)]
final class ModifyConstantStringParameterMixin
{
    private function __construct() {}

    #[ModifyConstant('value', new At('CONSTANT'), 5)]
    public function replaceConstant(#[Coerce] string $constant): int
    {
        return (int) $constant + 1;
    }
}

final class ModifyConstantStringParameterTarget
{
    public function value(int $value): int
    {
        return $value + 5;
    }
}

it('casts a scalar ModifyConstant input to a string when coerced', function (): void {
    (new ReflectionClass(ModifyConstantStringParameterTarget::class))->inject(ModifyConstantStringParameterMixin::class);

    expect((new ModifyConstantStringParameterTarget())->value(3))->toBe(9)
        ->and(array_filter(
            Decompiler::decompile(ModifyConstantStringParameterTarget::class . '::value')->instructions(),
            static fn($instruction): bool => $instruction->name === 'CAST' && $instruction->extendedValue === 6,
        ))->not->toBeEmpty();
});

#[Mixin(ModifyConstantBoolParameterTarget::class)]
final class ModifyConstantBoolParameterMixin
{
    private function __construct() {}

    #[ModifyConstant('value', new At('CONSTANT'), 5)]
    public function replaceConstant(#[Coerce] bool $constant): int
    {
        return $constant ? 6 : 0;
    }
}

final class ModifyConstantBoolParameterTarget
{
    public function value(int $value): int
    {
        return $value + 5;
    }
}

it('casts a scalar ModifyConstant input to bool when coerced', function (): void {
    (new ReflectionClass(ModifyConstantBoolParameterTarget::class))->inject(ModifyConstantBoolParameterMixin::class);

    expect((new ModifyConstantBoolParameterTarget())->value(3))->toBe(9)
        ->and(array_filter(
            Decompiler::decompile(ModifyConstantBoolParameterTarget::class . '::value')->instructions(),
            static fn($instruction): bool => $instruction->name === 'CAST' && $instruction->extendedValue === 3,
        ))->not->toBeEmpty();
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
