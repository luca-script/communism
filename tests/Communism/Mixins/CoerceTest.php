<?php

declare(strict_types=1);

use Communism\Mixin\At;
use Communism\Mixin\CallbackInfo;
use Communism\Mixin\Coerce;
use Communism\Mixin\Inject;
use Communism\Mixin\Mixin;
use Communism\Mixin\Redirect;
use Communism\Reflect\ReflectionClass;

#[Mixin(CoerceTarget::class)]
final class CoerceMixin
{
    private function __construct() {}
    #[Inject('value', new At('HEAD'))]
    public function inspectValue(CallbackInfo $info, #[Coerce] float $value): void
    {
        if ($value > 2.5) {
            $info->cancel('coerced value was too large');
        }
    }
}


final class CoerceTarget
{
    public function value(int $value): int
    {
        return $value + 1;
    }
}

it('allows an explicitly coerced compatible callback parameter', function (): void {
    (new ReflectionClass(CoerceTarget::class))->inject(CoerceMixin::class);

    expect((new CoerceTarget())->value(2))->toBe(3)
        ->and((new CoerceTarget())->value(3))->toBe(0);
});

#[Mixin(CoerceRejectTarget::class)]
final class CoerceRejectMixin
{
    private function __construct() {}
    #[Inject('value', new At('HEAD'))]
    public function inspectValue(CallbackInfo $info, string $value): void {}
}


final class CoerceRejectTarget
{
    public function value(int $value): int
    {
        return $value + 1;
    }
}

it('rejects an incompatible callback parameter without Coerce before mutation', function (): void {
    expect(static function (): void {
        (new ReflectionClass(CoerceRejectTarget::class))->inject(CoerceRejectMixin::class);
    })->toThrow(InvalidArgumentException::class, 'add #[Coerce]');

    expect((new CoerceRejectTarget())->value(2))->toBe(3);
});

function coerceRedirectFunction(int $value): int
{
    return $value * 2;
}

#[Mixin(CoerceRedirectTarget::class)]
final class CoerceRedirectMixin
{
    private function __construct() {}
    #[Redirect('value', new At('INVOKE', 'coerceRedirectFunction'))]
    #[Coerce]
    public function redirectValue(#[Coerce] float $value): float
    {
        return $value + 4;
    }
}


final class CoerceRedirectTarget
{
    public function value(int $value): int
    {
        return coerceRedirectFunction($value);
    }
}

it('allows Coerce on a Redirect argument mapped to an invocation', function (): void {
    (new ReflectionClass(CoerceRedirectTarget::class))->inject(CoerceRedirectMixin::class);

    expect((new CoerceRedirectTarget())->value(2))->toBe(6);
});
