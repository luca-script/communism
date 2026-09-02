<?php

declare(strict_types=1);

use Communism\Mixin\At;
use Communism\Mixin\CallbackInfo;
use Communism\Mixin\Coerce;
use Communism\Mixin\Inject;
use Communism\Mixin\HandlerValidationException;
use Communism\Mixin\Mixin;
use Communism\Mixin\Redirect;
use Communism\Reflect\ReflectionClass;

interface CoerceReceiverContract
{
    public function hidden(string $value): string;
}

final class CoerceReceiverService implements CoerceReceiverContract
{
    public function hidden(string $value): string
    {
        return $value . '!';
    }
}

#[Mixin(CoerceReceiverTarget::class)]
final class CoerceReceiverMixin
{
    private function __construct() {}

    #[Redirect('run', new At('INVOKE', '->hidden'))]
    public function redirect(CoerceReceiverContract $receiver, string $value): string
    {
        return $receiver->hidden(strtoupper($value));
    }
}

final class CoerceReceiverTarget
{
    public function run(CoerceReceiverService $service, string $value): string
    {
        return $service->hidden($value);
    }
}

it('validates a Redirect member receiver against its declared interface', function (): void {
    (new ReflectionClass(CoerceReceiverTarget::class))->inject(CoerceReceiverMixin::class);

    expect((new CoerceReceiverTarget())->run(new CoerceReceiverService(), 'ok'))->toBe('OK!');
});

#[Mixin(CoerceReceiverRejectTarget::class)]
final class CoerceReceiverRejectMixin
{
    private function __construct() {}

    #[Redirect('run', new At('INVOKE', '->hidden'))]
    public function redirect(string $receiver, string $value): string
    {
        return $value;
    }
}

final class CoerceReceiverRejectTarget
{
    public function run(CoerceReceiverService $service, string $value): string
    {
        return $service->hidden($value);
    }
}

it('rejects an incompatible Redirect member receiver before mutation', function (): void {
    expect(static fn() => (new ReflectionClass(CoerceReceiverRejectTarget::class))->inject(CoerceReceiverRejectMixin::class))
        ->toThrow(InvalidArgumentException::class, 'Redirect receiver');

    expect((new CoerceReceiverRejectTarget())->run(new CoerceReceiverService(), 'ok'))->toBe('ok!');
});

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

#[Mixin(CoerceRedirectReturnTarget::class)]
final class CoerceRedirectReturnMixin
{
    private function __construct() {}

    #[Redirect('value', new At('INVOKE', 'coerceRedirectReturnSource'))]
    #[Coerce]
    public function redirectReturn(): float
    {
        return 3.5;
    }
}

function coerceRedirectReturnSource(): int
{
    return 1;
}

final class CoerceRedirectReturnTarget
{
    public function value(): int
    {
        return coerceRedirectReturnSource();
    }
}

it('casts a coerced Redirect replacement result before a strict return', function (): void {
    (new ReflectionClass(CoerceRedirectReturnTarget::class))->inject(CoerceRedirectReturnMixin::class);

    expect((new CoerceRedirectReturnTarget())->value())->toBe(3);
});

#[Mixin(CoerceFieldTarget::class)]
final class CoerceFieldMixin
{
    private function __construct() {}

    #[Redirect('write', new At('FIELD', '::value'))]
    public function redirectField(#[Coerce] float $value): void
    {
        $GLOBALS['coerce_field_value'] = $value + 0.5;
    }
}

final class CoerceFieldTarget
{
    public int $value = 1;

    public function write(int $value): void
    {
        $this->value = $value;
    }
}

it('allows Coerce on a Redirect field value with a compatible numeric type', function (): void {
    (new ReflectionClass(CoerceFieldTarget::class))->inject(CoerceFieldMixin::class);

    $target = new CoerceFieldTarget();
    $target->write(7);

    expect($target->value)->toBe(1)
        ->and($GLOBALS['coerce_field_value'])->toBe(7.5);
});

#[Mixin(CoerceFieldReadTarget::class)]
final class CoerceFieldReadMixin
{
    private function __construct() {}

    #[Redirect('read', new At('FIELD', '::value'))]
    #[Coerce]
    public function redirectFieldRead(): int
    {
        return 3;
    }
}

final class CoerceFieldReadTarget
{
    public float $value = 1.5;

    public function read(): float
    {
        return $this->value;
    }
}

it('casts a coerced Redirect field-read result to the property type', function (): void {
    (new ReflectionClass(CoerceFieldReadTarget::class))->inject(CoerceFieldReadMixin::class);

    expect((new CoerceFieldReadTarget())->read())->toBe(3.0);
});

#[Mixin(CoerceFieldRejectTarget::class)]
final class CoerceFieldRejectMixin
{
    private function __construct() {}

    #[Redirect('write', new At('FIELD', '::value'))]
    public function redirectField(string $value): void {}
}

final class CoerceFieldRejectTarget
{
    public int $value = 1;

    public function write(int $value): void
    {
        $this->value = $value;
    }
}

it('rejects an incompatible Redirect field parameter before mutation', function (): void {
    try {
        (new ReflectionClass(CoerceFieldRejectTarget::class))->inject(CoerceFieldRejectMixin::class);
        throw new RuntimeException('Expected handler validation to fail');
    } catch (HandlerValidationException $exception) {
        expect($exception->handlerMethod)->toBe(CoerceFieldRejectMixin::class . '::redirectField')
            ->and($exception->targetMethod)->toBe(CoerceFieldRejectTarget::class . '::write')
            ->and($exception->point)->toBe('FIELD')
            ->and($exception->start)->toBeGreaterThanOrEqual(0)
            ->and($exception->end)->toBeGreaterThan($exception->start)
            ->and($exception->selector)->toContain('FIELD target ::value')
            ->and($exception->getMessage())->toContain('add #[Coerce]')
            ->and($exception->getPrevious())->toBeInstanceOf(InvalidArgumentException::class);
    }

    expect((new CoerceFieldRejectTarget())->value)->toBe(1);
});

class CoerceConstructorTarget {}
final class CoerceConstructorReplacement {}

#[Mixin(CoerceConstructorRedirectTarget::class)]
final class CoerceConstructorMixin
{
    private function __construct() {}

    #[Redirect('make', new At('NEW', CoerceConstructorTarget::class))]
    #[Coerce]
    public function redirectConstructor(): CoerceConstructorReplacement
    {
        return new CoerceConstructorReplacement();
    }
}

final class CoerceConstructorRedirectTarget
{
    public function make(): object
    {
        return new CoerceConstructorTarget();
    }
}

it('allows Coerce on a compatible Redirect constructor replacement', function (): void {
    (new ReflectionClass(CoerceConstructorRedirectTarget::class))->inject(CoerceConstructorMixin::class);

    expect((new CoerceConstructorRedirectTarget())->make())->toBeInstanceOf(CoerceConstructorReplacement::class);
});
