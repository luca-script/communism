<?php

declare(strict_types=1);

use Communism\Internals\Executor;

describe('Executor', function (): void {
    covers(Executor::class);

    function executorCoverageFirst(): string
    {
        return 'first';
    }

    function executorCoverageSecond(): string
    {
        return 'second';
    }

    it('swaps global function implementations and can restore them', function (): void {
        expect(executorCoverageFirst())->toBe('first')
            ->and(executorCoverageSecond())->toBe('second');

        Executor::swapFunctions('executorCoverageFirst', 'executorCoverageSecond');

        try {
            expect(executorCoverageFirst())->toBe('second')
                ->and(executorCoverageSecond())->toBe('first');
        } finally {
            Executor::swapFunctions('executorCoverageFirst', 'executorCoverageSecond');
        }

        expect(executorCoverageFirst())->toBe('first')
            ->and(executorCoverageSecond())->toBe('second');
    });

    it('rejects missing global functions and ignores swapping a function with itself', function (): void {
        Executor::swapFunctions('executorCoverageFirst', 'executorCoverageFirst');

        expect(static function (): void {
            Executor::swapFunctions('executorCoverageFirst', 'missingExecutorFunction');
        })
            ->toThrow(InvalidArgumentException::class);
    });
});
