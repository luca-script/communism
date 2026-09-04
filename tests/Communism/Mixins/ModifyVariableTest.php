<?php

declare(strict_types=1);

use Communism\Mixin\At;
use Communism\Mixin\Coerce;
use Communism\Mixin\Mixin;
use Communism\Mixin\ModifyVariable;
use Communism\Internals\Needle\Decompiler;
use Communism\Internals\Needle\Operand;
use Communism\Reflect\ReflectionClass;

#[Mixin(ModifyVariableTarget::class)]
final class ModifyVariableMixin
{
    private function __construct() {}
    #[ModifyVariable('value', new At('STORE'), 'result')]
    public function changeResult(int $result): int
    {
        return $result + 4;
    }
}


final class ModifyVariableTarget
{
    public function value(int $value): int
    {
        $result = $value;

        return $result;
    }
}

it('executes a Mixin-shaped ModifyVariable handler for a named store', function (): void {
    (new ReflectionClass(ModifyVariableTarget::class))->inject(ModifyVariableMixin::class);

    expect((new ModifyVariableTarget())->value(3))->toBe(7);
});

#[Mixin(ModifyVariableUnionTarget::class)]
final class ModifyVariableUnionMixin
{
    private function __construct() {}

    #[ModifyVariable('value', new At('STORE'), 'result')]
    public function changeUnion(int|float $result): int|float
    {
        return $result + 6;
    }
}

final class ModifyVariableUnionTarget
{
    public function value(int|float $value): int|float
    {
        $result = $value;

        return $result;
    }
}

it('matches a ModifyVariable handler using a union local type', function (): void {
    (new ReflectionClass(ModifyVariableUnionTarget::class))->inject(ModifyVariableUnionMixin::class);
    $body = Decompiler::decompile(ModifyVariableUnionTarget::class . '::value');
    $operand = $body->variableOperand('value');
    if (!$operand instanceof Operand) {
        throw new RuntimeException('Union parameter was not present in the decompiled CV table');
    }

    expect((new ModifyVariableUnionTarget())->value(3))->toBe(9)
        ->and($body->variableType($operand))->toBe('int|float');
});

#[Mixin(ModifyVariableInputCoerceTarget::class)]
final class ModifyVariableInputCoerceMixin
{
    private function __construct() {}

    #[ModifyVariable('value', new At('STORE'), 'result')]
    public function changeResult(#[Coerce] float $result): float
    {
        return $result + 0.5;
    }
}

final class ModifyVariableInputCoerceTarget
{
    public function value(int $value): int
    {
        $result = $value;

        return $result;
    }
}

it('coerces a ModifyVariable input before inlining the handler', function (): void {
    (new ReflectionClass(ModifyVariableInputCoerceTarget::class))->inject(ModifyVariableInputCoerceMixin::class);

    expect((new ModifyVariableInputCoerceTarget())->value(3))->toBe(3)
        ->and(array_filter(
            Decompiler::decompile(ModifyVariableInputCoerceTarget::class . '::value')->instructions(),
            static fn($instruction): bool => $instruction->name === 'CAST' && $instruction->extendedValue === 5,
        ))->not->toBeEmpty();
});


#[Mixin(ModifyVariableInputRejectTarget::class)]
final class ModifyVariableInputRejectMixin
{
    private function __construct() {}

    #[ModifyVariable('value', new At('STORE'), 'result')]
    public function rejects(float $result): float
    {
        return $result;
    }
}

final class ModifyVariableInputRejectTarget
{
    public function value(int $value): int
    {
        $result = $value;

        return $result;
    }
}

it('rejects a ModifyVariable input coercion without Coerce', function (): void {
    expect(static fn() => (new ReflectionClass(ModifyVariableInputRejectTarget::class))->inject(ModifyVariableInputRejectMixin::class))
        ->toThrow(InvalidArgumentException::class, 'did not match STORE target');
});

#[Mixin(ModifyVariableUnionRejectTarget::class)]
final class ModifyVariableUnionRejectMixin
{
    private function __construct() {}

    #[ModifyVariable('value', new At('STORE'), 'result')]
    public function rejects(string|bool $result): string|bool
    {
        return $result;
    }
}

final class ModifyVariableUnionRejectTarget
{
    public function value(int|float $value): int|float
    {
        $result = $value;

        return $result;
    }
}

it('rejects a ModifyVariable union type incompatible with the local', function (): void {
    expect(static fn() => (new ReflectionClass(ModifyVariableUnionRejectTarget::class))->inject(ModifyVariableUnionRejectMixin::class))
        ->toThrow(InvalidArgumentException::class, 'did not match STORE target');

    expect((new ModifyVariableUnionRejectTarget())->value(3))->toBe(3);
});

interface ModifyVariableIntersectionLeft {}

interface ModifyVariableIntersectionRight {}

final class ModifyVariableIntersectionValue implements ModifyVariableIntersectionLeft, ModifyVariableIntersectionRight {}

#[Mixin(ModifyVariableIntersectionTarget::class)]
final class ModifyVariableIntersectionMixin
{
    private function __construct() {}

    #[ModifyVariable('value', new At('STORE'), 'result')]
    public function preserveIntersection(ModifyVariableIntersectionLeft&ModifyVariableIntersectionRight $result): ModifyVariableIntersectionLeft&ModifyVariableIntersectionRight
    {
        return $result;
    }
}

final class ModifyVariableIntersectionTarget
{
    public function value(ModifyVariableIntersectionLeft&ModifyVariableIntersectionRight $value): ModifyVariableIntersectionLeft&ModifyVariableIntersectionRight
    {
        $result = $value;

        return $result;
    }
}

it('matches ModifyVariable handlers using intersection local types', function (): void {
    (new ReflectionClass(ModifyVariableIntersectionTarget::class))->inject(ModifyVariableIntersectionMixin::class);
    $value = new ModifyVariableIntersectionValue();

    expect((new ModifyVariableIntersectionTarget())->value($value))->toBe($value);
});

#[Mixin(ModifyVariableCoerceTarget::class)]
final class ModifyVariableCoerceMixin
{
    private function __construct() {}

    #[Coerce]
    #[ModifyVariable('value', new At('STORE'), 'result')]
    public function changeResult(int $result): float
    {
        return $result + 0.5;
    }
}

final class ModifyVariableCoerceTarget
{
    public function value(int $value): int
    {
        $result = $value;

        return $result;
    }
}

it('casts a coerced ModifyVariable result to the local type', function (): void {
    (new ReflectionClass(ModifyVariableCoerceTarget::class))->inject(ModifyVariableCoerceMixin::class);

    expect((new ModifyVariableCoerceTarget())->value(3))->toBe(3)
        ->and(array_filter(
            Decompiler::decompile(ModifyVariableCoerceTarget::class . '::value')->instructions(),
            static fn($instruction): bool => $instruction->name === 'CAST' && $instruction->extendedValue === 4,
        ))->not->toBeEmpty();
});

#[Mixin(ModifyVariableLoadTarget::class)]
final class ModifyVariableLoadMixin
{
    private function __construct() {}
    #[ModifyVariable('value', new At('LOAD'), 'local')]
    public function changeLoaded(int $local): int
    {
        return $local + 5;
    }
}


final class ModifyVariableLoadTarget
{
    public function value(int $value): int
    {
        $local = $value + 1;

        return $local;
    }
}

it('executes a Mixin-shaped ModifyVariable handler on a local load', function (): void {
    (new ReflectionClass(ModifyVariableLoadTarget::class))->inject(ModifyVariableLoadMixin::class);

    expect((new ModifyVariableLoadTarget())->value(3))->toBe(9);
});

#[Mixin(ModifyVariableAnyTarget::class)]
final class ModifyVariableAnyMixin
{
    private function __construct() {}
    #[ModifyVariable('value', new At('STORE'))]
    public function changeEveryStore(int $value): int
    {
        return $value + 1;
    }
}

final class ModifyVariableAnyTarget
{
    public function value(int $value): int
    {
        $first = $value;
        $second = $first;

        return $second;
    }
}


it('executes a Mixin-shaped ModifyVariable handler for every store', function (): void {
    (new ReflectionClass(ModifyVariableAnyTarget::class))->inject(ModifyVariableAnyMixin::class);

    expect((new ModifyVariableAnyTarget())->value(3))->toBe(5);
});

#[Mixin(ModifyVariableOrdinalTarget::class)]
final class ModifyVariableOrdinalMixin
{
    private function __construct() {}
    #[ModifyVariable('value', new At('STORE', ordinal: 1))]
    public function changeSecondStore(int $value): int
    {
        return $value + 10;
    }
}


final class ModifyVariableOrdinalTarget
{
    public function value(int $value): int
    {
        $first = $value;
        $second = $first;

        return $second;
    }
}

it('executes only the selected ModifyVariable store ordinal', function (): void {
    (new ReflectionClass(ModifyVariableOrdinalTarget::class))->inject(ModifyVariableOrdinalMixin::class);

    expect((new ModifyVariableOrdinalTarget())->value(3))->toBe(13);
});

#[Mixin(ModifyVariableCountTarget::class)]
final class ModifyVariableCountMixin
{
    private function __construct() {}
    #[ModifyVariable('value', new At('STORE'), require: 2, expect: 2, allow: 2)]
    public function changeWithCountGuarantee(int $value): int
    {
        return $value + 2;
    }
}

final class ModifyVariableCountTarget
{
    public function value(int $value): int
    {
        $first = $value;
        $second = $first;

        return $second;
    }
}


it('enforces ModifyVariable match-count constraints before rewriting', function (): void {
    (new ReflectionClass(ModifyVariableCountTarget::class))->inject(ModifyVariableCountMixin::class);

    expect((new ModifyVariableCountTarget())->value(3))->toBe(7);
});

#[Mixin(ModifyVariableRequiredMissingTarget::class)]
final class ModifyVariableRequiredMissingMixin
{
    private function __construct() {}
    #[ModifyVariable('value', new At('STORE'), require: 2)]
    public function requiresTwoStores(int $value): int
    {
        return $value + 1;
    }
}

final class ModifyVariableRequiredMissingTarget
{
    public function value(int $value): int
    {
        $only = $value;

        return $only;
    }
}


it('rejects a ModifyVariable require constraint without mutating the target', function (): void {
    expect(function (): void {
        (new ReflectionClass(ModifyVariableRequiredMissingTarget::class))->inject(ModifyVariableRequiredMissingMixin::class);
    })->toThrow(InvalidArgumentException::class, 'requires at least 2');

    expect((new ModifyVariableRequiredMissingTarget())->value(3))->toBe(3);
});

#[Mixin(ModifyVariableMissingTarget::class)]
final class ModifyVariableMissingMixin
{
    private function __construct() {}
    #[ModifyVariable('value', new At('STORE'), 'missing')]
    public function missingVariable(int $value): int
    {
        return $value + 1;
    }
}


final class ModifyVariableMissingTarget
{
    public function value(int $value): int
    {
        return $value;
    }
}

it('rejects a ModifyVariable point that cannot resolve its local', function (): void {
    expect(function (): void {
        (new ReflectionClass(ModifyVariableMissingTarget::class))->inject(ModifyVariableMissingMixin::class);
    })->toThrow(InvalidArgumentException::class, 'did not match');

    expect((new ModifyVariableMissingTarget())->value(3))->toBe(3);
});
