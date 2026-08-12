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
