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
        ->toBe([2, 2, 1])
        ->and(InvocationSpec::parse(['run', ['aliases' => ['renamedRun']]])->aliases)
        ->toBe(['renamedRun']);
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

it('parses PHP-native invocation selector quantifiers', function (): void {
    expect(InvocationSpec::parse('run{2}'))
        ->toMatchObject(['name' => 'run', 'minMatches' => 2, 'maxMatches' => 2])
        ->and(InvocationSpec::parse('run{1,3}')->acceptsMatchCount(2))->toBeTrue()
        ->and(InvocationSpec::parse('run{2,}')->acceptsMatchCount(99))->toBeTrue()
        ->and(InvocationSpec::parse('run{,2}')->acceptsMatchCount(0))->toBeTrue()
        ->and(InvocationSpec::parse('run')->acceptsMatchCount(1))->toBeTrue();

    expect(static fn(): InvocationSpec => InvocationSpec::parse('run{3,2}'))
        ->toThrow(InvalidArgumentException::class, 'MIN <= MAX');
});

it('parses and validates PHP invocation signatures against reflection metadata', function (): void {
    $spec = InvocationSpec::parse(['strlen', ['signature' => ['parameters' => ['string'], 'return' => 'int']]]);

    expect($spec->signature)->toBe(['parameters' => ['string'], 'return' => 'int'])
        ->and($spec->acceptsSignature(new ReflectionFunction('strlen')))->toBeTrue()
        ->and(InvocationSpec::parse(['strlen', ['signature' => ['parameters' => ['int'], 'return' => 'int']]])->acceptsSignature(new ReflectionFunction('strlen')))->toBeFalse();
});

it('rejects malformed invocation specifications and matches wildcard names', function (): void {
    foreach ([
        [],
        [123],
        ['run', ['unknown' => 1]],
        ['run', ['numargs' => [1]], ['numargs' => 2]],
        ['run', ['numargs' => [-1, 2]]],
        ['run', ['numargs' => [3, 2]]],
        ['run', ['signature' => ['parameters' => ['string']]]],
        ['run', ['signature' => 123]],
        ['run', ['aliases' => []]],
        ['run', ['aliases' => ['has space']]],
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

    expect(static fn(): InvocationSpec => InvocationSpec::parse(['run', ['signature' => ['parameters' => ['string'], 'return' => 'int']], ['signature' => ['parameters' => ['string'], 'return' => 'int']]]))
        ->toThrow(InvalidArgumentException::class);
});
