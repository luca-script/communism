<?php

declare(strict_types=1);

use Communism\Internals\Needle\Instruction;
use Communism\Internals\Needle\Matcher;
use Communism\Internals\Needle\MethodBody;
use Communism\Internals\Needle\Operand;
use Communism\Mixin\At;
use Communism\Mixin\Slice;

it('covers Matcher operand and boundary helpers', function (): void {
    $call = static function (string $name, mixed ...$arguments): mixed {
        $method = new ReflectionMethod(Matcher::class, $name);
        $method->setAccessible(true);

        return $method->invoke(null, ...$arguments);
    };
    $unused = Operand::unused();
    $constant = Operand::constant('member', 0);
    $body = new MethodBody('matcher', null, 0, 0, [
        new Instruction(0, 'RECV', Operand::cv(0), $unused, $unused),
        new Instruction(0, 'ASSIGN_OBJ', $unused, Operand::cv(0), $constant),
        new Instruction(0, 'OP_DATA', $unused, Operand::constant(1, 0), $unused),
        new Instruction(0, 'FETCH_OBJ_R', Operand::cv(1), Operand::cv(0), $constant),
        new Instruction(0, 'FETCH_DIM_R', Operand::cv(2), Operand::cv(0), Operand::constant(0, 0)),
        new Instruction(0, 'THROW', $unused, Operand::constant('Exception', 0), $unused),
    ], [0 => 'object', 1 => 'value', 2 => 'array']);

    expect($call('fieldMode', new Instruction(0, 'ASSIGN_OBJ', $unused, $unused, $unused)))->toBe('write')
        ->and($call('fieldMode', new Instruction(0, 'ASSIGN_DIM', $unused, $unused, $unused)))->toBe('array-write')
        ->and($call('fieldMode', new Instruction(0, 'FETCH_DIM_W', $unused, $unused, $unused)))->toBe('array-read')
        ->and($call('fieldMode', new Instruction(0, 'FETCH_OBJ_R', $unused, $unused, $unused)))->toBe('read')
        ->and($call('assignmentLength', $body, 1))->toBe(2)
        ->and($call('assignmentLength', $body, 3))->toBe(1)
        ->and($call('matchesField', $body->instruction(1), '::member', 'write'))->toBeTrue()
        ->and($call('matchesField', $body->instruction(3), '::member', 'read', 'READ'))->toBeTrue()
        ->and($call('matchesField', $body->instruction(4), '[]', 'array-read', 'ARRAY_READ'))->toBeTrue()
        ->and($call('matchesField', $body->instruction(3), '::member', 'read', 'WRITE'))->toBeFalse()
        ->and($call('matchesField', $body->instruction(3), 'bad', 'read'))->toBeFalse();

    $throwBody = new MethodBody('throw', null, 0, 0, [
        new Instruction(0, 'QM_ASSIGN', Operand::unused(), Operand::constant('Exception', 0), $unused),
        new Instruction(0, 'THROW', $unused, Operand::cv(0), $unused),
    ]);
    expect($call('matchesThrow', $throwBody, 1, 'Exception'))->toBeTrue()
        ->and($call('matchesThrow', $body, 5, 'Other'))->toBeFalse()
        ->and($call('matchesThrow', $body, 5, ''))->toBeTrue()
        ->and($call('matchesConstant', $body->instruction(1), 'member', null))->toBeTrue()
        ->and($call('matchesConstant', $body->instruction(1), 'member', 'string'))->toBeTrue()
        ->and($call('matchesConstant', $body->instruction(1), 'member', 'int'))->toBeFalse()
        ->and($call('matchesConstantType', 1, 'int'))->toBeTrue()
        ->and($call('matchesConstantType', 1.0, 'float'))->toBeTrue()
        ->and($call('matchesConstantType', 'x', 'string'))->toBeTrue()
        ->and($call('matchesConstantType', null, 'null'))->toBeTrue()
        ->and($call('matchesConstantType', true, 'bool'))->toBeTrue()
        ->and($call('matchesConstantType', [], 'array'))->toBeTrue()
        ->and($call('matchesConstantType', new stdClass(), 'object'))->toBeTrue()
        ->and($call('matchesConstantType', stdClass::class, 'class'))->toBeTrue()
        ->and($call('matchesConstantType', 'x', 'unknown'))->toBeFalse();

    expect($call('matchesVariable', $body, new Instruction(0, 'ASSIGN', Operand::cv(1), Operand::constant(1, 0), $unused), 'value', 'store', null))->toBeTrue()
        ->and($call('matchesVariable', $body, new Instruction(0, 'FETCH_R', $unused, Operand::cv(1), $unused), 'value', 'load', null))->toBeTrue()
        ->and($call('matchesVariable', $body, new Instruction(0, 'RECV', Operand::cv(1), $unused, $unused), 'value', 'load', null))->toBeFalse()
        ->and($call('matchesVariable', $body, new Instruction(0, 'FETCH_R', $unused, Operand::cv(1), $unused), 'value', 'load', 99))->toBeFalse()
        ->and($call('isBuiltIn', 'HEAD'))->toBeTrue()
        ->and($call('isBuiltIn', '_CUSTOM'))->toBeFalse()
        ->and($call('isInvocationStart', 'INIT_FCALL'))->toBeTrue()
        ->and($call('isInvocationStart', 'FRAMELESS_ICALL_1'))->toBeTrue()
        ->and($call('isInvocationStart', 'RETURN'))->toBeFalse()
        ->and($call('invocationEnd', $body, 1))->toBeNull();
});

it('matches PHP 8.6 frameless internal calls through their dispatch branch', function (): void {
    $unused = Operand::unused();
    $body = new MethodBody('frameless', null, 0, 0, [
        new Instruction(0, 'JMP_FRAMELESS', $unused, Operand::constant('strtoupper', 0), $unused),
        new Instruction(0, 'FRAMELESS_ICALL_1', Operand::temporary(0), Operand::cv(0), $unused),
    ]);

    expect(Matcher::find($body, new At('INVOKE', 'strtoupper')))->toHaveCount(1)
        ->and(Matcher::find($body, new At('INVOKE', 'strtolower')))->toBe([])
        ->and(Matcher::find($body, new At('INVOKE', 'strtoupper'), argumentIndex: 0))->toHaveCount(1);
});

it('covers Matcher validation and slice boundaries', function (): void {
    $unused = Operand::unused();

    expect(static fn() => Matcher::registerInjectionPoint('HEAD', static fn(): array => []))
        ->toThrow(InvalidArgumentException::class)
        ->and(static fn() => Matcher::validateAt(new At('TAIL', action: 'after')))
        ->toThrow(InvalidArgumentException::class)
        ->and(static fn() => Matcher::validateAt(new At('INVOKE_ASSIGN', 'name', action: 'before')))
        ->toThrow(InvalidArgumentException::class)
        ->and(static fn() => Matcher::validateAt(new At('INVOKE_ASSIGN', target: 1)))
        ->toThrow(InvalidArgumentException::class)
        ->and(static fn() => Matcher::find(new MethodBody('matcher', null, 0, 0, []), new At('HEAD'), argumentIndex: 0))
        ->toThrow(InvalidArgumentException::class)
        ->and(static fn() => Matcher::find(new MethodBody('matcher', null, 0, 0, []), new At('INVOKE', 'name', shift: 'BY', by: 1), argumentIndex: 0))
        ->toThrow(InvalidArgumentException::class)
        ->and(static fn() => Matcher::find(new MethodBody('matcher', null, 0, 0, []), new At('HEAD'), constantType: 'int'))
        ->toThrow(InvalidArgumentException::class)
        ->and(Matcher::find(new MethodBody('matcher::__construct', null, 0, 0, [new Instruction(0, 'NEW', Operand::unused(), Operand::constant('X', 0), Operand::unused())]), new At('NEW')))
        ->toEqual([]);

    expect(Matcher::find(new MethodBody('X', null, 0, 0, []), new At('CONSTRUCTOR_HEAD')))
        ->toEqual([])
        ->and(Matcher::find(new MethodBody('X::__construct', null, 0, 0, [new Instruction(0, 'RECV', Operand::cv(0), Operand::unused(), Operand::unused()), new Instruction(0, 'NOP', Operand::unused(), Operand::unused(), Operand::unused())]), new At('CONSTRUCTOR_HEAD')))
        ->toHaveCount(1);

    expect(static fn() => Matcher::validateAt(new At('HEAD', action: 'after')))
        ->toThrow(InvalidArgumentException::class)
        ->and(static fn() => Matcher::validateAt(new At('FIELD', '::value', action: 'before')))
        ->toThrow(InvalidArgumentException::class)
        ->and(static fn() => Matcher::validateAt(new At('THROW', target: 1)))
        ->toThrow(InvalidArgumentException::class)
        ->and(static fn() => Matcher::validateAt(new At('UNKNOWN')))
        ->toThrow(InvalidArgumentException::class);

    $body = new MethodBody('matcher', null, 0, 0, [
        new Instruction(0, 'NOP', Operand::unused(), Operand::unused(), Operand::unused()),
        new Instruction(0, 'RETURN', Operand::unused(), Operand::constant(1, 0), Operand::unused()),
    ]);
    expect(Matcher::find($body, new At('RETURN'), slice: new Slice(new At('HEAD'))))
        ->toHaveCount(1)
        ->and(Matcher::find($body, new At('HEAD'))[0]->start)->toBe(0);

    $call = static function (string $name, mixed ...$arguments): mixed {
        $method = new ReflectionMethod(Matcher::class, $name);
        $method->setAccessible(true);

        return $method->invoke(null, ...$arguments);
    };
    $jump = new Instruction(0, 'JMP', Operand::unused(), $unused, $unused);
    expect($call('matchesJump', $jump, ''))->toBeTrue()
        ->and($call('matchesJump', $jump, 1))->toBeFalse()
        ->and($call('matchesJump', $jump, 'UNCONDITIONAL'))->toBeTrue()
        ->and($call('matchesJump', new Instruction(0, 'JMPZ', Operand::unused(), $unused, $unused), 'CONDITIONAL'))->toBeTrue()
        ->and(static fn() => $call('shift', $body, new At('HEAD', shift: 'BY', by: 32), [new \Communism\Internals\Needle\MatchResult(0, 0, 'head')]))
        ->toThrow(InvalidArgumentException::class)
        ->and(static fn() => $call('sliceBounds', $body, new Slice(new At('CONSTANT', 'missing'), null)))
        ->toThrow(InvalidArgumentException::class)
        ->and(static fn() => $call('sliceBounds', $body, new Slice(null, new At('CONSTANT', 'missing'))))
        ->toThrow(InvalidArgumentException::class)
        ->and($call('validateAssignmentTarget', '[]'))->toBeNull()
        ->and(static fn() => $call('validateAssignmentTarget', '$bad'))->toThrow(InvalidArgumentException::class)
        ->and($call('matchesField', new Instruction(0, 'FETCH_OBJ_R', $unused, Operand::cv(0), Operand::constant('member', 0)), '::member', 'read', 'UNKNOWN'))->toBeFalse();

    expect($call('matchesField', new Instruction(0, 'ASSIGN_DIM', $unused, Operand::cv(0), Operand::constant(0, 0)), '[]', 'array-write', 'ARRAY_WRITE'))->toBeTrue();

    $nestedConstructor = new MethodBody('X::__construct', null, 0, 0, [
        new Instruction(0, 'NEW', $unused, Operand::constant('X', 0), $unused),
        new Instruction(0, 'INIT_FCALL', $unused, $unused, $unused),
        new Instruction(0, 'DO_FCALL', $unused, $unused, $unused),
        new Instruction(0, 'DO_FCALL', $unused, $unused, $unused),
    ]);
    expect($call('constructorEnd', $nestedConstructor, 0))->toBe(3)
        ->and($call('constructorEnd', new MethodBody('X::__construct', null, 0, 0, [new Instruction(0, 'NEW', $unused, $unused, $unused)]), 0))->toBeNull()
        ->and($call('matchesInvocation', new MethodBody('matcher', null, 0, 0, [new Instruction(0, 'INIT_FCALL', $unused, Operand::constant('foo', 0), $unused), new Instruction(0, 'DO_FCALL', $unused, $unused, $unused)]), 0, 1, \Communism\Internals\Needle\InvocationSpec::parse(['foo', ['numargs' => 2]])))->toBeFalse();
});
