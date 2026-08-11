<?php

declare(strict_types=1);

use Communism\Mixin\At;
use Communism\Mixin\Mixin;
use Communism\Mixin\ModifyVariable;
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
