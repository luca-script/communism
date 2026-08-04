<?php

declare(strict_types=1);

use Communism\ReflectionMethod;
use Communism\__Underlying__;

final class JitBlacklistDestructorProbe
{
    public static int $destructorCalls = 0;
    public static int $blacklistCalls = 0;

    public function target(): void {}

    public function __destruct()
    {
        self::$destructorCalls++;
    }
}

it('does not instantiate classes while blacklisting instance methods', function (): void {
    expect(function_exists('opcache_jit_blacklist'))->toBeTrue();

    JitBlacklistDestructorProbe::$destructorCalls = 0;
    JitBlacklistDestructorProbe::$blacklistCalls = 0;

    $method = new ReflectionMethod(__Underlying__::class, 'disableJitForMethod');
    $replacement = new ReflectionMethod('JitBlacklistTestUnderlying', 'disableJitForMethod');
    $swapped = false;
    try {
        $method->swap($replacement);
        $swapped = true;
        JitBlacklistDestructorProbe::$blacklistCalls = 0;

        __Underlying__::disableJitForMethod(JitBlacklistDestructorProbe::class, 'target');
        __Underlying__::disableJitForMethod(JitBlacklistDestructorProbe::class, '__destruct');

        expect(JitBlacklistDestructorProbe::$blacklistCalls)->toBe(2);
        expect(JitBlacklistDestructorProbe::$destructorCalls)->toBe(0);
    } finally {
        if ($swapped) {
            $method->swap($replacement);
        }
    }
});
