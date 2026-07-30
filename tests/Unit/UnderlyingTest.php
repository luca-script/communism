<?php

declare(strict_types=1);

use Communism\__Underlying__;
use Communism\Executor;

function test_blacklist_jit(Closure $closure): void
{
    JitBlacklistDestructorProbe::$blacklistCalls++;
    call_user_func(JitBlacklistDestructorProbe::$originalBlacklist, $closure);
}

final class JitBlacklistDestructorProbe
{
    public static int $destructorCalls = 0;
    public static int $blacklistCalls = 0;
    /** @var Closure(Closure): void */
    public static Closure $originalBlacklist;

    public function target(): void
    {
    }

    public function __destruct()
    {
        self::$destructorCalls++;
    }
}

it('does not instantiate classes while blacklisting instance methods', function (): void {
    expect(function_exists('opcache_jit_blacklist'))->toBeTrue();

    JitBlacklistDestructorProbe::$destructorCalls = 0;
    JitBlacklistDestructorProbe::$blacklistCalls = 0;

    class_exists(__Underlying__::class);
    JitBlacklistDestructorProbe::$originalBlacklist = Closure::fromCallable('Communism\\blacklistJit');
    $swapped = false;
    try {
        Executor::swapFunctions('Communism\\blacklistJit', 'test_blacklist_jit');
        $swapped = true;
        JitBlacklistDestructorProbe::$blacklistCalls = 0;

        __Underlying__::disableJitForMethod(JitBlacklistDestructorProbe::class, 'target');
        __Underlying__::disableJitForMethod(JitBlacklistDestructorProbe::class, '__destruct');

        expect(JitBlacklistDestructorProbe::$blacklistCalls)->toBe(2);
        expect(JitBlacklistDestructorProbe::$destructorCalls)->toBe(0);
    } finally {
        if ($swapped) {
            Executor::swapFunctions('Communism\\blacklistJit', 'test_blacklist_jit');
        }
    }
});
