<?php

declare(strict_types=1);

use Communism\Internals\Needle\Injector;
use Communism\Internals\Needle\CaptureException;
use Communism\Internals\Needle\Instruction;
use Communism\Internals\Needle\MatchResult;
use Communism\Internals\Needle\Matcher;
use Communism\Internals\Needle\InvocationSpec;
use Communism\Internals\Needle\MethodBody;
use Communism\Internals\Needle\Operand;
use Communism\Mixin\CallbackInfo;
use Communism\Mixin\CallbackInfoReturnable;
use Communism\Mixin\Coerce;
use Communism\Mixin\At;
use Communism\Mixin\Args;
use Communism\Mixin\Inject;
use Communism\Mixin\LocalCapture;

function injectorHelperBool(bool $value): bool
{
    return $value;
}

function injectorHelperFloat(float $value): float
{
    return $value;
}

function injectorHelperInt(int $value): int
{
    return $value;
}

function injectorHelperString(string $value): string
{
    return $value;
}

function injectorHelperNullable(bool $value = false): ?string
{
    return $value ? 'value' : null;
}

function injectorHelperObject(): stdClass
{
    return new stdClass();
}

function injectorHelperCallback(CallbackInfo $info): void {}

function injectorHelperCallbackWithLocal(CallbackInfo $info, string $value): void {}

function injectorHelperCallbackWithOtherLocal(CallbackInfo $info, string $other): void {}

/** @param CallbackInfoReturnable<mixed> $info */
function injectorHelperCallbackWithReturnable(CallbackInfoReturnable $info): void {}

function injectorArgsHelper(Args $args): void {}

/** @param CallbackInfoReturnable<mixed> $info */
function injectorHelperReturnable(CallbackInfoReturnable $info): void {}

function injectorNullableParameter(?int $value): void {}

function injectorIntParameter(int $value): void {}

function injectorCoercedFloatParameter(float $value): void {}

function injectorUntypedParameter(mixed $value): void {}

function injectorOtherNamedParameter(string $other): void {}

function injectorNullableReturn(bool $value = false): ?string
{
    return $value ? 'value' : null;
}

function injectorIntegerArgument(int $value): int
{
    return $value;
}

function injectorNullableIntegerReturn(int $value): ?int
{
    return $value === 0 ? null : $value;
}

function injectorStringReturn(int $value): string
{
    return (string) $value;
}

/** @return mixed */
function injectorNoReturnType(mixed $value)
{
    return $value;
}

it('covers Injector type compatibility and reflection helpers', function (): void {
    $call = static function (string $name, mixed ...$arguments): mixed {
        $method = new ReflectionMethod(Injector::class, $name);
        $method->setAccessible(true);

        return $method->invoke(null, ...$arguments);
    };

    expect($call('typeAccepts', 'mixed', 'int'))->toBeTrue()
        ->and($call('typeAccepts', 'object', 'stdClass'))->toBeTrue()
        ->and($call('typeAccepts', 'object', 'int'))->toBeFalse()
        ->and($call('typeAccepts', 'iterable', 'array'))->toBeTrue()
        ->and($call('typeAccepts', 'iterable', 'Iterator'))->toBeTrue()
        ->and($call('typeAccepts', 'int', 'string'))->toBeFalse()
        ->and($call('typeAccepts', 'stdClass', 'stdClass'))->toBeTrue()
        ->and($call('typeAccepts', ParentClassForInjectorCoverage::class, ChildClassForInjectorCoverage::class))->toBeTrue()
        ->and($call('canCoerce', 'int', 'float'))->toBeTrue()
        ->and($call('canCoerce', 'array', 'string'))->toBeFalse()
        ->and($call('canCoerce', 'ChildClassForInjectorCoverage', 'ParentClassForInjectorCoverage'))->toBeTrue();

    expect($call('reflection', 'injectorHelperString'))->toBeInstanceOf(ReflectionFunction::class)
        ->and($call('reflection', InjectorHelperCoverageTarget::class . '::method'))
        ->toBeInstanceOf(ReflectionMethod::class)
        ->and($call('callbackParameterType', (new ReflectionFunction('injectorHelperCallback'))->getParameters()[0]))
        ->toBe(CallbackInfo::class)
        ->and($call('callbackParameterType', (new ReflectionFunction('injectorHelperReturnable'))->getParameters()[0]))
        ->toBe(CallbackInfoReturnable::class)
        ->and($call('callbackParameterType', (new ReflectionFunction('injectorHelperString'))->getParameters()[0]))
        ->toBeNull();
});

it('returns default values and handles helper metadata without bytecode', function (): void {
    $call = static function (string $name, mixed ...$arguments): mixed {
        $method = new ReflectionMethod(Injector::class, $name);
        $method->setAccessible(true);

        return $method->invoke(null, ...$arguments);
    };

    expect($call('defaultReturnValue', new ReflectionFunction('injectorHelperBool')))->toBeFalse()
        ->and($call('defaultReturnValue', new ReflectionFunction('injectorHelperFloat')))->toBe(0.0)
        ->and($call('defaultReturnValue', new ReflectionFunction('injectorHelperInt')))->toBe(0)
        ->and($call('defaultReturnValue', new ReflectionFunction('injectorHelperString')))->toBe('')
        ->and($call('defaultReturnValue', new ReflectionFunction('injectorHelperNullable')))->toBeNull()
        ->and($call('defaultReturnValue', new ReflectionFunction('injectorHelperObject')))->toBeNull()
        ->and($call('cacheBase', new MethodBody('empty', null, 0, 0, [])))->toBe(0)
        ->and($call('cacheBase', new MethodBody('empty', null, 0, 0, [new Instruction(0, 'INIT_FCALL', Operand::constant('name', 0), Operand::unused(), Operand::unused())])))->toBe(0)
        ->and($call('returnInstruction', new MethodBody('injectorHelperString', null, 0, 0, []), 0, 0))
        ->toBeNull()
        ->and($call(
            'callbackReturn',
            new MethodBody('empty', null, 0, 0, []),
            ['returnOperand' => Operand::constant('value', 0)],
            1,
            0,
        ))->toBeInstanceOf(Instruction::class)
        ->and($call(
            'callbackReturnWithValue',
            new MethodBody('empty', null, 0, 0, []),
            Operand::constant('value', 0),
            1,
            0,
        ))->toBeInstanceOf(Instruction::class)
        ->and($call('isCallStart', 'INIT_METHOD_CALL'))->toBeTrue()
        ->and($call('isCallStart', 'RETURN'))->toBeFalse()
        ->and($call('operandKey', Operand::constant('value', 0)))->toBeString()
        ->and($call('requireMatch', new MethodBody('injectorHelperString', null, 0, 0, []), new Inject('injectorHelperString', new At('HEAD'))))
        ->toBeArray();

    $localHandler = new MethodBody(
        'injectorIntegerArgument',
        null,
        0,
        0,
        [new Instruction(0, 'RETURN', Operand::cv(1), Operand::unused(), Operand::unused())],
        [1 => 'unmapped'],
    );
    expect($call('mapHandlerLocals', new MethodBody('empty', null, 0, 0, [], [1 => 'unmapped']), $localHandler, [], [], 0))
        ->toEqual([[1 => Operand::cv(1)], 0])
        ->and($call('mapHandlerLocals', new MethodBody('empty', null, 0, 0, []), $localHandler, [], [], 0))
        ->toEqual([[1 => Operand::temporary(80)], 1]);

    $instruction = new Instruction(0, 'RETURN', Operand::unused(), Operand::unused(), Operand::unused());
    $reflectionTarget = new MethodBody('injectorIntegerArgument', null, 0, 0, []);
    $detach = static function (
        Operand $operand,
        int $temporaryOffset,
        int $cacheOffset,
        bool $cacheOperand,
        array $cvMap = [],
        array $callbackCvs = [],
        array $preserve = [],
        int $sourceCacheBase = 0,
    ) use ($call): Operand {
        $result = $call('detachOperand', $operand, $temporaryOffset, $cacheOffset, $cacheOperand, $cvMap, $callbackCvs, $preserve, $sourceCacheBase);
        if (!$result instanceof Operand) {
            throw new RuntimeException('detachOperand returned an invalid value');
        }

        return $result;
    };
    expect($call('sendOperands', [$instruction], []))->toBe([])
        ->and($call('directSendIndices', [], 0, -1))->toBe([])
        ->and($call('argumentIndex', null, []))->toBeNull()
        ->and($call('argumentIndex', Operand::constant(9, 0), [Operand::constant('value', 0)]))->toBeNull()
        ->and($call('replaceArgsOperands', $instruction, []))->toEqual($instruction)
        ->and($call('withoutSends', [$instruction], []))->toHaveCount(1)
        ->and($call('lastSendOperand', []))->toBeNull()
        ->and($call('withoutLastSend', [$instruction]))->toHaveCount(1)
        ->and($call('callbackCallEnd', [], 0))->toBeNull()
        ->and($call('relocateJumps', [], 0))->toBe([])
        ->and($call('invocationReflection', $reflectionTarget, InvocationSpec::parse('MissingInjectorClass::missing')))
        ->toBeNull()
        ->and(static fn() => $call(
            'prepareArgsHandler',
            $reflectionTarget,
            new MethodBody('injectorArgsHelper', null, 0, 0, [new Instruction(0, 'RECV', Operand::unused(), Operand::unused(), Operand::unused())]),
            new MatchResult(0, 1, 'invoke'),
            0,
            0,
            0,
        ))->toThrow(InvalidArgumentException::class)
        ->and($detach(Operand::unused(20), 1, 2, true, [], [], [], 10)->value)->toBe(12)
        ->and($detach(Operand::raw(4, 20), 1, 2, true, [], [], [], 10)->value)->toBe(12)
        ->and($detach(Operand::constant('value', 0), 1, 2, false)->kind)->toBe(Operand::CONSTANT)
        ->and($detach(Operand::cv(1), 1, 2, false, [1 => Operand::temporary(16)])->kind)->toBe(Operand::TEMPORARY)
        ->and(static fn() => $detach(Operand::cv(1), 1, 2, false, [], [1], [], 0))
        ->toThrow(InvalidArgumentException::class)
        ->and($detach(Operand::temporary(16), 2, 0, false)->value)->toBeInt()
        ->and($detach(Operand::variable(16), 2, 0, false)->value)->toBeInt();

    $jump = new Instruction(0, 'JMP', Operand::unused(32), Operand::unused(), Operand::unused(), originalIndex: 0);
    $targetInstruction = new Instruction(0, 'RETURN', Operand::unused(), Operand::unused(), Operand::unused(), originalIndex: 1);
    expect($call('relocateJumps', [$jump, $targetInstruction], 2))->toHaveCount(2)
        ->and($call('relocateJumps', [new Instruction(0, 'JMP', Operand::raw(4, 32), Operand::unused(), Operand::unused(), originalIndex: 0), $targetInstruction], 2))
        ->toHaveCount(2)
        ->and($call('relocateJumps', [new Instruction(0, 'JMP', Operand::unused(31), Operand::unused(), Operand::unused(), originalIndex: 0)], 1))
        ->toHaveCount(1)
        ->and($call('relocateJumps', [new Instruction(0, 'JMP', Operand::unused(64), Operand::unused(), Operand::unused(), originalIndex: 0)], 1))
        ->toHaveCount(1);

    expect($call(
        'callbackContext',
        new MethodBody('injectorHelperString', null, 0, 0, []),
        new MethodBody('injectorHelperCallback', null, 0, 0, []),
        new Inject('injectorHelperString', new At('HEAD')),
        new MatchResult(0, 0, 'head'),
    ))->toBeNull();

    $targetInstruction = new Instruction(
        0,
        'FIELD',
        Operand::unused(),
        Operand::cv(1),
        Operand::constant('key', 0),
        originalIndex: 0,
    );
    $receive = new Instruction(0, 'RECV', Operand::cv(0), Operand::unused(), Operand::unused());
    $noLiteralInstruction = new Instruction(0, 'RETURN', Operand::unused(), Operand::cv(1), Operand::unused());
    $noVariableInstruction = new Instruction(0, 'RETURN', Operand::unused(), Operand::unused(), Operand::unused());
    $arrayMatch = new MatchResult(0, 1, 'field', 'replace', fieldMode: 'array-read');

    expect($call('modifierInputMap', new MethodBody('empty', null, 0, 0, [$targetInstruction]), new MethodBody('empty', null, 0, 0, []), new MatchResult(0, 1, 'field', fieldMode: 'read')))
        ->toBe([])
        ->and($call('modifierInputMap', new MethodBody('empty', null, 0, 0, [$targetInstruction]), new MethodBody('empty', null, 0, 0, []), new MatchResult(0, 1, 'constant', 'arg')))
        ->toBe([])
        ->and($call('modifierInputMap', new MethodBody('empty', null, 0, 0, [$targetInstruction]), new MethodBody('empty', null, 0, 0, []), $arrayMatch))
        ->toBe([])
        ->and(static fn() => $call('modifierInputMap', new MethodBody('empty', null, 0, 0, [$noLiteralInstruction]), new MethodBody('empty', null, 0, 0, [$receive]), new MatchResult(0, 1, 'constant')))
        ->toThrow(InvalidArgumentException::class)
        ->and(static fn() => $call('modifierInputMap', new MethodBody('empty', null, 0, 0, [$targetInstruction]), new MethodBody('empty', null, 0, 0, [$receive]), new MatchResult(0, 1, 'invoke', 'arg')))
        ->toThrow(InvalidArgumentException::class)
        ->and(static fn() => $call('modifierInputMap', new MethodBody('empty', null, 0, 0, [$targetInstruction, $noLiteralInstruction]), new MethodBody('empty', null, 0, 0, [$receive]), new MatchResult(0, 2, 'field', fieldMode: 'array-write')))
        ->toThrow(InvalidArgumentException::class)
        ->and(static fn() => $call('modifierInputMap', new MethodBody('empty', null, 0, 0, [$targetInstruction, $noLiteralInstruction]), new MethodBody('empty', null, 0, 0, [$receive, $receive, $receive]), $arrayMatch))
        ->toThrow(InvalidArgumentException::class)
        ->and(static fn() => $call('modifierInputMap', new MethodBody('empty', null, 0, 0, [$targetInstruction, $noLiteralInstruction]), new MethodBody('empty', null, 0, 0, [new Instruction(0, 'RECV', Operand::unused(), Operand::unused(), Operand::unused())]), $arrayMatch))
        ->toThrow(InvalidArgumentException::class)
        ->and(static fn() => $call('modifierInputMap', new MethodBody('empty', null, 0, 0, [$targetInstruction, $noLiteralInstruction]), new MethodBody('empty', null, 0, 0, [$receive]), new MatchResult(0, 2, 'field', fieldMode: 'write')))
        ->toThrow(InvalidArgumentException::class)
        ->and(static fn() => $call('modifierInputMap', new MethodBody('empty', null, 0, 0, [$noLiteralInstruction]), new MethodBody('empty', null, 0, 0, [$receive]), new MatchResult(0, 1, 'variable')))
        ->toThrow(InvalidArgumentException::class)
        ->and(static fn() => $call('modifierInputMap', new MethodBody('empty', null, 0, 0, [$noVariableInstruction]), new MethodBody('empty', null, 0, 0, [$receive]), new MatchResult(0, 1, 'variable', variableMode: 'load')))
        ->toThrow(InvalidArgumentException::class)
        ->and(static fn() => $call('modifierInputMap', new MethodBody('empty', null, 0, 0, [new Instruction(0, 'ASSIGN', Operand::unused(), Operand::unused(), Operand::unused())]), new MethodBody('empty', null, 0, 0, [$receive]), new MatchResult(0, 1, 'variable', variableMode: 'load')))
        ->toThrow(InvalidArgumentException::class)
        ->and(static fn() => $call('modifierInputMap', new MethodBody('empty', null, 0, 0, [$noLiteralInstruction]), new MethodBody('empty', null, 0, 0, [$receive]), new MatchResult(0, 1, 'constant')))
        ->toThrow(InvalidArgumentException::class);
});

it('validates callback parameter compatibility and coercion rules', function (): void {
    $method = new ReflectionMethod(Injector::class, 'validateCallbackParameterType');
    $method->setAccessible(true);
    $parameter = static function (string $function): ReflectionParameter {
        return (new ReflectionFunction($function))->getParameters()[0];
    };

    $nullable = $parameter('injectorNullableParameter');
    $integer = $parameter('injectorIntParameter');
    $float = $parameter('injectorCoercedFloatParameter');
    $untyped = $parameter('injectorUntypedParameter');
    $attribute = (new ReflectionMethod(InjectorCoercedCoverageTarget::class, 'method'))->getParameters()[0];

    expect($method->invoke(null, $integer, $integer, 'same', false))->toBeNull()
        ->and($method->invoke(null, $untyped, $integer, 'untyped', false))->toBeNull()
        ->and($method->invoke(null, $float, $integer, 'coerced', true))->toBeNull()
        ->and($method->invoke(null, $attribute, $integer, 'attribute', false))->toBeNull()
        ->and($method->invoke(null, (new ReflectionFunction('array_key_exists'))->getParameters()[0], $integer, 'union-type', false))->toBeNull()
        ->and(static fn() => $method->invoke(null, $integer, $nullable, 'nullable', false))
        ->toThrow(CaptureException::class)
        ->and(static fn() => $method->invoke(null, $float, $integer, 'incompatible', false))
        ->toThrow(CaptureException::class);
});

it('covers callback local mapping edge cases', function (): void {
    $call = static function (string $name, mixed ...$arguments): mixed {
        $method = new ReflectionMethod(Injector::class, $name);
        $method->setAccessible(true);

        return $method->invoke(null, ...$arguments);
    };
    $unused = Operand::unused();
    $handlerInstructions = [
        new Instruction(0, 'RECV', Operand::cv(0), $unused, $unused),
        new Instruction(0, 'RECV', Operand::cv(1), $unused, $unused),
    ];
    $handler = new MethodBody('injectorHelperCallbackWithOtherLocal', null, 0, 0, $handlerInstructions);
    $target = new MethodBody(
        'injectorHelperString',
        null,
        0,
        0,
        [new Instruction(0, 'RECV', Operand::cv(0), $unused, $unused)],
        [0 => 'value'],
    );
    $inject = new Inject('injectorHelperString', new At('HEAD'));
    $context = $call('callbackContext', $target, $handler, $inject, new MatchResult(0, 0, 'head'));
    expect($context)->toBeArray();
    if (!is_array($context) || !is_array($context['cvMap'] ?? null)) {
        throw new RuntimeException('Expected callback context map');
    }
    expect($context['cvMap'][1])->toEqual(Operand::cv(0));

    $targetWithMismatchedName = new MethodBody(
        'injectorOtherNamedParameter',
        null,
        0,
        0,
        [new Instruction(0, 'RECV', Operand::cv(0), $unused, $unused)],
        [0 => 'value'],
    );
    expect(static fn() => $call('callbackContext', $targetWithMismatchedName, new MethodBody('injectorHelperCallbackWithLocal', null, 0, 0, $handlerInstructions), new Inject('injectorOtherNamedParameter', new At('HEAD'), locals: LocalCapture::NO_CAPTURE), new MatchResult(0, 0, 'head')))
        ->toThrow(CaptureException::class);

    $targetWithSyntheticReceive = new MethodBody(
        'injectorHelperObject',
        null,
        0,
        0,
        [new Instruction(0, 'RECV', Operand::cv(7), $unused, $unused)],
    );
    $syntheticContext = $call('callbackContext', $targetWithSyntheticReceive, $handler, $inject, new MatchResult(0, 0, 'head'));
    expect($syntheticContext)->toBeArray();
    if (!is_array($syntheticContext) || !is_array($syntheticContext['cvMap'] ?? null)) {
        throw new RuntimeException('Expected synthetic callback context map');
    }
    expect($syntheticContext['cvMap'][1])->toEqual(Operand::cv(7));
});

it('covers virtual CallbackInfo lowering guards', function (): void {
    $call = static function (string $name, mixed ...$arguments): mixed {
        $method = new ReflectionMethod(Injector::class, $name);
        $method->setAccessible(true);

        return $method->invoke(null, ...$arguments);
    };
    $unused = Operand::unused();
    $target = new MethodBody('injectorHelperString', null, 0, 0, []);
    $context = [
        'callbackCvs' => [0],
        'callbackReturnable' => false,
        'cancellable' => false,
        'cvMap' => [],
        'returnOperand' => Operand::constant('return', 0),
        'preserveReturnOperand' => false,
    ];
    $virtual = static function (string $method, ?Instruction $send = null, ?Operand $result = null) use ($unused): MethodBody {
        $instructions = [new Instruction(0, 'INIT_METHOD_CALL', $unused, Operand::cv(0), Operand::constant($method, 0))];
        if ($send instanceof Instruction) {
            $instructions[] = $send;
        }
        $instructions[] = new Instruction(0, 'DO_FCALL', $result ?? $unused, $unused, $unused);

        return new MethodBody('injectorHelperCallback', null, 0, 0, $instructions);
    };

    expect(static fn() => $call('lowerCallback', $target, $virtual('cancel'), $context))
        ->toThrow(InvalidArgumentException::class)
        ->and(static fn() => $call('lowerCallback', $target, $virtual('setReturnValue'), $context))
        ->toThrow(InvalidArgumentException::class);

    $returnable = [...$context, 'callbackReturnable' => true];
    $cancellableReturnable = [...$returnable, 'cancellable' => true];
    expect(static fn() => $call('lowerCallback', $target, $virtual('setReturnValue'), [...$returnable, 'cancellable' => false]))
        ->toThrow(InvalidArgumentException::class)
        ->and(static fn() => $call('lowerCallback', $target, $virtual('setReturnValue'), $returnable))
        ->toThrow(InvalidArgumentException::class)
        ->and(static fn() => $call('lowerCallback', $target, $virtual('setReturnValue'), $cancellableReturnable))
        ->toThrow(InvalidArgumentException::class)
        ->and(static fn() => $call('lowerCallback', $target, $virtual('getReturnValue'), $context))
        ->toThrow(InvalidArgumentException::class)
        ->and(static fn() => $call('lowerCallback', $target, $virtual('unsupported'), $returnable))
        ->toThrow(InvalidArgumentException::class)
        ->and(static fn() => $call('lowerCallback', $target, new MethodBody('injectorHelperCallback', null, 0, 0, [new Instruction(0, 'INIT_METHOD_CALL', $unused, Operand::cv(0), Operand::constant('cancel', 0))]), [...$returnable, 'cancellable' => true]))
        ->not->toThrow(Throwable::class);

    $send = new Instruction(0, 'SEND_VAL', $unused, Operand::constant(7, 0), $unused);
    expect($call('lowerCallback', $target, $virtual('setReturnValue', $send), $cancellableReturnable))
        ->toBeArray()
        ->and($call('lowerCallback', $target, $virtual('getId', null, Operand::cv(2)), $cancellableReturnable))
        ->toBeArray()
        ->and($call('lowerCallback', $target, $virtual('isCancelled', null, Operand::cv(2)), $cancellableReturnable))
        ->toBeArray()
        ->and($call('lowerCallback', $target, $virtual('isCancellable', null, Operand::cv(2)), $cancellableReturnable))
        ->toBeArray();
});

it('covers grouped placements and surrogate capture failures', function (): void {
    $unused = Operand::unused();
    $body = new MethodBody(
        'injectorHelperString',
        null,
        0,
        0,
        [new Instruction(0, 'RETURN', $unused, Operand::constant('value', 0), $unused)],
    );
    $handler = new MethodBody(
        'injectorHelperString',
        null,
        0,
        0,
        [
            new Instruction(0, 'NOP', $unused, $unused, $unused),
            new Instruction(0, 'RETURN', $unused, Operand::constant('replacement', 0), $unused),
        ],
    );
    $inject = new Inject('injectorHelperString', new At('HEAD'));
    expect(Injector::inject($body, [$inject, $inject], static fn() => $handler)->instructions())
        ->toHaveCount(3);

    $overlapBody = new MethodBody(
        'injectorHelperString',
        null,
        0,
        0,
        [
            new Instruction(0, 'NOP', $unused, $unused, $unused),
            new Instruction(0, 'RETURN', $unused, Operand::constant('value', 0), $unused),
        ],
    );
    Matcher::registerInjectionPoint('_OVERLAP_A', static fn(MethodBody $body, At $at): array => [new MatchResult(0, 1, 'custom', 'before')]);
    Matcher::registerInjectionPoint('_OVERLAP_B', static fn(MethodBody $body, At $at): array => [new MatchResult(0, 2, 'custom', 'before')]);
    try {
        expect(static fn() => Injector::inject(
            $overlapBody,
            [new Inject('injectorHelperString', new At('_OVERLAP_A')), new Inject('injectorHelperString', new At('_OVERLAP_B'))],
            static fn() => $handler,
        ))->toThrow(InvalidArgumentException::class);
    } finally {
        Matcher::unregisterInjectionPoint('_OVERLAP_A');
        Matcher::unregisterInjectionPoint('_OVERLAP_B');
    }

    $captureHandler = new MethodBody(
        'injectorHelperCallbackWithLocal',
        null,
        0,
        0,
        [
            new Instruction(0, 'RECV', Operand::cv(0), $unused, $unused),
            new Instruction(0, 'RECV', Operand::cv(1), $unused, $unused),
        ],
    );
    $captureInject = new Inject('injectorHelperString', new At('HEAD'), locals: LocalCapture::PRINT);
    expect(static fn() => Injector::inject(
        $body,
        [$captureInject],
        static fn() => $captureHandler,
        static fn() => $captureHandler,
    ))->toThrow(CaptureException::class);
});

it('validates invocation reflection and modifier return types', function (): void {
    $call = static function (string $name, mixed ...$arguments): mixed {
        $method = new ReflectionMethod(Injector::class, $name);
        $method->setAccessible(true);

        return $method->invoke(null, ...$arguments);
    };
    $target = new MethodBody('injectorIntegerArgument', null, 0, 0, []);
    $function = InvocationSpec::parse('injectorIntegerArgument');
    $member = InvocationSpec::parse('->method');
    $static = InvocationSpec::parse(InjectorHelperCoverageTarget::class . '::method');

    expect($call('invocationReflection', $target, $function))->toBeInstanceOf(ReflectionFunction::class)
        ->and($call('invocationReflection', $target, $static))->toBeInstanceOf(ReflectionMethod::class)
        ->and($call('invocationReflection', $target, $member))->toBeNull();

    expect($call('validateRedirectParameterTypes', $target, new MethodBody('injectorIntegerArgument', null, 0, 0, []), new MatchResult(0, 0, 'invoke'), 0))
        ->toBeNull()
        ->and($call('validateModifyArgTypes', $target, new MethodBody('injectorIntegerArgument', null, 0, 0, []), new MatchResult(0, 1, 'invoke', 'arg', $member, 0)))
        ->toBeNull()
        ->and($call('validateModifyArgTypes', $target, new MethodBody('injectorIntegerArgument', null, 0, 0, []), new MatchResult(0, 1, 'invoke', 'arg', $function, 99)))
        ->toBeNull()
        ->and($call('validateRedirectReturnType', new MethodBody('injectorNoReturnType', null, 0, 0, []), new MethodBody('injectorHelperString', null, 0, 0, []), new MatchResult(0, 0, 'invoke', 'replace', InvocationSpec::parse('injectorNoReturnType'))))
        ->toBeNull();

    $returnTarget = new MethodBody('injectorNullableReturn', null, 0, 0, []);
    $returnMatch = new MatchResult(0, 0, 'invoke', 'replace', InvocationSpec::parse('injectorNullableReturn'));
    expect($call(
        'validateRedirectReturnType',
        $returnTarget,
        new MethodBody('injectorNullableReturn', null, 0, 0, []),
        $returnMatch,
    ))->toBeNull()
        ->and(static fn() => $call(
            'validateRedirectReturnType',
            $returnTarget,
            new MethodBody('injectorHelperString', null, 0, 0, []),
            $returnMatch,
        ))->toThrow(InvalidArgumentException::class);

    $argumentMatch = new MatchResult(0, 1, 'invoke', 'arg', $function, 0);
    expect($call(
        'validateModifyArgTypes',
        $target,
        new MethodBody('injectorIntegerArgument', null, 0, 0, []),
        $argumentMatch,
    ))->toBeNull()
        ->and(static fn() => $call(
            'validateModifyArgTypes',
            $target,
            new MethodBody('injectorNullableIntegerReturn', null, 0, 0, []),
            $argumentMatch,
        ))->toThrow(InvalidArgumentException::class)
        ->and(static fn() => $call(
            'validateModifyArgTypes',
            $target,
            new MethodBody('injectorStringReturn', null, 0, 0, []),
            $argumentMatch,
        ))->toThrow(InvalidArgumentException::class);
});

it('covers Injector replacement shapes and failure guards', function (): void {
    $call = static function (string $name, mixed ...$arguments): mixed {
        $method = new ReflectionMethod(Injector::class, $name);
        $method->setAccessible(true);

        return $method->invoke(null, ...$arguments);
    };
    $value = Operand::constant('value', 0);
    $unused = Operand::unused();
    $return = new Instruction(0, 'RETURN', $unused, $value, $unused);
    $handler = new Instruction(0, 'RETURN', $unused, Operand::constant('replacement', 0), $unused);
    $body = new MethodBody('injectorHelperString', null, 0, 0, [$return]);

    expect($call('replacement', $body, new MatchResult(0, 1, 'return', 'before'), [$return], [], null))
        ->toEqual([$return])
        ->and($call('replacement', $body, new MatchResult(0, 1, 'return'), [$return], [$handler], null))
        ->toHaveCount(2)
        ->and($call('replacement', $body, new MatchResult(0, 1, 'return'), [$return], [], $handler))
        ->toHaveCount(1);

    $invoke = [
        new Instruction(0, 'INIT_FCALL', $unused, $unused, $unused),
        new Instruction(0, 'SEND_VAL', $unused, $value, $unused),
        new Instruction(0, 'DO_FCALL', $unused, $unused, $unused),
    ];
    $invokeBody = new MethodBody('injectorHelperString', null, 0, 0, $invoke);
    $invokeMatch = new MatchResult(0, 3, 'invoke', 'replace');
    expect($call('replacement', $invokeBody, $invokeMatch, $invoke, [], $handler, [0 => Operand::constant('argument', 0)]))
        ->toHaveCount(3)
        ->and(static fn() => $call('replacement', $invokeBody, $invokeMatch, $invoke, [], null))
        ->toThrow(InvalidArgumentException::class)
        ->and(static fn() => $call('replacement', $invokeBody, new MatchResult(0, 3, 'invoke', 'arg', argumentIndex: 0), $invoke, [], null))
        ->toThrow(InvalidArgumentException::class);

    $new = [new Instruction(0, 'NEW', $unused, $value, $unused)];
    expect($call('replacement', $body, new MatchResult(0, 1, 'new'), $new, [], $handler))->toHaveCount(1)
        ->and(static fn() => $call('replacement', $body, new MatchResult(0, 1, 'new'), $new, [], null))
        ->toThrow(InvalidArgumentException::class);

    $fieldRead = [new Instruction(0, 'FETCH_OBJ_R', $unused, Operand::cv(0), $value)];
    expect(static fn() => $call('replacement', $body, new MatchResult(0, 1, 'field', fieldMode: 'read'), $fieldRead, [], null))
        ->toThrow(InvalidArgumentException::class)
        ->and(static fn() => $call('replacement', $body, new MatchResult(0, 0, 'field', fieldMode: 'read'), [], [$handler], $handler))
        ->toThrow(InvalidArgumentException::class)
        ->and($call('replacement', $body, new MatchResult(0, 1, 'field', fieldMode: 'write'), $fieldRead, [$handler], null))
        ->toEqual([$handler]);

    expect($call('replacement', $body, new MatchResult(0, 1, 'constant'), [$return], [], $handler))
        ->toHaveCount(1)
        ->and(static fn() => $call('replacement', $body, new MatchResult(0, 1, 'constant'), [], [], $handler))
        ->toThrow(InvalidArgumentException::class)
        ->and(static fn() => $call('replacement', $body, new MatchResult(0, 1, 'constant'), [$return], [], null))
        ->toThrow(InvalidArgumentException::class);

    $load = [new Instruction(0, 'FETCH_R', $unused, Operand::cv(0), $unused)];
    $assign = [new Instruction(0, 'ASSIGN', $unused, Operand::cv(0), Operand::cv(1))];
    expect($call('replacement', $body, new MatchResult(0, 1, 'variable', variableMode: 'load'), $load, [], $handler))
        ->toHaveCount(1)
        ->and(static fn() => $call('replacement', $body, new MatchResult(0, 1, 'variable', variableMode: 'load'), [$return], [], $handler))
        ->toThrow(InvalidArgumentException::class)
        ->and(static fn() => $call('replacement', $body, new MatchResult(0, 1, 'variable', variableMode: 'load'), $load, [], null))
        ->toThrow(InvalidArgumentException::class)
        ->and(static fn() => $call('replacement', $body, new MatchResult(0, 0, 'variable'), [], [$handler], $handler))
        ->toThrow(InvalidArgumentException::class)
        ->and($call('replacement', $body, new MatchResult(0, 1, 'variable'), $assign, [], $handler))
        ->toHaveCount(1)
        ->and(static fn() => $call('replacement', $body, new MatchResult(0, 1, 'variable'), [$return], [], $handler))
        ->toThrow(InvalidArgumentException::class);

    $invokeBody = new MethodBody('injectorIntegerArgument', null, 0, 0, [
        new Instruction(0, 'INIT_FCALL', $unused, $unused, $unused),
        new Instruction(0, 'NOP', $unused, $unused, $unused),
        new Instruction(0, 'SEND_VAL', $unused, Operand::constant(1, 0), $unused, originalIndex: 2),
        new Instruction(0, 'DO_FCALL', $unused, $unused, $unused),
    ]);
    $receive = new Instruction(0, 'RECV', Operand::cv(0), $unused, $unused);
    expect($call('modifierInputMap', $invokeBody, new MethodBody('injectorIntegerArgument', null, 0, 0, [$receive]), new MatchResult(0, 4, 'invoke', 'arg', argumentIndex: 0)))
        ->toEqual([0 => Operand::constant(1, 0)])
        ->and(static fn() => $call('modifierInputMap', $invokeBody, new MethodBody('injectorIntegerArgument', null, 0, 0, [new Instruction(0, 'RECV', $unused, $unused, $unused)]), new MatchResult(0, 4, 'invoke', 'replace')))
        ->toThrow(InvalidArgumentException::class);

    $assignBody = new MethodBody('injectorIntegerArgument', null, 0, 0, [new Instruction(0, 'ASSIGN', $unused, Operand::cv(1), Operand::constant(2, 0))]);
    expect($call('modifierInputMap', $assignBody, new MethodBody('injectorIntegerArgument', null, 0, 0, [$receive]), new MatchResult(0, 1, 'variable', variableMode: 'load')))
        ->toEqual([0 => Operand::cv(1)]);

    $fieldBody = new MethodBody('injectorIntegerArgument', null, 0, 0, [new Instruction(0, 'FETCH_DIM_R', $unused, Operand::cv(1), Operand::constant(0, 0))]);
    expect(static fn() => $call('modifierInputMap', $fieldBody, new MethodBody('injectorIntegerArgument', null, 0, 0, [new Instruction(0, 'RECV', $unused, $unused, $unused)]), new MatchResult(0, 1, 'field', fieldMode: 'array-read')))
        ->toThrow(InvalidArgumentException::class);
});

it('covers malformed virtual argument calls and redirect return validation', function (): void {
    $call = static function (string $name, mixed ...$arguments): mixed {
        $method = new ReflectionMethod(Injector::class, $name);
        $method->setAccessible(true);

        return $method->invoke(null, ...$arguments);
    };
    $unused = Operand::unused();
    $target = new MethodBody('injectorIntegerArgument', null, 0, 0, []);
    $handler = new MethodBody('injectorIntegerArgument', null, 0, 0, []);
    $invoke = static function (Operand $method, array $inner = []) use ($unused): MethodBody {
        /** @var list<Instruction> $instructions */
        $instructions = [
            new Instruction(0, 'INIT_METHOD_CALL', $unused, Operand::cv(0), $method),
            ...$inner,
            new Instruction(0, 'DO_METHOD_CALL', $unused, $unused, $unused),
        ];

        return new MethodBody('injectorIntegerArgument', null, 0, 0, $instructions);
    };
    $lower = static function (MethodBody $body) use ($call, $target): mixed {
        return $call('lowerArgs', $target, $body, 0, [Operand::constant('argument', 0)]);
    };

    $nested = new MethodBody('injectorIntegerArgument', null, 0, 0, [
        new Instruction(0, 'INIT_METHOD_CALL', $unused, Operand::cv(0), Operand::constant('count', 0)),
        new Instruction(0, 'INIT_METHOD_CALL', $unused, Operand::cv(0), Operand::constant('set', 0)),
        new Instruction(0, 'SEND_VAL', $unused, Operand::constant(0, 0), $unused, originalIndex: 2),
        new Instruction(0, 'SEND_VAL', $unused, Operand::constant(7, 0), $unused, originalIndex: 3),
        new Instruction(0, 'DO_METHOD_CALL', $unused, $unused, $unused),
        new Instruction(0, 'DO_METHOD_CALL', Operand::cv(1), $unused, $unused),
    ]);
    expect($lower($nested))->toBeArray();

    expect(static fn() => $lower(new MethodBody('injectorIntegerArgument', null, 0, 0, [new Instruction(0, 'INIT_METHOD_CALL', $unused, Operand::cv(0), Operand::constant('get', 0))])))
        ->toThrow(InvalidArgumentException::class)
        ->and(static fn() => $lower($invoke(Operand::constant('get', 0), [new Instruction(0, 'SEND_VAL', $unused, Operand::constant('other', 0), $unused)])))
        ->toThrow(InvalidArgumentException::class)
        ->and(static fn() => $lower($invoke(Operand::constant('setall', 0), [new Instruction(0, 'SEND_VAL', $unused, Operand::constant(['key' => 'value'], 0), $unused, originalIndex: 1)])))
        ->toThrow(InvalidArgumentException::class)
        ->and(static fn() => $lower($invoke(Operand::constant('setall', 0), [new Instruction(0, 'SEND_VAL', $unused, Operand::constant(['one', 'two'], 0), $unused, originalIndex: 1)])))
        ->toThrow(InvalidArgumentException::class)
        ->and(static fn() => $lower($invoke(Operand::constant('setall', 0), [new Instruction(0, 'SEND_VAL', $unused, Operand::constant([new stdClass()], 0), $unused, originalIndex: 1)])))
        ->toThrow(InvalidArgumentException::class)
        ->and(static fn() => $lower($invoke(Operand::constant('count', 0), [new Instruction(0, 'SEND_VAL', $unused, Operand::constant('unexpected', 0), $unused, originalIndex: 1)])))
        ->toThrow(InvalidArgumentException::class);

    expect(static fn() => $call('prepareArgsHandler', $target, $handler, new MatchResult(0, 0, 'return'), 0, 0, 0))
        ->toThrow(InvalidArgumentException::class)
        ->and(static fn() => $call('validateRedirectReturnType', $target, $handler, new MatchResult(0, 0, 'invoke')))
        ->not->toThrow(Throwable::class)
        ->and(static fn() => $call(
            'validateRedirectReturnType',
            new MethodBody('injectorNullableIntegerReturn', null, 0, 0, []),
            new MethodBody('injectorStringReturn', null, 0, 0, []),
            new MatchResult(0, 0, 'invoke', invocation: InvocationSpec::parse('injectorNullableIntegerReturn')),
        ))->toThrow(InvalidArgumentException::class)
        ->and(static fn() => $call(
            'validateRedirectReturnType',
            new MethodBody('injectorIntegerArgument', null, 0, 0, []),
            new MethodBody('injectorStringReturn', null, 0, 0, []),
            new MatchResult(0, 0, 'invoke', invocation: InvocationSpec::parse('injectorIntegerArgument')),
        ))->toThrow(InvalidArgumentException::class)
        ->and(static fn() => $call(
            'validateRedirectReturnType',
            new MethodBody('injectorIntegerArgument', null, 0, 0, []),
            new MethodBody('injectorNoReturnType', null, 0, 0, []),
            new MatchResult(0, 0, 'invoke', invocation: InvocationSpec::parse('injectorIntegerArgument')),
        ))->not->toThrow(Throwable::class);
});

class ParentClassForInjectorCoverage {}
class ChildClassForInjectorCoverage extends ParentClassForInjectorCoverage {}

final class InjectorCoercedCoverageTarget
{
    #[Coerce]
    public function method(#[Coerce] float $value): void {}
}

final class InjectorHelperCoverageTarget
{
    public function method(): void {}
}
