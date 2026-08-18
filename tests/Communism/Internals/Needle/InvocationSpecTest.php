<?php

declare(strict_types=1);

use Communism\Internals\Needle\InvocationSpec;

it('parses function, member, static, and extended invocation specifications', function (): void {
    expect(InvocationSpec::parse('strlen'))
        ->toMatchObject(['kind' => InvocationSpec::FUNCTION, 'class' => null, 'name' => 'strlen', 'numArgs' => null])
        ->and(InvocationSpec::parse('->run'))
        ->toMatchObject(['kind' => InvocationSpec::MEMBER, 'name' => 'run'])
        ->and(InvocationSpec::parse('::run'))
        ->toMatchObject(['kind' => InvocationSpec::STATIC, 'name' => 'run'])
        ->and(InvocationSpec::parse('Service::run'))
        ->toMatchObject(['kind' => InvocationSpec::STATIC, 'class' => 'Service', 'name' => 'run'])
        ->and(InvocationSpec::parse(['run', ['numargs' => [1, 5, 2]]])->numArgs)
        ->toBe([1, 5, 2])
        ->and(InvocationSpec::parse(['run', [['numargs' => 2]]])->numArgs)
        ->toBe([2, 2, 1]);
});

it('normalizes integer argument ranges and tests their bounds', function (): void {
    $exact = InvocationSpec::parse(['run', ['numargs' => 2]]);
    $range = InvocationSpec::parse(['run', ['numargs' => [1, 5]]]);

    expect($exact->acceptsArgumentCount(2))->toBeTrue()
        ->and($exact->acceptsArgumentCount(1))->toBeFalse()
        ->and($range->acceptsArgumentCount(1))->toBeTrue()
        ->and($range->acceptsArgumentCount(5))->toBeTrue()
        ->and($range->acceptsArgumentCount(6))->toBeFalse()
        ->and(InvocationSpec::parse('run')->acceptsArgumentCount(PHP_INT_MAX))->toBeTrue();
});

it('rejects malformed invocation specifications and matches wildcard names', function (): void {
    foreach ([
        [],
        [123],
        ['run', ['unknown' => 1]],
        ['run', ['numargs' => [1]], ['numargs' => 2]],
        ['run', ['numargs' => [-1, 2]]],
        ['run', ['numargs' => [3, 2]]],
        [''],
        ['has space'],
        ['->'],
        ['::123'],
        ['123'],
    ] as $spec) {
        expect(static fn(): InvocationSpec => InvocationSpec::parse($spec))
            ->toThrow(InvalidArgumentException::class);
    }

    expect(InvocationSpec::matchesName('Service::run', '*'))->toBeTrue()
        ->and(InvocationSpec::matchesName('Service::run', 'service::*'))->toBeTrue()
        ->and(InvocationSpec::matchesName('Service::run', 'Other::*'))->toBeFalse();

    expect(static fn(): InvocationSpec => InvocationSpec::parse(['run', ['numargs' => 1], ['numargs' => 2]]))
        ->toThrow(InvalidArgumentException::class);
});
