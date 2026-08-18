<?php

declare(strict_types=1);

use Communism\Mixin\Final_ as FinalMember;
use Communism\Mixin\Mixin;
use Communism\Mixin\Mutable;
use Communism\Mixin\Overwrite;
use Communism\Mixin\Shadow;

#[Mixin(FinalMethodTarget::class)]
final class FinalMethodMixin
{
    private function __construct() {}
    #[FinalMember]
    public function addedFinal(): string
    {
        return 'added';
    }

    #[FinalMember, Overwrite]
    public function existing(): string
    {
        return 'replacement';
    }
}


/**
 * @method string addedFinal()
 * @method string existing()
 */
class FinalMethodTarget
{
    public function existing(): string
    {
        return 'original';
    }
}

#[Mixin(MutablePropertyTarget::class)]
final class MutablePropertyMixin
{
    private function __construct()
    {
        $this->value = 0;
    }
    #[Shadow, Mutable]
    public readonly int $value;
}


#[Mixin(FinalPropertyTarget::class)]
final class FinalPropertyMixin
{
    private function __construct() {}
    #[Shadow, FinalMember]
    public int $value;
}


final class FinalPropertyTarget
{
    private int $value = 5;

    public function value(): int
    {
        return $this->value;
    }
}

final class MutablePropertyTarget
{
    private readonly int $value;

    public function __construct(int $value)
    {
        $this->value = $value;
    }

    public function update(int $value): void
    {
        (new \ReflectionProperty(self::class, 'value'))->setValue($this, $value);
    }

    public function value(): int
    {
        return $this->value;
    }
}

#[Mixin(InvalidMutableTarget::class)]
final class InvalidMutableMixin
{
    private function __construct() {}
    #[Mutable]
    public function notShadowed(): void {}
}


#[Mixin(ConflictingMutabilityTarget::class)]
final class ConflictingMutabilityMixin
{
    private function __construct() {}
    #[Shadow, FinalMember, Mutable]
    public function target(): void {}
}


final class ConflictingMutabilityTarget
{
    public function target(): void {}
}

final class InvalidMutableTarget {}

it('marks newly injected and overwritten methods final', function (): void {
    (new Communism\Reflect\ReflectionClass(FinalMethodTarget::class))->inject(FinalMethodMixin::class);

    $target = new FinalMethodTarget();
    expect($target->addedFinal())->toBe('added')
        ->and($target->existing())->toBe('replacement')
        ->and((new \ReflectionMethod(FinalMethodTarget::class, 'addedFinal'))->isFinal())->toBeTrue()
        ->and((new \ReflectionMethod(FinalMethodTarget::class, 'existing'))->isFinal())->toBeTrue();
});

it('removes readonly semantics from a mutable shadow before target code runs', function (): void {
    (new Communism\Reflect\ReflectionClass(MutablePropertyTarget::class))->inject(MutablePropertyMixin::class);

    $target = new MutablePropertyTarget(3);
    $target->update(9);

    expect($target->value())->toBe(9)
        ->and((new \ReflectionProperty(MutablePropertyTarget::class, 'value'))->isReadOnly())->toBeFalse();
});

it('marks a shadowed property readonly when it is declared final', function (): void {
    (new Communism\Reflect\ReflectionClass(FinalPropertyTarget::class))->inject(FinalPropertyMixin::class);

    expect((new FinalPropertyTarget())->value())->toBe(5)
        ->and((new \ReflectionProperty(FinalPropertyTarget::class, 'value'))->isReadOnly())->toBeTrue();
});

it('rejects Mutable on a method that is not a shadow', function (): void {
    expect(function (): never {
        (new Communism\Reflect\ReflectionClass(InvalidMutableTarget::class))->inject(InvalidMutableMixin::class);
        throw new \RuntimeException('Mutable injection unexpectedly succeeded');
    })->toThrow(\InvalidArgumentException::class, 'requires #[Shadow]');

    expect(in_array('notShadowed', get_class_methods(InvalidMutableTarget::class), true))->toBeFalse();
});

it('rejects conflicting Final and Mutable declarations before mutation', function (): void {
    expect(function (): never {
        (new Communism\Reflect\ReflectionClass(ConflictingMutabilityTarget::class))->inject(ConflictingMutabilityMixin::class);
        throw new \RuntimeException('Conflicting mutability injection unexpectedly succeeded');
    })->toThrow(\InvalidArgumentException::class, 'cannot combine #[Final] and #[Mutable]');
});
