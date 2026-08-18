<?php

declare(strict_types=1);

use Communism\Internals\AccessorInvokerRuntime;

function accessorRuntimeGlobalAccess(): mixed
{
    return AccessorInvokerRuntime::access(null, []);
}

final class AccessorInvokerRuntimeTarget
{
    public string $value = 'value';
    public static string $staticValue = 'static';

    public function target(): string
    {
        return 'target';
    }

    public static function staticTarget(): string
    {
        return 'static target';
    }

    public function outerInstanceAccess(): mixed
    {
        return $this->innerInstanceAccess();
    }

    public function outerGetterWithArgument(): mixed
    {
        return $this->innerGetterWithArgument();
    }

    private function innerInstanceAccess(): mixed
    {
        return AccessorInvokerRuntime::access($this, []);
    }

    private function innerGetterWithArgument(): mixed
    {
        return AccessorInvokerRuntime::access($this, ['unexpected']);
    }

    public static function outerStaticInstanceAccess(): mixed
    {
        return self::innerStaticInstanceAccess();
    }

    private static function innerStaticInstanceAccess(): mixed
    {
        return AccessorInvokerRuntime::access(null, []);
    }

    public static function outerStaticInvoke(): mixed
    {
        return self::innerStaticInvoke();
    }

    private static function innerStaticInvoke(): mixed
    {
        return AccessorInvokerRuntime::invoke(null, []);
    }
}

it('handles direct runtime access and invocation edge cases', function (): void {
    AccessorInvokerRuntime::registerAccessor(
        AccessorInvokerRuntimeTarget::class,
        'innerInstanceAccess',
        'value',
        false,
    );
    AccessorInvokerRuntime::registerAccessor(
        AccessorInvokerRuntimeTarget::class,
        'innerStaticInstanceAccess',
        'value',
        false,
    );
    AccessorInvokerRuntime::registerAccessor(
        AccessorInvokerRuntimeTarget::class,
        'innerGetterWithArgument',
        'value',
        false,
    );
    AccessorInvokerRuntime::registerInvoker(
        AccessorInvokerRuntimeTarget::class,
        'innerStaticInvoke',
        'target',
    );

    $target = new AccessorInvokerRuntimeTarget();
    expect($target->outerInstanceAccess())->toBe('value')
        ->and(fn(): mixed => $target->outerGetterWithArgument())
        ->toThrow(InvalidArgumentException::class)
        ->and(fn(): mixed => AccessorInvokerRuntimeTarget::outerStaticInstanceAccess())
        ->toThrow(LogicException::class)
        ->and(fn(): mixed => AccessorInvokerRuntimeTarget::outerStaticInvoke())
        ->toThrow(LogicException::class);
});

it('rejects unregistered runtime callers', function (): void {
    expect(fn(): mixed => AccessorInvokerRuntime::access(null, []))
        ->toThrow(LogicException::class)
        ->and(fn(): mixed => AccessorInvokerRuntime::invoke(null, []))
        ->toThrow(LogicException::class);

    expect(static fn(): mixed => accessorRuntimeGlobalAccess())
        ->toThrow(LogicException::class);
});
