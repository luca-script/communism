<?php

declare(strict_types=1);

use Communism\Mixin\At;
use Communism\Mixin\Group;
use Communism\Mixin\Inject;
use Communism\Mixin\Mixin;
use Communism\Mixin\MixinConfiguration;
use Communism\Mixin\ModifyVariable;

it('validates At descriptions and action defaults', function (): void {
    expect((new At('HEAD'))->action())->toBe('before')
        ->and((new At('INVOKE', shift: 'AFTER'))->action())->toBe('after')
        ->and((new At('INVOKE_ASSIGN'))->action())->toBe('after')
        ->and((new At('RETURN'))->action())->toBe('before')
        ->and((new At('OTHER'))->action())->toBe('replace')
        ->and((new At('HEAD', target: 'method', ordinal: 2, opcode: 'add'))->description())
        ->toBe('HEAD target method argument 2 opcode ADD')
        ->and((new At('HEAD', target: new stdClass()))->description())
        ->toBe('HEAD target custom invocation');

    expect(static fn(): At => new At(''))->toThrow(InvalidArgumentException::class)
        ->and(static fn(): At => new At('HEAD', ordinal: -2))->toThrow(InvalidArgumentException::class)
        ->and(static fn(): At => new At('HEAD', by: -1))->toThrow(InvalidArgumentException::class)
        ->and(static fn(): At => new At('HEAD', shift: 'SIDE'))->toThrow(InvalidArgumentException::class)
        ->and(static fn(): At => new At('HEAD', shift: 'BY'))->toThrow(InvalidArgumentException::class)
        ->and(static fn(): At => new At('HEAD', by: 33))->toThrow(InvalidArgumentException::class);
});

it('validates annotation constraints and mixin target matching', function (): void {
    $at = new At('INVOKE');

    expect(static fn(): Group => new Group(''))->toThrow(InvalidArgumentException::class)
        ->and(static fn(): Group => new Group('g', min: -1))->toThrow(InvalidArgumentException::class)
        ->and(static fn(): Group => new Group('g', max: -1))->toThrow(InvalidArgumentException::class)
        ->and(static fn(): Group => new Group('g', min: 2, max: 1))->toThrow(InvalidArgumentException::class)
        ->and(static fn(): Inject => new Inject('', $at))->toThrow(InvalidArgumentException::class)
        ->and(static fn(): Inject => new Inject('m', $at, require: -1))->toThrow(InvalidArgumentException::class)
        ->and(static fn(): Inject => new Inject('m', $at, expect: -1))->toThrow(InvalidArgumentException::class)
        ->and(static fn(): Inject => new Inject('m', $at, allow: -1))->toThrow(InvalidArgumentException::class)
        ->and(static fn(): Inject => new Inject('m', $at, argumentIndex: -1))->toThrow(InvalidArgumentException::class)
        ->and(static fn(): Inject => new Inject('m', $at, argumentIndex: 0))->toThrow(InvalidArgumentException::class)
        ->and(static fn(): Inject => new Inject('m', new At('HEAD'), argumentIndex: 0))->toThrow(InvalidArgumentException::class)
        ->and(static fn(): Inject => new Inject('m', $at, variableIndex: -1))->toThrow(InvalidArgumentException::class)
        ->and(static fn(): Inject => new Inject('m', $at, variableIndex: 0))->toThrow(InvalidArgumentException::class)
        ->and(static fn(): Inject => new Inject('m', $at, constantType: 'invalid'))->toThrow(InvalidArgumentException::class)
        ->and(static fn(): Inject => new Inject('m', $at, mode: 'invalid'))->toThrow(InvalidArgumentException::class)
        ->and(static fn(): ModifyVariable => new ModifyVariable('m', $at, index: -1))->toThrow(InvalidArgumentException::class)
        ->and(static fn(): ModifyVariable => new ModifyVariable('m', $at, require: -1))->toThrow(InvalidArgumentException::class)
        ->and(static fn(): ModifyVariable => new ModifyVariable('m', $at, print: true, require: 1))->toThrow(InvalidArgumentException::class)
        ->and(static fn(): Mixin => new Mixin())->toThrow(InvalidArgumentException::class);

    $mixin = new Mixin('*', AnnotationEdgeTestTarget::class);
    expect($mixin->allows(AnnotationEdgeTestTarget::class))->toBeTrue()
        ->and($mixin->allows(AnnotationEdgeOtherTarget::class))->toBeTrue()
        ->and((new Mixin(AnnotationEdgeTestTarget::class))->allows(AnnotationEdgeOtherTarget::class))->toBeFalse();
});

it('orders declarative configurations deterministically and filters their context', function (): void {
    $late = new MixinConfiguration('LateMixin', [AnnotationEdgeTestTarget::class], priority: 20);
    $early = new MixinConfiguration('EarlyMixin', [AnnotationEdgeTestTarget::class], priority: 10);
    $tie = new MixinConfiguration('TieMixin', [AnnotationEdgeTestTarget::class], priority: 10);

    expect(MixinConfiguration::ordered([$late, $early, $tie]))->toBe([$early, $tie, $late])
        ->and($early->appliesTo(AnnotationEdgeTestTarget::class))->toBeTrue()
        ->and($early->appliesTo(strtolower(AnnotationEdgeTestTarget::class)))->toBeTrue()
        ->and($early->appliesTo(AnnotationEdgeOtherTarget::class))->toBeFalse()
        ->and((new MixinConfiguration('EnvMixin', environment: 'test'))->appliesTo(AnnotationEdgeTestTarget::class, 'test'))->toBeTrue()
        ->and((new MixinConfiguration('EnvMixin', environment: 'test'))->appliesTo(AnnotationEdgeTestTarget::class, 'prod'))->toBeFalse()
        ->and((new MixinConfiguration('OptionalMixin', required: false, compatibilityVersion: '999.0'))->appliesTo(AnnotationEdgeTestTarget::class))->toBeFalse();

    expect(static fn(): MixinConfiguration => new MixinConfiguration('', []))
        ->toThrow(InvalidArgumentException::class, 'requires a mixin')
        ->and(static fn(): array => MixinConfiguration::ordered(['invalid']))
        ->toThrow(InvalidArgumentException::class, 'MixinConfiguration objects');
});

final class AnnotationEdgeTestTarget {}

final class AnnotationEdgeOtherTarget {}
