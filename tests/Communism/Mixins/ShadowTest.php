<?php

declare(strict_types=1);

use Communism\Mixin\Mixin;
use Communism\Reflect\ReflectionClass;
use Communism\Mixin\Shadow;

#[Mixin(MethodShadowTarget::class)]
final class MethodShadowMixin
{
    private function __construct() {}
    #[Shadow]
    public function calculate(int $value): int
    {
        return -1;
    }

    public function exposeCalculation(int $value): int
    {
        return $this->calculate($value);
    }
}


/**
 * @method int exposeCalculation(int $value)
 */
final class MethodShadowTarget
{
    public function calculate(int $value): int
    {
        return $value * 2;
    }
}

it('validates method shadows and omits them from composition', function (): void {
    (new ReflectionClass(MethodShadowTarget::class))->inject(MethodShadowMixin::class);

    $target = new MethodShadowTarget();
    expect($target->calculate(3))->toBe(6)
        ->and($target->exposeCalculation(3))->toBe(6);
});

#[Mixin(AliasedMethodShadowTarget::class)]
final class AliasedMethodShadowMixin
{
    private function __construct() {}
    #[Shadow('calculate')]
    public function shadowCalculate(int $value): int
    {
        return -1;
    }
}


final class AliasedMethodShadowTarget
{
    public function calculate(int $value): int
    {
        return $value + 4;
    }
}

it('accepts a method shadow with an explicit target alias', function (): void {
    (new ReflectionClass(AliasedMethodShadowTarget::class))->inject(AliasedMethodShadowMixin::class);

    expect((new AliasedMethodShadowTarget())->calculate(3))->toBe(7)
        ->and(in_array('shadowCalculate', get_class_methods(AliasedMethodShadowTarget::class), true))->toBeFalse();
});

#[Mixin(PrefixedMethodShadowTarget::class)]
final class PrefixedMethodShadowMixin
{
    private function __construct() {}

    #[Shadow(prefix: 'shadow')]
    public function shadowcalculate(int $value): int
    {
        return -1;
    }
}

final class PrefixedMethodShadowTarget
{
    public function calculate(int $value): int
    {
        return $value * 3;
    }
}

it('resolves a Shadow method target by removing its prefix', function (): void {
    (new ReflectionClass(PrefixedMethodShadowTarget::class))->inject(PrefixedMethodShadowMixin::class);

    expect((new PrefixedMethodShadowTarget())->calculate(3))->toBe(9)
        ->and(in_array('shadowcalculate', get_class_methods(PrefixedMethodShadowTarget::class), true))->toBeFalse();
});

#[Mixin(RenamedMethodShadowTarget::class)]
final class RenamedMethodShadowMixin
{
    private function __construct() {}

    #[Shadow(aliases: ['renamedCalculate'])]
    public function calculate(): int
    {
        return 0;
    }
}

final class RenamedMethodShadowTarget
{
    public function renamedCalculate(int $value): int
    {
        return $value + 4;
    }
}

it('resolves a Shadow method through an ordered alias', function (): void {
    (new ReflectionClass(RenamedMethodShadowTarget::class))->inject(RenamedMethodShadowMixin::class);

    expect(in_array('calculate', get_class_methods(RenamedMethodShadowTarget::class), true))->toBeFalse();
});

#[Mixin(RenamedPropertyShadowTarget::class)]
final class RenamedPropertyShadowMixin
{
    private function __construct() {}

    #[Shadow(aliases: ['renamedValue'])]
    private string $value;

    public function readValue(): string
    {
        return $this->value;
    }

    public function writeValue(string $value): void
    {
        $this->value = $value;
    }
}

final class RenamedPropertyShadowTarget
{
    private string $renamedValue = 'aliased';

    public function currentValue(): string
    {
        return $this->renamedValue;
    }
}

it('rewrites composed method access for an aliased Shadow property', function (): void {
    (new ReflectionClass(RenamedPropertyShadowTarget::class))->inject(RenamedPropertyShadowMixin::class);

    expect((new \ReflectionMethod(RenamedPropertyShadowTarget::class, 'readValue'))->invoke(new RenamedPropertyShadowTarget()))
        ->toBe('aliased');
});

#[Mixin(MismatchedPrefixShadowTarget::class)]
final class MismatchedPrefixShadowMixin
{
    private function __construct() {}

    #[Shadow(prefix: 'shadow')]
    public function unrelated(int $value): int
    {
        return $value;
    }
}

final class MismatchedPrefixShadowTarget
{
    public function calculate(int $value): int
    {
        return $value;
    }
}

it('rejects a Shadow method whose name cannot be resolved by its prefix', function (): void {
    expect(static function (): void {
        (new ReflectionClass(MismatchedPrefixShadowTarget::class))->inject(MismatchedPrefixShadowMixin::class);
    })->toThrow(InvalidArgumentException::class, 'does not start with prefix');
});

#[Mixin(MissingMethodShadowTarget::class)]
final class MissingMethodShadowMixin
{
    private function __construct() {}
    #[Shadow]
    public function missingMethod(): void {}
}


final class MissingMethodShadowTarget {}

it('rejects a method shadow whose target does not exist', function (): void {
    expect(function (): void {
        (new ReflectionClass(MissingMethodShadowTarget::class))->inject(MissingMethodShadowMixin::class);
    })->toThrow(InvalidArgumentException::class, 'has no method missingMethod shadowed by');
});
