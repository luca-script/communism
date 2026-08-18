<?php

declare(strict_types=1);

use Communism\Internals\Zend;
use Communism\Reflect\ReflectionMethod;

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

final class JitBlacklistTestZend extends Zend
{
    public static function disableJitForMethod(string $className, string $method): void
    {
        JitBlacklistDestructorProbe::$blacklistCalls++;
    }
}

it('does not instantiate classes while blacklisting instance methods', function (): void {
    expect(function_exists('opcache_jit_blacklist'))->toBeTrue();

    JitBlacklistDestructorProbe::$destructorCalls = 0;
    JitBlacklistDestructorProbe::$blacklistCalls = 0;
    Zend::disableJitForMethod(JitBlacklistDestructorProbe::class, 'target');
    expect(JitBlacklistDestructorProbe::$destructorCalls)->toBe(0);

    $method = new ReflectionMethod(Zend::class, 'disableJitForMethod');
    $replacement = new ReflectionMethod(JitBlacklistTestZend::class, 'disableJitForMethod');
    $swapped = false;
    try {
        $method->swap($replacement);
        $swapped = true;

        Zend::disableJitForMethod(JitBlacklistDestructorProbe::class, 'target');
        Zend::disableJitForMethod(JitBlacklistDestructorProbe::class, '__destruct');

        expect(JitBlacklistDestructorProbe::$blacklistCalls)->toBe(2)
            ->and(JitBlacklistDestructorProbe::$destructorCalls)->toBe(0);
    } finally {
        if ($swapped) {
            $method->swap($replacement);
        }
    }
});
