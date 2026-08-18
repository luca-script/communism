<?php

declare(strict_types=1);

use Communism\Mixin\At;
use Communism\Mixin\Inject;
use Communism\Mixin\Mixin;
use Communism\Reflect\ReflectionClass;

#[Mixin(AtomicInjectionTarget::class)]
final class AtomicInjectionTrait
{
    private function __construct() {}
    #[Inject('first', new At('RETURN'))]
    public function firstInjector(): void {}

    #[Inject('second', new At('INVOKE', 'missingAtomicCall'))]
    public function secondInjector(): void {}
}


final class AtomicInjectionTarget
{
    public function first(): int
    {
        return 1;
    }

    public function second(): void {}
}

it('preflights every selected injection before changing the target', function (): void {
    expect(function (): void {
        (new ReflectionClass(AtomicInjectionTarget::class))->inject(AtomicInjectionTrait::class);
    })
        ->toThrow(InvalidArgumentException::class, 'Injection point did not match');

    expect(in_array('firstInjector', get_class_methods(AtomicInjectionTarget::class), true))->toBeFalse();
    expect(in_array('secondInjector', get_class_methods(AtomicInjectionTarget::class), true))->toBeFalse();
});
