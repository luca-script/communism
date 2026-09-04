<?php

declare(strict_types=1);

use Communism\Mixin\At;
use Communism\Mixin\Inject;
use Communism\Mixin\Mixin;
use Communism\Mixin\TargetMethods;
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

it('rejects malformed multi-target Inject declarations', function (): void {
    expect(static fn() => new Inject([], new At('HEAD')))
        ->toThrow(InvalidArgumentException::class, 'target method')
        ->and(static fn() => new Inject(['first' => 'value'], new At('HEAD')))
        ->toThrow(InvalidArgumentException::class, 'target method')
        ->and(static fn() => new Inject(['first', ''], new At('HEAD')))
        ->toThrow(InvalidArgumentException::class, 'target method');
});

describe('TargetMethods', function (): void {
    covers(TargetMethods::class);

    it('normalizes valid injection target method lists', function (): void {

    expect(TargetMethods::normalize('run'))->toBe(['run'])
        ->and(TargetMethods::normalize(['first', 'second']))->toBe(['first', 'second'])
        ->and(TargetMethods::normalize(['first', 'second']))->toBe(['first', 'second']);

    expect(static fn() => TargetMethods::normalize([]))->toThrow(InvalidArgumentException::class)
        ->and(static fn() => TargetMethods::normalize(['first' => 'run']))->toThrow(InvalidArgumentException::class)
        ->and(static fn() => TargetMethods::normalize(['']))->toThrow(InvalidArgumentException::class);
    });
});

describe('Inject', function (): void {
    covers(Inject::class);

    it('normalizes Inject targets and rejects invalid target values', function (): void {

    $inject = new Inject(['first', 'second'], new At('HEAD'));
    expect($inject->method)->toBe('first')->and($inject->targets)->toBe(['first', 'second']);
    expect(static fn() => new Inject(['first', 42], new At('HEAD')))
        ->toThrow(InvalidArgumentException::class);
    });
});
