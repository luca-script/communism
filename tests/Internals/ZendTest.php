<?php

declare(strict_types=1);

use Communism\Reflect\ReflectionMethod;
use Communism\Internals\Zend;

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

    $method = new ReflectionMethod(Zend::class, 'disableJitForMethod');
    $replacement = new ReflectionMethod('JitBlacklistTestZend', 'disableJitForMethod');
    $swapped = false;
    try {
        $method->swap($replacement);
        $swapped = true;
        JitBlacklistDestructorProbe::$blacklistCalls = 0;

        Zend::disableJitForMethod(JitBlacklistDestructorProbe::class, 'target');
        Zend::disableJitForMethod(JitBlacklistDestructorProbe::class, '__destruct');

        expect(JitBlacklistDestructorProbe::$blacklistCalls)->toBe(2);
        expect(JitBlacklistDestructorProbe::$destructorCalls)->toBe(0);
    } finally {
        if ($swapped) {
            $method->swap($replacement);
        }
    }
});
