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
