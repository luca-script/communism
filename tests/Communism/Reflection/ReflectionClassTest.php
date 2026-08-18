<?php

declare(strict_types=1);

use Communism\Reflect\ReflectionClass;

final class ReflectionClassCoverageTarget
{
    public string $value = 'initial';

    public function method(): string
    {
        return $this->value;
    }
}

class ReflectionClassMutableCoverageTarget
{
    public string $value;
}

class ReflectionClassTraitKindTarget {}
class ReflectionClassEnumKindTarget {}
class ReflectionClassInterfaceKindTarget {}

trait ReflectionClassPropertyTrait
{
    public string $traitValue;
}

class ReflectionClassTraitHost
{
    use ReflectionClassPropertyTrait;
}

it('exposes class metadata and wrapped members', function (): void {
    $reflection = new ReflectionClass(ReflectionClassCoverageTarget::class);

    expect($reflection->getName())->toBe(ReflectionClassCoverageTarget::class)
        ->and($reflection->isTrait())->toBeFalse()
        ->and($reflection->isInterface())->toBeFalse()
        ->and($reflection->isEnum())->toBeFalse()
        ->and($reflection->isReadOnly())->toBeFalse()
        ->and($reflection->isFinal())->toBeTrue()
        ->and($reflection->isAbstract())->toBeFalse()
        ->and($reflection->getProperty('value')->getName())->toBe('value')
        ->and($reflection->getMethod('method')->getName())->toBe('method');

    expect(array_map(static fn($property): string => $property->getName(), $reflection->getProperties()))
        ->toContain('value');
    expect(array_map(static fn($method): string => $method->getName(), $reflection->getMethods()))
        ->toContain('method');
});

it('temporarily removes finality and restores it after the callback', function (): void {
    $reflection = new ReflectionClass(ReflectionClassCoverageTarget::class);

    expect($reflection->withExtensible(function (): string {
        expect((new \ReflectionClass(ReflectionClassCoverageTarget::class))->isFinal())->toBeFalse();

        return 'extensible';
    }))->toBe('extensible');
    expect((new \ReflectionClass(ReflectionClassCoverageTarget::class))->isFinal())->toBeTrue();

    expect(fn(): mixed => $reflection->withExtensible(static function (): never {
        throw new RuntimeException('callback failed');
    }))->toThrow(RuntimeException::class);
    expect((new \ReflectionClass(ReflectionClassCoverageTarget::class))->isFinal())->toBeTrue();
});

it('toggles the final class flag explicitly', function (): void {
    $reflection = new ReflectionClass(ReflectionClassCoverageTarget::class);

    $reflection->setFinal(false);
    expect((new \ReflectionClass(ReflectionClassCoverageTarget::class))->isFinal())->toBeFalse();

    $reflection->setFinal();
    expect((new \ReflectionClass(ReflectionClassCoverageTarget::class))->isFinal())->toBeTrue();
});

it('exposes and toggles the remaining class metadata flags', function (): void {
    $reflection = new ReflectionClass(ReflectionClassMutableCoverageTarget::class);

    $reflection->setAbstract(true);
    expect($reflection->isAbstract())->toBeTrue();
    $reflection->setAbstract(false);

    $reflection->setReadonly(false);
    $reflection->setTrait(false);
    $reflection->setEnum(false);
    $reflection->setInterface(false);
    $reflection->setAnonymous(false);

    (new ReflectionClass(ReflectionClassTraitKindTarget::class))->setTrait(true);
    expect(static function (): mixed {
        (new ReflectionClass(ReflectionClassEnumKindTarget::class))->setEnum(true);
        return null;
    })
        ->toThrow(InvalidArgumentException::class);
    (new ReflectionClass(ReflectionClassInterfaceKindTarget::class))->setInterface(true);

    expect($reflection->isTrait())->toBeFalse()
        ->and($reflection->isEnum())->toBeFalse()
        ->and($reflection->isInterface())->toBeFalse()
        ->and($reflection->isReadOnly())->toBeFalse();
});

it('toggles readonly properties on classes and traits', function (): void {
    $class = new ReflectionClass(ReflectionClassMutableCoverageTarget::class);
    $class->propertiesReadonly(true);
    expect($class->getProperty('value')->isReadOnly())->toBeTrue();
    $class->propertiesReadonly(false);
    expect($class->getProperty('value')->isReadOnly())->toBeFalse();

    $trait = new ReflectionClass(ReflectionClassPropertyTrait::class);
    $trait->propertiesReadonly(true);
    expect((new ReflectionClass(ReflectionClassTraitHost::class))->getProperty('traitValue')->isReadOnly())->toBeTrue();
    $trait->propertiesReadonly(false);
    expect((new ReflectionClass(ReflectionClassTraitHost::class))->getProperty('traitValue')->isReadOnly())->toBeFalse();

    $propertyReadonly = new ReflectionMethod(ReflectionClass::class, 'propertyReadonlyOnClass');
    $propertyReadonly->setAccessible(true);
    expect($propertyReadonly->invoke($class, ReflectionClassMutableCoverageTarget::class, 'missing', true))
        ->toBeNull();
});
