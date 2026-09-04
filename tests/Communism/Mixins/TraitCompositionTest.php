<?php

declare(strict_types=1);

use Communism\Mixin\At;
use Communism\Mixin\Inject;
use Communism\Mixin\Mixin;
use Communism\Mixin\Overwrite;
use Communism\Mixin\Intrinsic;
use Communism\Mixin\Implements_;
use Communism\Mixin\Interface_;
use Communism\Mixin\InterfaceRemap;
use Communism\Mixin\SoftOverride;
use Communism\Reflect\ReflectionClass;
use Communism\Mixin\Shadow;
use Communism\Mixin\Unique;

#[Mixin(PartialInjectionTarget::class, OverrideInjectionTarget::class)]
final class PartialInjectionTrait
{
    private function __construct() {}
    #[Shadow]
    public bool $shouldGive;

    public function allowGiving(): void
    {
        $this->shouldGive = true;
    }

    public function notInjected(): void {}

    #[Overwrite('canGive')]
    public function replacementCanGive(): bool
    {
        return false;
    }
}


/**
 * @method void allowGiving()
 */
final class PartialInjectionTarget
{
    public function __construct(private bool $shouldGive = false) {}

    public function canGive(): bool
    {
        return $this->shouldGive;
    }
}

final class OverrideInjectionTarget
{
    private bool $shouldGive = true;

    public function canGive(): bool
    {
        return $this->shouldGive;
    }
}

it('partially injects selected mixin methods into a class', function (): void {
    (new ReflectionClass(PartialInjectionTarget::class))->inject(PartialInjectionTrait::class, ['allowGiving']);

    $target = new PartialInjectionTarget();
    call_user_func([$target, 'allowGiving']);

    expect($target->canGive())->toBeTrue();
    expect(in_array('notInjected', get_class_methods($target), true))->toBeFalse();
});
it('moves an overridden method to an internal name', function (): void {
    (new ReflectionClass(OverrideInjectionTarget::class))->inject(PartialInjectionTrait::class, ['replacementCanGive']);

    $target = new OverrideInjectionTarget();

    expect($target->canGive())->toBeFalse();
});

#[Mixin(UniqueTarget::class)]
final class UniqueMixin
{
    private function __construct() {}
    #[Unique]
    public function existing(): string
    {
        return 'mixin';
    }
}

final class UniqueTarget
{
    public function existing(): string
    {
        return 'target';
    }
}


it('keeps the target member when a Unique member collides', function (): void {
    (new ReflectionClass(UniqueTarget::class))->inject(UniqueMixin::class);

    $target = new UniqueTarget();
    expect($target->existing())->toBe('target');
    $uniqueMethod = array_values(array_filter(
        get_class_methods($target),
        static fn(string $method): bool => str_starts_with($method, '__unique_'),
    ))[0] ?? null;
    if (!is_string($uniqueMethod)) {
        throw new RuntimeException('The unique method was not injected');
    }
    expect((new ReflectionMethod($target, $uniqueMethod))->invoke($target))->toBe('mixin');
});

#[Mixin(ClassUniqueTarget::class)]
#[Unique]
final class ClassUniqueMixin
{
    private function __construct() {}

    public function existing(): string
    {
        return 'mixin';
    }
}

final class ClassUniqueTarget
{
    public function existing(): string
    {
        return 'target';
    }
}

it('applies Unique to every composed method when declared on the mixin class', function (): void {
    (new ReflectionClass(ClassUniqueTarget::class))->inject(ClassUniqueMixin::class);

    $target = new ClassUniqueTarget();
    expect($target->existing())->toBe('target');
    $uniqueMethods = array_values(array_filter(
        get_class_methods($target),
        static fn(string $method): bool => str_starts_with($method, '__unique_'),
    ));
    expect($uniqueMethods)->toHaveCount(1)
        ->and((new ReflectionMethod($target, $uniqueMethods[0]))->invoke($target))->toBe('mixin');
});

#[Mixin(OverwriteStandardTarget::class)]
final class OverwriteStandardMixin
{
    private function __construct() {}
    #[Overwrite]
    public function value(): int
    {
        return 7;
    }
}

final class OverwriteStandardTarget
{
    public function value(): int
    {
        return 2;
    }
}


it('uses the Mixin-shaped Overwrite annotation without a target alias', function (): void {
    (new ReflectionClass(OverwriteStandardTarget::class))->inject(OverwriteStandardMixin::class);

    expect((new OverwriteStandardTarget())->value())->toBe(7);
});

#[Mixin(OverwriteAliasTarget::class)]
final class OverwriteAliasMixin
{
    private function __construct() {}

    #[Overwrite(aliases: ['renamedValue'])]
    public function value(): int
    {
        return 12;
    }
}

final class OverwriteAliasTarget
{
    public function renamedValue(): int
    {
        return 4;
    }
}

it('uses an Overwrite alias when the primary PHP method name is absent', function (): void {
    (new ReflectionClass(OverwriteAliasTarget::class))->inject(OverwriteAliasMixin::class);

    expect((new OverwriteAliasTarget())->renamedValue())->toBe(12);
});

#[Mixin(OverwriteAliasMissingTarget::class)]
final class OverwriteAliasMissingMixin
{
    private function __construct() {}

    #[Overwrite(aliases: ['stillMissing'])]
    public function value(): int
    {
        return 12;
    }
}

final class OverwriteAliasMissingTarget {}

it('rejects an Overwrite when neither the primary name nor aliases exist', function (): void {
    expect(static function (): void {
        (new ReflectionClass(OverwriteAliasMissingTarget::class))->inject(OverwriteAliasMissingMixin::class);
    })->toThrow(InvalidArgumentException::class, 'has no method value to override');
});

#[Mixin(IntrinsicExistingTarget::class)]
final class IntrinsicExistingMixin
{
    private function __construct() {}

    #[Intrinsic]
    public function value(): int
    {
        return 99;
    }
}

final class IntrinsicExistingTarget
{
    public function value(): int
    {
        return 7;
    }
}

it('does not replace an existing target method with a non-displacing Intrinsic', function (): void {
    (new ReflectionClass(IntrinsicExistingTarget::class))->inject(IntrinsicExistingMixin::class);

    expect((new IntrinsicExistingTarget())->value())->toBe(7);
});

#[Mixin(IntrinsicMissingTarget::class)]
final class IntrinsicMissingMixin
{
    private function __construct() {}

    #[Intrinsic]
    public function value(): int
    {
        return 11;
    }
}

final class IntrinsicMissingTarget {}

it('composes a non-displacing Intrinsic when the target method is absent', function (): void {
    (new ReflectionClass(IntrinsicMissingTarget::class))->inject(IntrinsicMissingMixin::class);

    expect((new \ReflectionMethod(IntrinsicMissingTarget::class, 'value'))->invoke(new IntrinsicMissingTarget()))->toBe(11);
});

#[Mixin(IntrinsicInvalidTarget::class)]
final class IntrinsicInvalidMixin
{
    private function __construct() {}

    #[Intrinsic(displace: true)]
    public function value(): int
    {
        return $this->value() + 10;
    }
}

final class IntrinsicInvalidTarget
{
    public function value(): int
    {
        return 5;
    }
}

it('displaces an existing method and rewrites intrinsic self-calls', function (): void {
    (new ReflectionClass(IntrinsicInvalidTarget::class))->inject(IntrinsicInvalidMixin::class);

    expect((new IntrinsicInvalidTarget())->value())->toBe(15);
});

class IntrinsicInheritedParent
{
    public function value(): int
    {
        return 3;
    }
}

#[Mixin(IntrinsicInheritedTarget::class)]
final class IntrinsicInheritedMixin
{
    private function __construct() {}

    #[Intrinsic(displace: true)]
    public function value(): int
    {
        return 9;
    }
}

final class IntrinsicInheritedTarget extends IntrinsicInheritedParent {}

it('rejects displacing an inherited Intrinsic method before mutating the parent', function (): void {
    expect(static function (): void {
        (new ReflectionClass(IntrinsicInheritedTarget::class))->inject(IntrinsicInheritedMixin::class);
    })->toThrow(InvalidArgumentException::class, 'cannot displace an inherited method');

    expect((new IntrinsicInheritedTarget())->value())->toBe(3)
        ->and((new IntrinsicInheritedParent())->value())->toBe(3);
});

class SoftOverrideParent
{
    public function value(): int
    {
        return 2;
    }
}

#[Mixin(SoftOverrideTarget::class)]
final class SoftOverrideMixin
{
    private function __construct() {}

    #[SoftOverride]
    public function value(): int
    {
        return 8;
    }
}

final class SoftOverrideTarget extends SoftOverrideParent {}

it('shadows an inherited method without mutating the parent class', function (): void {
    (new ReflectionClass(SoftOverrideTarget::class))->inject(SoftOverrideMixin::class);

    expect((new SoftOverrideTarget())->value())->toBe(8)
        ->and((new SoftOverrideParent())->value())->toBe(2);
});

#[Mixin(SoftOverrideInvalidTarget::class)]
final class SoftOverrideInvalidMixin
{
    private function __construct() {}

    #[SoftOverride]
    public function value(): int
    {
        return 8;
    }
}

final class SoftOverrideInvalidTarget
{
    public function value(): int
    {
        return 2;
    }
}

it('rejects SoftOverride on a directly declared method before mutation', function (): void {
    expect(static function (): void {
        (new ReflectionClass(SoftOverrideInvalidTarget::class))->inject(SoftOverrideInvalidMixin::class);
    })->toThrow(InvalidArgumentException::class, 'must target an inherited method');

    expect((new SoftOverrideInvalidTarget())->value())->toBe(2);
});

interface ComposedContract
{
    public function describe(): string;
}

#[Mixin(ComposedInterfaceTarget::class)]
#[Implements_(new Interface_(ComposedContract::class, 'contract_'))]
final class ComposedInterfaceMixin
{
    private function __construct() {}

    public function contract_describe(): string
    {
        return 'default';
    }
}

final class ComposedInterfaceTarget {}

it('composes prefixed interface default methods under their contract names', function (): void {
    (new ReflectionClass(ComposedInterfaceTarget::class))->inject(ComposedInterfaceMixin::class);

    expect((new \ReflectionMethod(ComposedInterfaceTarget::class, 'describe'))->invoke(new ComposedInterfaceTarget()))
        ->toBe('default')
        ->and(in_array('describe', get_class_methods(ComposedInterfaceTarget::class), true))->toBeTrue()
        ->and((static function (): bool {
            $interfaces = class_implements(ComposedInterfaceTarget::class, false);
            return $interfaces !== false && in_array(ComposedContract::class, $interfaces, true);
        })())->toBeTrue();
});

interface MissingComposedContract
{
    public function missing(): void;
}

#[Mixin(MissingComposedTarget::class)]
#[Implements_(new Interface_(MissingComposedContract::class, 'contract_'))]
final class MissingComposedInterfaceMixin
{
    private function __construct() {}
}

final class MissingComposedTarget {}

it('rejects interface composition when a prefixed method is missing', function (): void {
    expect(static function (): void {
        (new ReflectionClass(MissingComposedTarget::class))->inject(MissingComposedInterfaceMixin::class);
    })->toThrow(InvalidArgumentException::class, 'is missing contract_missing');
});

#[Mixin(UnprefixedInterfaceTarget::class)]
#[Implements_(new Interface_(ComposedContract::class, 'contract_', remap: InterfaceRemap::ALL))]
final class UnprefixedInterfaceMixin
{
    private function __construct() {}

    public function describe(): string
    {
        return 'unprefixed';
    }
}

final class UnprefixedInterfaceTarget {}

it('uses an unprefixed interface method when InterfaceRemap::ALL allows it', function (): void {
    (new ReflectionClass(UnprefixedInterfaceTarget::class))->inject(UnprefixedInterfaceMixin::class);

    expect((new \ReflectionMethod(UnprefixedInterfaceTarget::class, 'describe'))->invoke(new UnprefixedInterfaceTarget()))
        ->toBe('unprefixed')
        ->and((static function (): bool {
            $interfaces = class_implements(UnprefixedInterfaceTarget::class, false);
            return $interfaces !== false && in_array(ComposedContract::class, $interfaces, true);
        })())->toBeTrue();
});

describe('Unique', function (): void {
    covers([Unique::class, ...COMMUNISM_INJECTOR_COVERAGE_CLASSES]);

    it('defaults Unique to non-silent and accepts silent mode', function (): void {

        expect((new Unique())->silent)->toBeFalse()
            ->and((new Unique(true))->silent)->toBeTrue();
    });
});

#[Mixin(StrictInterfaceRemapTarget::class)]
#[Implements_(new Interface_(ComposedContract::class, 'contract_', remap: InterfaceRemap::ONLY_PREFIXED))]
final class StrictInterfaceRemapMixin
{
    private function __construct() {}

    public function describe(): string
    {
        return 'unprefixed';
    }
}

final class StrictInterfaceRemapTarget {}

it('rejects an unprefixed interface method when remapping is restricted', function (): void {
    expect(static function (): void {
        (new ReflectionClass(StrictInterfaceRemapTarget::class))->inject(StrictInterfaceRemapMixin::class);
    })->toThrow(InvalidArgumentException::class, 'is missing contract_describe');
});

describe('Interface_', function (): void {
    covers([Interface_::class, ...COMMUNISM_INJECTOR_COVERAGE_CLASSES]);

    it('rejects undeclared interfaces and invalid interface prefixes', function (): void {

        expect(static fn() => new Interface_('MissingInterfaceForCoverage', 'prefix_'))
            ->toThrow(InvalidArgumentException::class, 'declared interface')
            ->and(static fn() => new Interface_(ComposedContract::class, ''))
            ->toThrow(InvalidArgumentException::class, 'identifier prefix')
            ->and(static fn() => new Interface_(ComposedContract::class, 'not-valid-'))
            ->toThrow(InvalidArgumentException::class, 'identifier prefix');
    });
});
