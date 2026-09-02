<?php

declare(strict_types=1);

use Communism\Mixin\At;
use Communism\Mixin\Mixin;
use Communism\Mixin\ModifyConstant;
use Communism\Reflect\ReflectionClass;

#[Mixin(ModifyConstantLongTarget::class)]
final class ModifyConstantLongMixin
{
    private function __construct() {}
    #[ModifyConstant('value', new At('CONSTANT'), type: 'long')]
    public function replaceLong(int $value): int
    {
        return $value + 10;
    }
}


final class ModifyConstantLongTarget
{
    public function value(int $value): int
    {
        return $value + 2;
    }
}

it('accepts long as the PHP integer constant discriminator', function (): void {
    (new ReflectionClass(ModifyConstantLongTarget::class))->inject(ModifyConstantLongMixin::class);

    expect((new ModifyConstantLongTarget())->value(1))->toBe(13);
});

#[Mixin(ModifyConstantDoubleTarget::class)]
final class ModifyConstantDoubleMixin
{
    private function __construct() {}
    #[ModifyConstant('value', new At('CONSTANT'), type: 'double')]
    public function replaceDouble(float $value): float
    {
        return $value + 1.5;
    }
}


final class ModifyConstantDoubleTarget
{
    public function value(float $value): float
    {
        return $value + 2.5;
    }
}

it('accepts double as the PHP floating-point constant discriminator', function (): void {
    (new ReflectionClass(ModifyConstantDoubleTarget::class))->inject(ModifyConstantDoubleMixin::class);

    expect((new ModifyConstantDoubleTarget())->value(1.0))->toBe(5.0);
});

#[Mixin(ModifyConstantClassTarget::class)]
final class ModifyConstantClassMixin
{
    private function __construct() {}
    #[ModifyConstant('value', new At('CONSTANT'), ModifyConstantClassTarget::class, 'class')]
    public function replaceClass(string $value): string
    {
        return 'replaced';
    }
}


final class ModifyConstantClassTarget
{
    public function value(): string
    {
        return self::class;
    }
}

it('matches resolvable class-name constants without treating arbitrary strings as classes', function (): void {
    (new ReflectionClass(ModifyConstantClassTarget::class))->inject(ModifyConstantClassMixin::class);

    expect((new ModifyConstantClassTarget())->value())->toBe('replaced');
});

#[Mixin(ModifyConstantClassMissingTarget::class)]
final class ModifyConstantClassMissingMixin
{
    private function __construct() {}
    #[ModifyConstant('value', new At('CONSTANT'), type: 'class')]
    public function replaceMissingClass(string $value): string
    {
        return 'replaced';
    }
}


final class ModifyConstantClassMissingTarget
{
    public function value(): string
    {
        return 'not-a-class';
    }
}

it('rejects a class discriminator for an arbitrary string before mutation', function (): void {
    expect(static function (): void {
        (new ReflectionClass(ModifyConstantClassMissingTarget::class))->inject(ModifyConstantClassMissingMixin::class);
    })->toThrow(InvalidArgumentException::class, 'did not match');

    expect((new ModifyConstantClassMissingTarget())->value())->toBe('not-a-class');
});

#[Mixin(ModifyConstantNullTarget::class)]
final class ModifyConstantNullMixin
{
    private function __construct() {}

    #[ModifyConstant('value', new At('CONSTANT'), type: 'null', nullValue: true)]
    public function replaceNull(mixed $constant): string
    {
        return 'replaced';
    }
}

final class ModifyConstantNullTarget
{
    public function value(bool $includeNull): string
    {
        return ($includeNull ? null : 'kept') ?? 'fallback';
    }
}

it('matches null literals with the explicit nullValue discriminator', function (): void {
    (new ReflectionClass(ModifyConstantNullTarget::class))->inject(ModifyConstantNullMixin::class);

    expect((new ModifyConstantNullTarget())->value(true))->toBe('replaced');
});

it('rejects an inconsistent nullValue discriminator', function (): void {
    expect(fn(): ModifyConstant => new ModifyConstant('value', new At('CONSTANT'), type: 'string', nullValue: true))
        ->toThrow(InvalidArgumentException::class, 'nullValue requires the null type discriminator');
});
