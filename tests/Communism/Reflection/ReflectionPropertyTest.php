<?php

declare(strict_types=1);

use Communism\Reflect\ReflectionProperty;

final class ReflectionPropertyCoverageTarget
{
    public static string $staticValue = 'initial';
    public string $publicValue = 'initial';
}

final class ReflectionPropertyHookCoverageTarget
{
    public string $hooked {
        set => $value;
    }
}

it('reads and writes static and instance properties through the wrapper', function (): void {
    $instance = new ReflectionPropertyCoverageTarget();
    $instanceProperty = new ReflectionProperty(ReflectionPropertyCoverageTarget::class, 'publicValue');
    $staticProperty = new ReflectionProperty(ReflectionPropertyCoverageTarget::class, 'staticValue');

    expect($instanceProperty->getName())->toBe('publicValue')
        ->and($instanceProperty->getDeclaringClass()->getName())->toBe(ReflectionPropertyCoverageTarget::class)
        ->and($instanceProperty->isReadOnly())->toBeFalse();

    $instanceProperty->setValueOnInstance($instance, 'changed');
    $staticProperty->setStaticValue('also changed');

    expect($instanceProperty->withPublic(static fn(): string => 'public'))->toBe('public')
        ->and($staticProperty->withProtected(static fn(): string => 'protected'))->toBe('protected');

    $staticProperty->setPublicSet();
    $staticProperty->setProtectedSet();
    $staticProperty->setPrivateSet();

    expect($instance->publicValue)->toBe('changed')
        ->and(ReflectionPropertyCoverageTarget::$staticValue)->toBe('also changed');

    expect(static function () use ($staticProperty, $instance): void {
        $staticProperty->setValueOnInstance($instance, 'invalid');
    })
        ->toThrow(LogicException::class);
    expect(static function () use ($instanceProperty): void {
        $instanceProperty->setStaticValue('invalid');
    })
        ->toThrow(LogicException::class);
    expect(static function () use ($instanceProperty): void {
        $instanceProperty->setValueOnInstance(new stdClass(), 'invalid');
    })
        ->toThrow(InvalidArgumentException::class);
});

it('temporarily changes property visibility and restores it after callbacks', function (): void {
    $property = new ReflectionProperty(ReflectionPropertyCoverageTarget::class, 'publicValue');

    $result = $property->withPrivate(function (): string {
        expect((new \ReflectionProperty(ReflectionPropertyCoverageTarget::class, 'publicValue'))->isPrivate())
            ->toBeTrue();

        return 'result';
    });

    expect($result)->toBe('result')
        ->and((new \ReflectionProperty(ReflectionPropertyCoverageTarget::class, 'publicValue'))->isPublic())->toBeTrue();

    expect(fn(): mixed => $property->withProtected(static function (): never {
        throw new RuntimeException('callback failed');
    }))->toThrow(RuntimeException::class);
    expect((new \ReflectionProperty(ReflectionPropertyCoverageTarget::class, 'publicValue'))->isPublic())->toBeTrue();
});

it('sets property flags and restores readonly state', function (): void {
    $property = new ReflectionProperty(ReflectionPropertyCoverageTarget::class, 'publicValue');

    $property->setPrivate();
    expect((new \ReflectionProperty(ReflectionPropertyCoverageTarget::class, 'publicValue'))->isPrivate())->toBeTrue();

    $property->setPrivate(false);
    $property->setPublic();
    $property->setPublic(false);
    $property->setProtected();
    $property->setProtected(false);
    $property->setPublic();
    $property->setReadonly();
    expect($property->isReadOnly())->toBeTrue();

    $property->setReadonly(false);
    expect($property->isReadOnly())->toBeFalse();
});

it('leaves property-set visibility unchanged when no property hook exists', function (): void {
    $property = new ReflectionProperty(ReflectionPropertyCoverageTarget::class, 'publicValue');

    $property->setPublicSet();
    $property->setProtectedSet();
    $property->setPrivateSet();

    expect((new \ReflectionProperty(ReflectionPropertyCoverageTarget::class, 'publicValue'))->isPublic())->toBeTrue();
});

it('changes property-set visibility when a hook exists', function (): void {
    $property = new ReflectionProperty(ReflectionPropertyHookCoverageTarget::class, 'hooked');

    $property->setProtectedSet();
    $property->setPrivateSet();
    $property->setPublicSet(false);
    $property->setPublicSet();

    expect((new \ReflectionProperty(ReflectionPropertyHookCoverageTarget::class, 'hooked'))->hasHooks())
        ->toBeTrue();
});
