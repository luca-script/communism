<?php

declare(strict_types=1);

use Communism\Mixin\Mixin;
use Communism\Reflect\ReflectionClass;

#[Mixin(MixinClassSurfaceTarget::class)]
final class MixinClassSurface
{
    private function __construct() {}

    public function added(): string
    {
        return 'mixin';
    }
}

/** @method string added() */
final class MixinClassSurfaceTarget {}

it('injects methods from a final non-instantiable mixin class', function (): void {
    (new ReflectionClass(MixinClassSurfaceTarget::class))->inject(MixinClassSurface::class);

    expect((new MixinClassSurfaceTarget())->added())->toBe('mixin');
});

it('makes mixin construction impossible to callers', function (): void {
    $reflection = new \ReflectionClass(MixinClassSurface::class);

    expect(function (): void {
        (new \ReflectionClass(MixinClassSurface::class))->newInstance();
    })->toThrow(ReflectionException::class);

    expect($reflection->getConstructor()?->isPrivate())->toBeTrue();
});

#[Mixin(NonFinalMixinSurfaceTarget::class)]
class NonFinalMixinSurface
{
    private function __construct() {}
}

final class NonFinalMixinSurfaceTarget {}

it('rejects a non-final mixin class before mutation', function (): void {
    expect(function (): void {
        (new ReflectionClass(NonFinalMixinSurfaceTarget::class))->inject(NonFinalMixinSurface::class);
    })->toThrow(InvalidArgumentException::class, 'final mixin class');
});

#[Mixin(PublicConstructorMixinSurfaceTarget::class)]
final class PublicConstructorMixinSurface
{
    public function __construct() {}
}

final class PublicConstructorMixinSurfaceTarget {}

it('rejects a mixin with a public constructor before mutation', function (): void {
    expect(function (): void {
        (new ReflectionClass(PublicConstructorMixinSurfaceTarget::class))->inject(PublicConstructorMixinSurface::class);
    })->toThrow(InvalidArgumentException::class, 'private zero-argument constructor');
});

trait LegacyTraitMixinSurface
{
    public function legacy(): void {}
}

final class LegacyTraitMixinSurfaceTarget
{
    use LegacyTraitMixinSurface;
}

it('rejects legacy trait mixins', function (): void {
    expect(function (): void {
        (new ReflectionClass(LegacyTraitMixinSurfaceTarget::class))->inject(LegacyTraitMixinSurface::class);
    })->toThrow(InvalidArgumentException::class, 'final mixin class');
});
