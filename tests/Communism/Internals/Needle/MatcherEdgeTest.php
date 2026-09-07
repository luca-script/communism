<?php

declare(strict_types=1);

use Communism\Internals\Needle\Instruction;
use Communism\Internals\Needle\InvocationSpec;
use Communism\Internals\Needle\Matcher;
use Communism\Internals\Needle\MatchResult;
use Communism\Internals\Needle\MethodBody;
use Communism\Internals\Needle\Operand;
use Communism\Mixin\At;
use Communism\Mixin\Slice;

describe('Matcher', function (): void {
    covers([MatchResult::class, Matcher::class]);

    final class MatcherReturnTypeFixture
    {
        public int $typedProperty = 0;
        public $untypedProperty;

        public static function returnsInt(): int
        {
            return 1;
        }

        public function returnsString(): string
        {
            return 'value';
        }
    }

    it('covers Matcher operand and boundary helpers', function (): void {
        $call = static function (string $name, mixed ...$arguments): mixed {
            $method = new ReflectionMethod(Matcher::class, $name);

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
            ->and($call('isInvocationStart', 'INIT_FCALL_BY_NAME'))->toBeTrue()
            ->and($call('isInvocationStart', 'FRAMELESS_ICALL_1'))->toBeTrue()
            ->and($call('isInvocationStart', 'RETURN'))->toBeFalse()
            ->and($call('invocationEnd', $body, 1))->toBeNull();
    });

    it('matches named function calls emitted by newer PHP builds', function (): void {
        $unused = Operand::unused();
        $body = new MethodBody('named-call', null, 0, 0, [
            new Instruction(0, 'INIT_FCALL_BY_NAME', $unused, $unused, Operand::constant('strtoupper', 0)),
            new Instruction(0, 'SEND_VAR', $unused, Operand::cv(0), $unused),
            new Instruction(0, 'DO_FCALL', Operand::temporary(1), $unused, $unused),
        ]);

        expect(Matcher::find($body, new At('INVOKE', 'strtoupper')))->toHaveCount(1)
            ->and(Matcher::find($body, new At('INVOKE', 'strtolower')))->toBe([]);
    });

    it('matches invocation results assigned to a local', function (): void {
        $unused = Operand::unused();
        $body = new MethodBody('invoke-assign', null, 0, 0, [
            new Instruction(0, 'INIT_FCALL', $unused, $unused, Operand::constant('strlen', 0)),
            new Instruction(0, 'SEND_VAR', $unused, Operand::cv(0), $unused),
            new Instruction(0, 'DO_FCALL', Operand::temporary(16), $unused, $unused),
            new Instruction(0, 'ASSIGN', $unused, $unused, Operand::temporary(16)),
        ]);

        expect(Matcher::find($body, new At('INVOKE_ASSIGN', 'strlen')))->toHaveCount(1)
            ->and(Matcher::find(new MethodBody('invoke-assign', null, 0, 0, array_slice($body->instructions(), 0, 3)), new At('INVOKE_ASSIGN', 'strlen')))
            ->toBe([])
            ->and(Matcher::find($body, new At('INVOKE', 'strlen'), argumentIndex: 1))->toBe([])
            ->and(static fn() => Matcher::find($body, new At('INVOKE_ASSIGN', 'strlen{2}')))
            ->toThrow(InvalidArgumentException::class, 'outside its quantifier bounds');

        $twoCalls = new MethodBody('invoke-assign', null, 0, 0, [
            ...$body->instructions(),
            ...$body->instructions(),
        ]);
        expect(Matcher::find($twoCalls, new At('INVOKE_ASSIGN', 'strlen{0,1}')))->toHaveCount(1);
    });

    it('matches PHP 8.6 frameless internal calls through their dispatch branch', function (): void {
        $unused = Operand::unused();
        $body = new MethodBody('frameless', null, 0, 0, [
            new Instruction(0, 'JMP_FRAMELESS', $unused, Operand::constant('strtoupper', 0), $unused),
            new Instruction(0, 'INIT_FCALL', $unused, $unused, Operand::constant('strtoupper', 0)),
            new Instruction(0, 'SEND_VAR', $unused, Operand::cv(0), $unused),
            new Instruction(0, 'DO_ICALL', Operand::temporary(1), $unused, $unused),
            new Instruction(0, 'FRAMELESS_ICALL_1', Operand::temporary(0), Operand::cv(0), $unused),
        ]);

        expect(Matcher::find($body, new At('INVOKE', 'strtoupper')))->toHaveCount(1)
            ->and(Matcher::find($body, new At('INVOKE', 'strtolower')))->toBe([])
            ->and(Matcher::find($body, new At('INVOKE', 'strtoupper'), argumentIndex: 0))->toHaveCount(1);
    });

    it('covers Matcher validation and slice boundaries', function (): void {
        $unused = Operand::unused();

        Matcher::validateAt(new At('INVOKE_ASSIGN', 'name', action: 'after'));
        Matcher::validateAt(new At('JUMP'));
        Matcher::validateAt(new At('NEW'));
        Matcher::validateAt(new At('FIELD', '::value'));
        Matcher::validateAt(new At('STORE'));
        Matcher::validateAt(new At('CONSTANT'));
        Matcher::validateAt(new At('THROW'));

        expect(static fn() => Matcher::registerInjectionPoint('HEAD', static fn(): array => []))
            ->toThrow(InvalidArgumentException::class)
            ->and(static fn() => Matcher::validateAt(new At('INVOKE', 'name', shift: 'BY', by: 1, action: 'replace')))
            ->toThrow(InvalidArgumentException::class)
            ->and(static fn() => Matcher::validateAt(new At('HEAD', opcode: 'RETURN')))
            ->toThrow(InvalidArgumentException::class)
            ->and(static fn() => Matcher::validateAt(new At('JUMP', 'target', opcode: 'JMP')))
            ->toThrow(InvalidArgumentException::class)
            ->and(static fn() => Matcher::validateAt(new At('INVOKE', 'name', action: 'modify')))
            ->toThrow(InvalidArgumentException::class)
            ->and(static fn() => Matcher::validateAt(new At('NEW', target: 1)))
            ->toThrow(InvalidArgumentException::class)
            ->and(static fn() => Matcher::validateAt(new At('JUMP', target: 1)))
            ->toThrow(InvalidArgumentException::class)
            ->and(static fn() => Matcher::validateAt(new At('STORE', target: 1)))
            ->toThrow(InvalidArgumentException::class)
            ->and(static fn() => Matcher::validateAt(new At('FIELD', target: 1)))
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

        expect(Matcher::find(new MethodBody('matcher', null, 0, 0, [
            new Instruction(0, 'RECV', Operand::cv(0), $unused, $unused),
            new Instruction(0, 'NOP', $unused, $unused, $unused),
        ]), new At('HEAD'))[0]->start)->toBe(1);

        expect(Matcher::find(new MethodBody('X::__construct', null, 0, 0, [
            new Instruction(0, 'RECV', Operand::cv(0), $unused, $unused),
            new Instruction(0, 'INIT_STATIC_METHOD_CALL', $unused, Operand::unused(2), Operand::constant('__construct', 0)),
            new Instruction(0, 'DO_STATIC_METHOD_CALL', $unused, $unused, $unused),
            new Instruction(0, 'RETURN', $unused, $unused, $unused),
        ]), new At('CONSTRUCTOR_HEAD'))[0]->start)->toBe(3);

        $newBody = new MethodBody('new', null, 0, 0, [
            new Instruction(0, 'NEW', $unused, Operand::constant(stdClass::class, 0), $unused),
            new Instruction(0, 'DO_FCALL', $unused, $unused, $unused),
        ]);
        expect(Matcher::find($newBody, new At('NEW', stdClass::class)))->toHaveCount(1)
            ->and(Matcher::find($newBody, new At('NEW', DateTime::class)))->toBe([]);

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
        expect(Matcher::find(new MethodBody('constant', null, 0, 0, [
            new Instruction(0, 'QM_ASSIGN', Operand::constant(1, 0), Operand::unused(), Operand::unused()),
        ]), new At('CONSTANT', 1)))->toHaveCount(1);

        $call = static function (string $name, mixed ...$arguments): mixed {
            $method = new ReflectionMethod(Matcher::class, $name);

            return $method->invoke(null, ...$arguments);
        };
        $jump = new Instruction(0, 'JMP', Operand::unused(), $unused, $unused);
        expect($call('matchesJump', $jump, ''))->toBeTrue()
            ->and($call('matchesJump', $jump, 1))->toBeFalse()
            ->and($call('matchesJump', new Instruction(0, 'RETURN', $unused, $unused, $unused), ''))->toBeFalse()
            ->and($call('matchesJump', $jump, 'UNCONDITIONAL'))->toBeTrue()
            ->and($call('matchesJump', new Instruction(0, 'JMPZ', Operand::unused(), $unused, $unused), 'CONDITIONAL'))->toBeTrue()
            ->and($call(
                'matchesInvocationAssignment',
                new Instruction(0, 'ASSIGN', $unused, $unused, Operand::temporary(16)),
                new Instruction(0, 'DO_FCALL', Operand::temporary(16), $unused, $unused),
            ))->toBeTrue()
            ->and($call(
                'matchesInvocationAssignment',
                new Instruction(0, 'RETURN', $unused, $unused, Operand::temporary(16)),
                new Instruction(0, 'DO_FCALL', Operand::temporary(16), $unused, $unused),
            ))->toBeFalse()
            ->and($call('fieldTargets', ['::value', ['aliases' => ['::other']]]))->toBe(['::value', '::other'])
            ->and(static fn() => $call('shift', $body, new At('HEAD', shift: 'BY', by: 32), [new MatchResult(0, 0, 'head')]))
            ->toThrow(InvalidArgumentException::class)
            ->and(static fn() => $call('sliceBounds', $body, new Slice(new At('CONSTANT', 'missing'), null)))
            ->toThrow(InvalidArgumentException::class)
            ->and(static fn() => $call('sliceBounds', $body, new Slice(null, new At('CONSTANT', 'missing'))))
            ->toThrow(InvalidArgumentException::class)
            ->and($call('validateAssignmentTarget', '[]'))->toBeNull()
            ->and($call('fieldTargets', ['::value']))->toBe(['::value'])
            ->and(static fn() => $call('fieldTargets', ['::value', ['unknown' => []]]))->toThrow(InvalidArgumentException::class, 'Unknown FIELD selector extension')
            ->and(static fn() => $call('fieldTargets', ['::value', ['aliases' => []]]))->toThrow(InvalidArgumentException::class, 'non-empty list')
            ->and(static fn() => $call('fieldTargets', ['::value', ['aliases' => [1]]]))->toThrow(InvalidArgumentException::class, 'member targets')
            ->and(static fn() => $call('validateAssignmentTarget', '::'))->toThrow(InvalidArgumentException::class, 'field target')
            ->and(Matcher::find(new MethodBody('jump', null, 0, 0, [
                new Instruction(0, 'JMP', Operand::unused(), $unused, $unused),
                new Instruction(0, 'JMPZ', Operand::unused(), $unused, $unused),
            ]), new At('JUMP', opcode: 'JMP')))->toHaveCount(1)
            ->and(static fn() => $call('validateAssignmentTarget', '$bad'))->toThrow(InvalidArgumentException::class)
            ->and($call('matchesField', new Instruction(0, 'FETCH_OBJ_R', $unused, Operand::cv(0), Operand::constant('member', 0)), '::member', 'read', 'UNKNOWN'))->toBeFalse();

        expect($call('matchesField', new Instruction(0, 'ASSIGN_DIM', $unused, Operand::cv(0), Operand::constant(0, 0)), '[]', 'array-write', 'ARRAY_WRITE'))->toBeTrue();
        expect(static fn() => $call('fieldTargets', [1]))->toThrow(InvalidArgumentException::class)
            ->and(static fn() => $call('fieldTargets', ['::value', [], []]))->toThrow(InvalidArgumentException::class);
        expect($call('matchesField', new Instruction(0, 'FETCH_OBJ_R', $unused, Operand::cv(0), Operand::constant('member', 0)), $unused, 'read'))->toBeFalse()
            ->and($call('matchesField', new Instruction(0, 'FETCH_OBJ_R', $unused, Operand::cv(0), Operand::constant('member', 0)), ['::member'], 'read'))->toBeFalse()
            ->and($call('matchesField', $body, 'not an instruction', '::member', 'read'))->toBeFalse()
            ->and($call('matchesFieldDescriptor', new MethodBody('not-a-method', null, 0, 0, []), 'typedProperty', 'int'))->toBeFalse()
            ->and($call('matchesFieldDescriptor', new MethodBody('MissingMatcherClass::method', null, 0, 0, []), 'typedProperty', 'int'))->toBeFalse()
            ->and($call('matchesFieldDescriptor', new MethodBody(MatcherReturnTypeFixture::class . '::returnsString', null, 0, 0, []), 'missingProperty', 'int'))->toBeFalse()
            ->and($call('matchesFieldDescriptor', new MethodBody(MatcherReturnTypeFixture::class . '::returnsString', null, 0, 0, []), 'untypedProperty', 'int'))->toBeFalse()
            ->and($call('matchesFieldDescriptor', new MethodBody(MatcherReturnTypeFixture::class . '::returnsString', null, 0, 0, []), 'typedProperty', 'int'))->toBeTrue();

        expect($call('matchesVariable', $body, new Instruction(0, 'FETCH_R', $unused, Operand::cv(1), $unused), 123, 'load', null))->toBeFalse()
            ->and($call('matchesVariable', $body, 0, 123, 'load', null))->toBeFalse()
            ->and($call('matchesVariable', $body, new Instruction(0, 'ASSIGN', $unused, Operand::constant(1, 0), $unused), 'value', 'store', null))->toBeFalse()
            ->and($call('matchesVariable', $body, new Instruction(0, 'VERIFY_RETURN_TYPE', $unused, Operand::cv(1), $unused), 'value', 'load', null))->toBeFalse()
            ->and($call('matchesVariable', $body, 0, new Instruction(0, 'FETCH_R', $unused, Operand::cv(1), $unused), 'value', 'load', null, 'string', true))->toBeFalse()
            ->and($call('matchesVariableTypeAt', $body, Operand::cv(1), 'mixed', 1))->toBeTrue();
        expect($call('matchesVariable', $body, 0, new Instruction(0, 'FETCH_R', $unused, Operand::cv(1), $unused), 'value', 'load', null, 'string', false))->toBeFalse();

        $nestedConstructor = new MethodBody('X::__construct', null, 0, 0, [
            new Instruction(0, 'NEW', $unused, Operand::constant('X', 0), $unused),
            new Instruction(0, 'INIT_FCALL', $unused, $unused, $unused),
            new Instruction(0, 'DO_FCALL', $unused, $unused, $unused),
            new Instruction(0, 'DO_FCALL', $unused, $unused, $unused),
        ]);
        expect($call('constructorEnd', $nestedConstructor, 0))->toBe(3)
            ->and($call('constructorEnd', new MethodBody('X::__construct', null, 0, 0, [
                new Instruction(0, 'NEW', $unused, $unused, $unused),
                new Instruction(0, 'NOP', $unused, $unused, $unused),
            ]), 0))->toBeNull()
            ->and($call('constructorEnd', new MethodBody('X::__construct', null, 0, 0, [new Instruction(0, 'NEW', $unused, $unused, $unused)]), 0))->toBeNull()
            ->and($call('matchesInvocation', new MethodBody('matcher', null, 0, 0, [new Instruction(0, 'INIT_FCALL', $unused, Operand::constant('foo', 0), $unused), new Instruction(0, 'DO_FCALL', $unused, $unused, $unused)]), 0, 1, InvocationSpec::parse(['foo', ['numargs' => 2]])))->toBeFalse();

        $frameless = new MethodBody('frameless-direct', null, 0, 0, [
            new Instruction(0, 'FRAMELESS_ICALL_1', Operand::temporary(0), $unused, $unused, invocationTarget: 'strtoupper'),
        ]);
        expect($call('matchesFramelessFunction', $frameless, 0, InvocationSpec::parse('strtoupper')))->toBeTrue()
            ->and($call('matchesFramelessFunction', new MethodBody('frameless-direct', null, 0, 0, [new Instruction(0, 'NOP', $unused, $unused, $unused)]), 0, InvocationSpec::parse('strtoupper')))->toBeFalse()
            ->and($call('invocationEnd', new MethodBody('frameless-direct', null, 0, 0, [new Instruction(0, 'JMP_FRAMELESS', $unused, $unused, $unused)]), 0))->toBeNull()
            ->and($call('invocationArguments', $frameless, 0, 0))->toBe(1);
    });

    it('infers operand types from constants and bytecode assignments', function (): void {
        $unused = Operand::unused();
        $cv0 = Operand::cv(0);
        $cv1 = Operand::cv(1);
        $cv2 = Operand::cv(2);
        $body = new MethodBody('inference', null, 0, 0, [
            new Instruction(0, 'ASSIGN', $unused, $cv0, Operand::constant(3, 0)),
            new Instruction(0, 'ASSIGN', $unused, $cv1, Operand::constant(2.5, 1)),
            new Instruction(0, 'ADD', $cv2, $cv0, $cv1),
            new Instruction(0, 'BOOL', $cv2, $cv0, $unused),
        ]);

        expect(Matcher::inferOperandType($body, Operand::constant(1, 0), 0))->toBe('int')
            ->and(Matcher::inferOperandType($body, Operand::constant(2.5, 1), 0))->toBe('float')
            ->and(Matcher::inferOperandType($body, Operand::constant(true, 2), 0))->toBe('bool')
            ->and(Matcher::inferOperandType($body, Operand::constant('value', 3), 0))->toBe('string')
            ->and(Matcher::inferOperandType($body, Operand::raw(1, 0), 0))->toBeNull()
            ->and(Matcher::inferOperandType($body, $cv0, 2))->toBe('int')
            ->and(Matcher::inferOperandType($body, $cv1, 3))->toBe('float');

        expect(Matcher::inferOperandType($body, Operand::constant(false, 4), 0))->toBe('bool');
    });

    it('infers result types from common arithmetic bytecode operations', function (): void {
        $temporary = Operand::temporary(0);
        $body = new MethodBody('operations', null, 0, 0, [
            new Instruction(0, 'CONCAT', $temporary, Operand::constant('a', 0), Operand::constant('b', 1)),
            new Instruction(0, 'DIV', $temporary, Operand::constant(4, 2), Operand::constant(2, 3)),
            new Instruction(0, 'SL', $temporary, Operand::constant(4, 4), Operand::constant(1, 5)),
            new Instruction(0, 'BOOL', $temporary, Operand::constant(1, 6), Operand::unused()),
            new Instruction(0, 'MUL', $temporary, Operand::constant(4, 7), Operand::constant(2, 8)),
        ]);

        expect(Matcher::inferOperandType($body, $temporary, 1))->toBe('string')
            ->and(Matcher::inferOperandType($body, $temporary, 2))->toBe('float')
            ->and(Matcher::inferOperandType($body, $temporary, 3))->toBe('int')
            ->and(Matcher::inferOperandType($body, $temporary, 4))->toBe('bool')
            ->and(Matcher::inferOperandType($body, $temporary, 5))->toBe('int');

        $floatArithmetic = new MethodBody('float-operation', null, 0, 0, [
            new Instruction(0, 'MUL', $temporary, Operand::constant(1.0, 0), Operand::constant(2, 1)),
        ]);
        expect(Matcher::inferOperandType($floatArithmetic, $temporary, 1))->toBe('float');
    });

    it('matches declared and inferred local types conservatively', function (): void {
        $call = static function (string $name, mixed ...$arguments): mixed {
            $method = new ReflectionMethod(Matcher::class, $name);

            return $method->invoke(null, ...$arguments);
        };
        $unused = Operand::unused();
        $cv0 = Operand::cv(0);
        $typed = new MethodBody(
            'typed-locals',
            null,
            0,
            0,
            [new Instruction(0, 'RECV', $cv0, $unused, $unused)],
            variableTypes: [0 => 'int|float'],
        );

        expect($call('isArgumentVariable', $typed, $cv0))->toBeTrue()
            ->and($call('isArgumentVariable', $typed, Operand::cv(1)))->toBeFalse()
            ->and($call('matchesVariableType', $typed, $cv0, 'int|float'))->toBeTrue()
            ->and($call('matchesVariableType', $typed, $cv0, 'string'))->toBeFalse()
            ->and($call('matchesVariableType', $typed, $cv0, 'mixed'))->toBeTrue()
            ->and($call('matchesVariableTypeName', 'stdClass', 'object'))->toBeTrue()
            ->and($call('matchesVariableTypeName', 'int|string', 'int'))->toBeFalse()
            ->and($call('matchesVariableTypeName', 'ArrayObject', 'ArrayObject'))->toBeTrue()
            ->and($call('matchesValueType', $typed, Operand::constant(1, 0), 'int'))->toBeTrue()
            ->and($call('matchesValueType', $typed, $cv0, 'int|float'))->toBeTrue()
            ->and(Matcher::inferOperandType($typed, $cv0, 1))->toBe('int|float');

        $sameAssignments = new MethodBody('same-assignments', null, 0, 0, [
            new Instruction(0, 'ASSIGN', $unused, $cv0, Operand::constant(1, 0)),
            new Instruction(0, 'ASSIGN', $unused, $cv0, Operand::constant(2, 1)),
        ]);
        expect(Matcher::inferOperandType($sameAssignments, $cv0, 0))->toBe('int');
        expect($call('matchesVariableTypeAt', $sameAssignments, $cv0, 'int', 1))->toBeTrue()
            ->and($call('matchesVariableTypeAt', $sameAssignments, $cv0, 'string', 1))->toBeFalse();

        $conflictingAssignments = new MethodBody('conflicting-assignments', null, 0, 0, [
            new Instruction(0, 'ASSIGN', $unused, $cv0, Operand::constant(1, 0)),
            new Instruction(0, 'ASSIGN', $unused, $cv0, Operand::constant('value', 1)),
        ]);
        expect(Matcher::inferOperandType($conflictingAssignments, $cv0, 0))->toBeNull();
    });

    it('infers return types from function and method invocation bytecode', function (): void {
        $temporary = Operand::temporary(0);
        $unused = Operand::unused();
        $call = static function (string $name, mixed ...$arguments): mixed {
            $method = new ReflectionMethod(Matcher::class, $name);

            return $method->invoke(null, ...$arguments);
        };

        $functionCall = new MethodBody('function-call', null, 0, 0, [
            new Instruction(0, 'INIT_FCALL', $unused, Operand::constant('strlen', 0), $unused),
            new Instruction(0, 'DO_FCALL', $temporary, $unused, $unused),
        ]);
        $staticCall = new MethodBody('static-call', null, 0, 0, [
            new Instruction(0, 'INIT_STATIC_METHOD_CALL', $unused, Operand::constant(MatcherReturnTypeFixture::class, 0), Operand::constant('returnsInt', 1)),
            new Instruction(0, 'DO_STATIC_METHOD_CALL', $temporary, $unused, $unused),
        ]);
        $memberCall = new MethodBody(MatcherReturnTypeFixture::class . '::returnsString', null, 0, 0, [
            new Instruction(0, 'INIT_METHOD_CALL', $unused, $unused, Operand::constant('returnsString', 0)),
            new Instruction(0, 'DO_METHOD_CALL', $temporary, $unused, $unused),
        ]);
        $unknownCall = new MethodBody('unknown-call', null, 0, 0, [
            new Instruction(0, 'INIT_FCALL', $unused, Operand::constant('missingMatcherFunction', 0), $unused),
            new Instruction(0, 'DO_FCALL', $temporary, $unused, $unused),
        ]);

        expect(Matcher::inferOperandType($functionCall, $temporary, 2))->toBe('int')
            ->and(Matcher::inferOperandType($staticCall, $temporary, 2))->toBe('int')
            ->and(Matcher::inferOperandType($memberCall, $temporary, 2))->toBe('string')
            ->and(Matcher::inferOperandType($unknownCall, $temporary, 2))->toBeNull()
            ->and($call('matchesInvocationSignature', $functionCall, InvocationSpec::parse(['strlen', ['signature' => ['parameters' => ['string'], 'return' => 'int']]]), 'strlen', null))->toBeTrue()
            ->and($call('matchesInvocationSignature', $staticCall, InvocationSpec::parse([MatcherReturnTypeFixture::class . '::returnsInt', ['signature' => ['parameters' => [], 'return' => 'int']]]), 'returnsInt', 'returnsInt'))->toBeTrue()
            ->and($call('matchesInvocationSignature', $memberCall, InvocationSpec::parse(['->returnsString', ['signature' => ['parameters' => [], 'return' => 'string']]]), 'returnsString', 'returnsString'))->toBeTrue()
            ->and($call('matchesInvocationSignature', $functionCall, InvocationSpec::parse(['strlen', ['signature' => ['parameters' => [], 'return' => 'void']]]), null, null))->toBeFalse();

        $namedFunctionCall = new MethodBody('named-function-call', null, 0, 0, [
            new Instruction(0, 'INIT_FCALL', $unused, $unused, Operand::constant('strlen', 0)),
            new Instruction(0, 'DO_FCALL', $temporary, $unused, $unused),
        ]);
        expect(Matcher::inferOperandType($namedFunctionCall, $temporary, 2))->toBe('int')
            ->and($call('inferredInvocationReturnType', new MethodBody('unknown', null, 0, 0, [new Instruction(0, 'INIT_FCALL', $unused, $unused, Operand::constant('missingMatcherFunction', 0))]), 1))->toBeNull()
            ->and($call('inferredInstructionType', new MethodBody('unknown', null, 0, 0, []), new Instruction(0, 'NOP', $unused, $unused, $unused), 0))->toBeNull()
            ->and($call('matchesInvocationSignature', $functionCall, InvocationSpec::parse(['strlen', ['signature' => ['parameters' => [], 'return' => 'int']]]), 'missingMatcherFunction', null))->toBeFalse();

        expect($call('inferredInvocationReturnType', new MethodBody('no-invocation', null, 0, 0, [
            new Instruction(0, 'NOP', $unused, $unused, $unused),
        ]), 1))->toBeNull()
            ->and($call('inferredInvocationReturnType', new MethodBody('dynamic-invocation', null, 0, 0, [
                new Instruction(0, 'INIT_DYNAMIC_CALL', $unused, $unused, $unused),
            ]), 1))->toBeNull();
    });
});
