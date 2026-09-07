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
use Communism\Mixin\Local;
use Communism\Mixin\Parameter;
use Communism\Mixin\Slice;

describe('Injector', function (): void {
    covers(Injector::class);

    function injectorHelperBool(bool $value): bool
    {
        return $value;
    }

    function injectorCallOperand(Closure $call, string $name, mixed ...$arguments): Operand
    {
        $result = $call($name, ...$arguments);
        if (!$result instanceof Operand) {
            throw new LogicException('Expected an Operand result.');
        }
        return $result;
    }

    /** @return array<mixed, mixed> */
    function injectorCallArray(Closure $call, string $name, mixed ...$arguments): array
    {
        $result = $call($name, ...$arguments);
        if (!is_array($result)) {
            throw new LogicException('Expected an array result.');
        }
        return $result;
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

    function injectorHelperCoercedString(#[Coerce] string $value): string
    {
        return $value;
    }

    /**
     * @param array<int, mixed> $value
     * @return array<int, mixed>
     */
    function injectorHelperCoercedArray(#[Coerce] array $value): array
    {
        return $value;
    }

    /**
     * @param iterable<int, mixed> $value
     * @return iterable<int, mixed>
     */
    function injectorHelperCoercedIterable(#[Coerce] iterable $value): iterable
    {
        return $value;
    }

    function injectorHelperCoercedNoReturn(#[Coerce] mixed $value): mixed
    {
        return $value;
    }

    function injectorHelperCoercedInt(#[Coerce] int $value): int
    {
        return $value;
    }

    function injectorHelperCoercedStdClass(#[Coerce] stdClass $value): stdClass
    {
        return $value;
    }

    function injectorHelperCoercedUnion(#[Coerce] bool|stdClass|string $value): void {}

    function injectorHelperVariadicInt(int ...$values): void {}

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

    function injectorHelperCallbackBoth(CallbackInfo $info, #[Local] #[Parameter(name: 'value')] string $value): void {}

    function injectorHelperCallbackExplicitLocal(CallbackInfo $info, #[Local(name: 'value')] string $value): void {}

    function injectorHelperCallbackMissingLocal(CallbackInfo $info, #[Local(name: 'missing')] string $value): void {}

    function injectorHelperCallbackMissingParameter(CallbackInfo $info, #[Parameter(ordinal: 99)] string $value): void {}

    function injectorHelperCallbackMissingNamedParameter(CallbackInfo $info, #[Parameter(name: 'missing')] string $value): void {}

    function injectorHelperCallbackNamedValueParameter(CallbackInfo $info, #[Parameter(name: 'value')] string $renamed): void {}

    function injectorHelperCallbackExplicitOtherLocal(CallbackInfo $info, #[Local(name: 'other')] string $value): void {}

    function injectorHelperCallbackWithPositionalLocal(CallbackInfo $info, string $value, string $other): void {}

    eval(<<<'PHP'
    function injectorHelperDuplicateLocal(\Communism\Mixin\CallbackInfo $info, #[\Communism\Mixin\Local] #[\Communism\Mixin\Local] string $value): void {}
    PHP);

    /** @param CallbackInfoReturnable<mixed> $info */
    function injectorHelperCallbackWithReturnable(CallbackInfoReturnable $info): void {}

    function injectorArgsHelper(Args $args): void {}

    /** @param CallbackInfoReturnable<mixed> $info */
    function injectorHelperReturnable(CallbackInfoReturnable $info): void {}

    function injectorNullableParameter(?int $value): void {}

    function injectorIntParameter(int $value): void {}

    function injectorCoercedFloatParameter(float $value): void {}

    function injectorUntypedParameter(mixed $value): void {}

    /** @param mixed $value */
    function injectorNoTypeParameter($value): void {}

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

    interface InjectorReflectionLeft {}
    interface InjectorReflectionRight {}

    final class InjectorReflectionValue implements InjectorReflectionLeft, InjectorReflectionRight {}

    final class InjectorReflectionLeftValue implements InjectorReflectionLeft {}

    function injectorReflectionLeftValue(): InjectorReflectionLeftValue
    {
        return new InjectorReflectionLeftValue();
    }

    function injectorReflectionUnion(int|string $value): int|float
    {
        return is_int($value) ? $value : (strlen($value) > 0 ? 1.0 : 0);
    }

    function injectorReflectionIntersection(InjectorReflectionLeft&InjectorReflectionRight $value): void {}

    function injectorReflectionValue(): InjectorReflectionValue
    {
        return new InjectorReflectionValue();
    }

    function injectorReflectionCoercionUnion(): float|bool|string|stdClass
    {
        if (getenv('INJECTOR_COVERAGE_BOOL') === '1') {
            return false;
        }
        if (getenv('INJECTOR_COVERAGE_STRING') === '1') {
            return 'value';
        }
        if (getenv('INJECTOR_COVERAGE_FLOAT') === '1') {
            return 1.0;
        }
        return new stdClass();
    }

    /** @param iterable<int, mixed> $value */
    function injectorReflectionIterable(iterable $value): void {}

    /** @return array<int, string> */
    function injectorLiteralArray(): array
    {
        return ['value'];
    }

    it('covers Injector type compatibility and reflection helpers', function (): void {
        $call = static function (string $name, mixed ...$arguments): mixed {
            $method = new ReflectionMethod(Injector::class, $name);

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

    it('describes bounded and unbounded injection slices', function (): void {
        $method = new ReflectionMethod(Injector::class, 'sliceDescription');

        expect($method->invoke(null, new Slice()))->toBe('HEAD..TAIL')
            ->and($method->invoke(null, new Slice(new At('HEAD'))))->toBe('HEAD..TAIL')
            ->and($method->invoke(null, new Slice(null, new At('RETURN'))))->toBe('HEAD..RETURN');
    });

    it('resolves optional handler names and matched instruction names', function (): void {
        $handlerName = new ReflectionMethod(Injector::class, 'handlerName');
        $resolvedInstruction = new ReflectionMethod(Injector::class, 'resolvedInstruction');
        $inject = new Inject('run', new At('HEAD'));
        $body = new MethodBody('target', null, 0, 0, [
            new Instruction(0, 'RETURN', Operand::unused(), Operand::unused(), Operand::unused()),
        ]);

        expect($handlerName->invoke(null, null, $inject))->toBeNull()
            ->and($handlerName->invoke(null, static fn(): string => 'handler', $inject))->toBe('handler')
            ->and($handlerName->invoke(null, static fn(): ?string => null, $inject))->toBeNull()
            ->and($resolvedInstruction->invoke(null, $body, []))->toBeNull()
            ->and($resolvedInstruction->invoke(null, $body, [new MatchResult(0, 0, 'return')]))->toBe('RETURN');
    });

    it('evaluates reflected union and intersection type compatibility', function (): void {
        $call = static function (string $name, mixed ...$arguments): mixed {
            return (new ReflectionMethod(Injector::class, $name))->invoke(null, ...$arguments);
        };
        $unionReflection = new ReflectionFunction('injectorReflectionUnion');
        $unionParameter = $unionReflection->getParameters()[0]->getType();
        $unionReturn = $unionReflection->getReturnType();
        $intersection = (new ReflectionFunction('injectorReflectionIntersection'))
            ->getParameters()[0]
            ->getType();
        $valueReturn = (new ReflectionFunction('injectorReflectionValue'))->getReturnType();
        $leftValueReturn = (new ReflectionFunction('injectorReflectionLeftValue'))->getReturnType();
        $coercionUnion = (new ReflectionFunction('injectorReflectionCoercionUnion'))->getReturnType();
        $arrayReturn = (new ReflectionFunction('injectorLiteralArray'))->getReturnType();
        $iterable = (new ReflectionFunction('injectorReflectionIterable'))->getParameters()[0]->getType();
        $integer = (new ReflectionFunction('injectorHelperInt'))->getParameters()[0]->getType();
        $string = (new ReflectionFunction('injectorHelperString'))->getParameters()[0]->getType();

        expect($call('namedTypes', null))->toBe([])
            ->and($call('namedTypes', $integer))->toBe(['int'])
            ->and($call('namedTypes', $unionParameter))->toBe(['string', 'int'])
            ->and($call('reflectionTypeAccepts', $unionParameter, $integer))->toBeTrue()
            ->and($call('reflectionTypeAccepts', $integer, $unionParameter))->toBeFalse()
            ->and($call('reflectionTypeAccepts', $intersection, $valueReturn))->toBeTrue()
            ->and($call('reflectionTypeAccepts', $intersection, $leftValueReturn))->toBeFalse()
            ->and($call('reflectionTypeAccepts', (new ReflectionFunction('injectorHelperObject'))->getReturnType(), $intersection))->toBeFalse()
            ->and($call('reflectionTypeCanCoerce', $unionReturn, $integer))->toBeTrue()
            ->and($call('reflectionTypeCanCoerce', $integer, $string))->toBeFalse()
            ->and($call('reflectionTypeCanCoerce', $intersection, $intersection))->toBeFalse()
            ->and($call('reflectionTypeAcceptsName', $unionParameter, 'int'))->toBeTrue()
            ->and($call('reflectionTypeAcceptsName', $unionParameter, 'bool'))->toBeFalse()
            ->and($call('reflectionTypeAcceptsName', $intersection, InjectorReflectionValue::class))->toBeTrue()
            ->and($call('reflectionTypeAcceptsName', $intersection, InjectorReflectionLeftValue::class))->toBeFalse()
            ->and($call('reflectionTypeCanCoerceName', $unionParameter, 'float'))->toBeTrue()
            ->and($call('reflectionTypeCanCoerceName', $unionParameter, 'array'))->toBeFalse()
            ->and($call('reflectionTypeCanCoerceName', $intersection, 'int'))->toBeFalse()
            ->and($call('reflectionCoercionCastType', $string, $integer))->toBe(6)
            ->and($call('reflectionCoercionCastType', $integer, $integer))->toBeNull()
            ->and($call('reflectionCoercionCastType', $coercionUnion, $integer))->toBe(5)
            ->and($call('namedCoercionCastType', 'string', $integer))->toBe(6)
            ->and($call('namedCoercionCastType', 'bool', $string))->toBe(3)
            ->and($call('namedCoercionCastType', 'int', (new ReflectionFunction('injectorHelperFloat'))->getParameters()[0]->getType()))->toBe(4)
            ->and($call('namedCoercionCastType', 'float', $integer))->toBe(5)
            ->and($call('namedCoercionCastType', 'array', $iterable))->toBe(7)
            ->and($call('namedCoercionCastType', 'stdClass', $integer))->toBeNull()
            ->and($call('namedCoercionCastType', 'stdClass&InjectorReflectionLeft|bool', $integer))->toBe(3)
            ->and($call('namedCoercionCastType', 'stdClass|bool', $integer))->toBe(3)
            ->and($call('namedCoercionCastType', 'float|bool|string|stdClass', $integer))->toBe(5)
            ->and($call('namedCoercionCastType', null, $integer))->toBeNull()
            ->and($call('namedCoercionCastType', 'stdClass|array', $integer))->toBeNull()
            ->and($call('namedCoercionCastType', 'iterable', $arrayReturn))->toBeNull()
            ->and($call('namedCoercionCastType', 'stdClass&InjectorReflectionLeft|array', $integer))->toBeNull()
            ->and($call('reflectionCoercionCastType', $iterable, $arrayReturn))->toBeNull()
            ->and($call('reflectionCoercionCastType', $coercionUnion, (new ReflectionFunction('injectorHelperObject'))->getReturnType()))->toBeNull();
    });

    it('validates and coerces virtual argument operands by reflected parameter type', function (): void {
        $call = static function (string $name, mixed ...$arguments): mixed {
            return (new ReflectionMethod(Injector::class, $name))->invoke(null, ...$arguments);
        };
        $handler = new MethodBody('injectorHelperString', null, 0, 0, []);

        expect($call('validateArgsValue', null, 0, Operand::constant(1, 0)))->toBeInstanceOf(Operand::class)
            ->and($call('validateArgsValue', new ReflectionFunction('injectorHelperInt'), 0, Operand::constant(1, 0)))->toEqual(Operand::constant(1, 0))
            ->and(injectorCallOperand($call, 'validateArgsValue', new ReflectionFunction('injectorHelperInt'), 0, Operand::constant(2.5, 0), true)->value)->toBe(2)
            ->and(injectorCallOperand($call, 'validateArgsValue', new ReflectionFunction('injectorHelperFloat'), 0, Operand::constant(2, 0), true)->value)->toBe(2.0)
            ->and($call('validateArgsValue', new ReflectionFunction('injectorHelperBool'), 0, Operand::constant(true, 0)))->toEqual(Operand::constant(true, 0))
            ->and($call('validateArgsValue', new ReflectionFunction('injectorHelperString'), 0, Operand::constant('value', 0)))->toEqual(Operand::constant('value', 0))
            ->and($call('validateArgsValue', new ReflectionFunction('injectorNoTypeParameter'), 0, Operand::constant([], 0)))->toEqual(Operand::constant([], 0));
        expect(static fn() => $call('validateArgsValue', new ReflectionFunction('injectorHelperInt'), 0, Operand::constant(new stdClass(), 0)))
            ->toThrow(InvalidArgumentException::class, 'provides stdClass');

        expect(static fn() => $call('validateArgsValue', new ReflectionFunction('injectorHelperInt'), 0, Operand::constant('wrong', 0)))
            ->toThrow(InvalidArgumentException::class, 'requires int');

        foreach ([
            'injectorHelperBool' => 'int',
            'injectorHelperFloat' => 'int',
            'injectorHelperInt' => 'float',
            'injectorHelperString' => 'int',
        ] as $function => $actualType) {
            $target = new MethodBody('argument-target', null, 0, 0, [], variableTypes: [0 => $actualType]);
            $cv = Operand::cv(0);
            $result = injectorCallArray($call, 'coerceArgsOperand', $target, $handler, new ReflectionFunction($function), 0, $cv, true, 0, 0, [$cv]);
            expect($result[1])->toBeInstanceOf(Instruction::class)
                ->and($result[2])->toBe(1);
        }

        $target = new MethodBody('argument-target', null, 0, 0, [], variableTypes: [0 => 'string']);
        $cv = Operand::cv(0);
        expect(injectorCallArray($call, 'coerceArgsOperand', $target, $handler, null, 0, $cv, true, 0, 0, [$cv]))
            ->toEqual([$cv, null, 0])
            ->and(injectorCallArray($call, 'coerceArgsOperand', $target, $handler, new ReflectionFunction('injectorHelperInt'), 0, Operand::constant(1, 0), true, 0, 0, []))
            ->toEqual([Operand::constant(1, 0), null, 0]);

        $variadic = new ReflectionFunction('injectorHelperVariadicInt');
        expect(injectorCallArray($call, 'coerceArgsOperand', $target, $handler, $variadic, 2, $cv, true, 0, 0, [$cv]))
            ->toBeArray();
        expect(injectorCallArray($call, 'coerceArgsOperand', new MethodBody('argument-target', null, 0, 0, [], variableTypes: [9 => 'object']), $handler, new ReflectionFunction('injectorHelperInt'), 0, Operand::cv(9), true, 0, 0, []))
            ->toEqual([Operand::cv(9), null, 0]);
        expect(injectorCallArray($call, 'coerceArgsOperand', new MethodBody('argument-target', null, 0, 0, []), $handler, new ReflectionFunction('injectorHelperInt'), 0, Operand::cv(9), true, 0, 0, []))
            ->toEqual([Operand::cv(9), null, 0]);
        $intTarget = new MethodBody('argument-target', null, 0, 0, [], variableTypes: [0 => 'int']);
        expect(injectorCallArray($call, 'coerceArgsOperand', $intTarget, $handler, new ReflectionFunction('injectorHelperInt'), 0, Operand::cv(0), true, 0, 0, [Operand::cv(0)]))
            ->toEqual([Operand::cv(0), null, 0]);
        $arrayTarget = new MethodBody('argument-target', null, 0, 0, [], variableTypes: [0 => 'array']);
        expect(injectorCallArray($call, 'coerceArgsOperand', $arrayTarget, $handler, new ReflectionFunction('injectorReflectionIterable'), 0, Operand::cv(0), true, 0, 0, [Operand::cv(0)]))
            ->toEqual([Operand::cv(0), null, 0]);

        expect(injectorCallArray($call, 'coerceCallbackInputs', $target, new MethodBody('injectorHelperCallbackWithLocal', null, 0, 0, []), [], 0, 0))
            ->toEqual([[], [], 0]);
        $coercionTarget = new MethodBody('injectorHelperInt', null, 0, 0, [
            new Instruction(0, 'RECV', Operand::cv(0), Operand::unused(), Operand::unused()),
        ], variableTypes: [0 => 'int']);
        $coercionHandler = new MethodBody('injectorHelperCoercedString', null, 0, 0, [
            new Instruction(0, 'RECV', Operand::cv(0), Operand::unused(), Operand::unused()),
        ]);
        $coercionPrelude = injectorCallArray($call, 'coerceCallbackInputs', $coercionTarget, $coercionHandler, [0 => Operand::cv(0)], 0, 0);
        expect($coercionPrelude[0])->toHaveCount(1)
            ->and($coercionPrelude[2])->toBe(1);

        $arrayTarget = new MethodBody('injectorHelperInt', null, 0, 0, [], variableTypes: [0 => 'iterable']);
        $arrayHandler = new MethodBody('injectorHelperCoercedArray', null, 0, 0, [
            new Instruction(0, 'RECV', Operand::cv(0), Operand::unused(), Operand::unused()),
        ]);
        expect(injectorCallArray($call, 'coerceCallbackInputs', $arrayTarget, $arrayHandler, [0 => Operand::cv(0)], 0, 0)[2])->toBe(1);

        $iterableTarget = new MethodBody('injectorHelperInt', null, 0, 0, [], variableTypes: [0 => 'array']);
        $iterableHandler = new MethodBody('injectorHelperCoercedIterable', null, 0, 0, [
            new Instruction(0, 'RECV', Operand::cv(0), Operand::unused(), Operand::unused()),
        ]);
        expect(injectorCallArray($call, 'coerceCallbackInputs', $iterableTarget, $iterableHandler, [0 => Operand::cv(0)], 0, 0)[2])->toBe(0);

        $numericTarget = new MethodBody('injectorHelperInt', null, 0, 0, [], variableTypes: [0 => 'float']);
        $numericHandler = new MethodBody('injectorHelperCoercedInt', null, 0, 0, [
            new Instruction(0, 'RECV', Operand::cv(0), Operand::unused(), Operand::unused()),
        ]);
        expect(injectorCallArray($call, 'coerceCallbackInputs', $numericTarget, $numericHandler, [0 => Operand::cv(0)], 0, 0)[2])->toBe(1);

        $objectTarget = new MethodBody('injectorHelperInt', null, 0, 0, [], variableTypes: [0 => InjectorReflectionLeftValue::class]);
        $objectHandler = new MethodBody('injectorHelperCoercedStdClass', null, 0, 0, [
            new Instruction(0, 'RECV', Operand::cv(0), Operand::unused(), Operand::unused()),
        ]);
        expect(injectorCallArray($call, 'coerceCallbackInputs', $objectTarget, $objectHandler, [0 => Operand::cv(0)], 0, 0)[2])->toBe(0);

        $unionHandler = new MethodBody('injectorHelperCoercedUnion', null, 0, 0, [
            new Instruction(0, 'RECV', Operand::cv(0), Operand::unused(), Operand::unused()),
        ]);
        expect(injectorCallArray($call, 'coerceCallbackInputs', $numericTarget, $unionHandler, [0 => Operand::cv(0)], 0, 0)[2])->toBe(1);
    });

    it('folds callback comparisons only when both constants are compatible', function (): void {
        $method = new ReflectionMethod(Injector::class, 'foldCallbackComparison');
        $instruction = static function (string $name, mixed $left, mixed $right): Instruction {
            return new Instruction(
                0,
                $name,
                Operand::unused(),
                Operand::constant($left, 0),
                Operand::constant($right, 0),
            );
        };

        expect($method->invoke(null, $instruction('IS_EQUAL', 1, '1')))->toBeNull()
            ->and($method->invoke(null, $instruction('IS_NOT_EQUAL', 1, 2)))->toBeTrue()
            ->and($method->invoke(null, $instruction('IS_IDENTICAL', 1, 1)))->toBeTrue()
            ->and($method->invoke(null, $instruction('IS_NOT_IDENTICAL', 1, 1)))->toBeFalse();
    });

    it('handles field return coercion when reflection metadata is unavailable', function (): void {
        $method = new ReflectionMethod(Injector::class, 'coerceReplacementReturn');
        $return = new Instruction(0, 'RETURN', Operand::constant('value', 0), Operand::unused(), Operand::unused());
        $handler = new MethodBody('injectorHelperString', null, 0, 0, []);
        $missingClass = new MethodBody('MissingInjectorClass::method', null, 0, 0, [
            new Instruction(0, 'FETCH_OBJ_R', Operand::unused(), Operand::unused(), Operand::constant('value', 0)),
        ]);
        $missingProperty = new MethodBody(InjectorHelperCoverageTarget::class . '::method', null, 0, 0, [
            new Instruction(0, 'FETCH_OBJ_R', Operand::unused(), Operand::unused(), Operand::constant('missing', 0)),
        ]);

        expect($method->invoke(null, $missingClass, $handler, new MatchResult(0, 0, 'field', fieldMode: 'read'), [], $return, 0, 0, 0))
            ->toBe([[], $return, 0])
            ->and($method->invoke(null, $missingProperty, $handler, new MatchResult(0, 0, 'field', fieldMode: 'read'), [], $return, 0, 0, 0))
            ->toBe([[], $return, 0])
            ->and($method->invoke(null, $missingClass, new MethodBody('injectorNoReturnType', null, 0, 0, []), new MatchResult(0, 0, 'field', fieldMode: 'read'), [], $return, 0, 0, 0))
            ->toBe([[], $return, 0]);
    });

    it('classifies scalar field return targets for coercion', function (): void {
        $method = new ReflectionMethod(Injector::class, 'coerceReplacementReturn');
        $return = new Instruction(0, 'RETURN', Operand::constant('value', 0), Operand::unused(), Operand::unused());
        $handler = new MethodBody('injectorHelperString', null, 0, 0, []);

        foreach (['integer', 'float', 'boolean', 'array', 'string'] as $property) {
            $target = new MethodBody(InjectorTypedFieldCoverageTarget::class . '::method', null, 0, 0, [
                new Instruction(0, 'FETCH_OBJ_R', Operand::unused(), Operand::unused(), Operand::constant($property, 0)),
            ]);

            expect($method->invoke(null, $target, $handler, new MatchResult(0, 0, 'field', fieldMode: 'read'), [], $return, 0, 0, 0))
                ->toBeArray();
        }

        $variable = new MethodBody(
            'target',
            null,
            0,
            0,
            [new Instruction(0, 'ASSIGN', Operand::unused(), Operand::cv(1), Operand::unused())],
            [1 => 'value'],
            variableTypes: [1 => 'int'],
        );
        expect($method->invoke(null, $variable, $handler, new MatchResult(0, 0, 'variable', variableMode: 'load'), [], $return, 0, 0, 0))
            ->toBeArray();

        foreach ([1, 1.0, true, [], 'value', null, new stdClass()] as $value) {
            $target = new MethodBody('constant-target', null, 0, 0, [
                new Instruction(0, 'QM_ASSIGN', Operand::unused(), Operand::constant($value, 0), Operand::unused()),
            ]);
            expect($method->invoke(null, $target, new MethodBody('injectorHelperCoercedString', null, 0, 0, []), new MatchResult(0, 1, 'constant'), [], $return, 0, 0, 0))
                ->toBeArray();
        }

        $variableTarget = new MethodBody('constant-target', null, 0, 0, [
            new Instruction(0, 'FETCH_R', Operand::cv(0), Operand::cv(0), Operand::unused()),
        ], variableTypes: [0 => 'int']);
        expect($method->invoke(null, $variableTarget, new MethodBody('injectorHelperCoercedString', null, 0, 0, []), new MatchResult(0, 1, 'variable', fieldMode: 'read', variableMode: 'load'), [], $return, 0, 0, 0))
            ->toBeArray()
            ->and($method->invoke(null, new MethodBody('constant-target', null, 0, 0, [new Instruction(0, 'QM_ASSIGN', Operand::unused(), Operand::constant(1, 0), Operand::unused())]), new MethodBody('injectorHelperCoercedNoReturn', null, 0, 0, []), new MatchResult(0, 1, 'constant'), [], $return, 0, 0, 0))
            ->toBeArray();
    });

    it('validates nullable redirect types and receivers', function (): void {
        $call = static function (string $name, mixed ...$arguments): mixed {
            return (new ReflectionMethod(Injector::class, $name))->invoke(null, ...$arguments);
        };
        $integer = (new ReflectionFunction('injectorHelperInt'))->getParameters()[0]->getType();
        $string = (new ReflectionFunction('injectorHelperString'))->getParameters()[0]->getType();
        $nullable = (new ReflectionFunction('injectorNullableIntegerReturn'))->getReturnType();
        $untypedParameter = (new ReflectionFunction('injectorNoTypeParameter'))->getParameters()[0];
        $nullableParameter = (new ReflectionFunction('injectorNullableParameter'))->getParameters()[0];
        $coercedReceiver = (new ReflectionMethod(InjectorCoercedCoverageTarget::class, 'method'))->getParameters()[0];

        expect(static fn() => $call('validateTypesForRedirect', $nullable, $integer, false, 'target', 'handler'))
            ->toThrow(InvalidArgumentException::class, 'cannot accept nullable')
            ->and(static fn() => $call('validateTypesForRedirect', $integer, $nullable, false, 'target', 'handler'))
            ->toThrow(InvalidArgumentException::class, 'may return null')
            ->and($call('validateTypesForRedirect', $nullable, $nullable, false, 'target', 'handler'))->toBeNull()
            ->and(static fn() => $call('validateTypesForRedirect', $integer, $string, false, 'target', 'handler'))
            ->toThrow(InvalidArgumentException::class, 'uses string')
            ->and(static fn() => $call('validateTypeNamesForRedirect', 'int', 'string', true, false, false, 'target', 'handler'))
            ->toThrow(InvalidArgumentException::class, 'cannot accept nullable')
            ->and(static fn() => $call('validateTypeNamesForRedirect', 'int', 'string', false, true, false, 'target', 'handler'))
            ->toThrow(InvalidArgumentException::class, 'may return null')
            ->and($call('validateTypeNamesForRedirect', 'int', 'string', true, true, false, 'target', 'handler'))->toBeNull()
            ->and(static fn() => $call('validateTypeNamesForRedirect', 'int', 'string', false, false, false, 'target', 'handler'))
            ->toThrow(InvalidArgumentException::class, 'uses string')
            ->and($call('validateRedirectReceiverType', $untypedParameter, stdClass::class, 'handler', false))->toBeNull()
            ->and(static fn() => $call('validateRedirectReceiverType', $nullableParameter, stdClass::class, 'handler', false))
            ->toThrow(InvalidArgumentException::class, 'must not be nullable')
            ->and($call('validateRedirectReceiverType', $coercedReceiver, 'int', 'handler', true))->toBeNull()
            ->and(static fn() => $call('validateRedirectReceiverType', (new ReflectionFunction('injectorHelperInt'))->getParameters()[0], stdClass::class, 'handler', false))
            ->toThrow(InvalidArgumentException::class, 'expects int');
    });

    it('handles missing and untyped field metadata during redirect validation', function (): void {
        $call = static function (string $name, mixed ...$arguments): mixed {
            return (new ReflectionMethod(Injector::class, $name))->invoke(null, ...$arguments);
        };
        $handler = new MethodBody('injectorHelperString', null, 0, 0, []);
        $field = static function (string $target, Operand $name): MethodBody {
            return new MethodBody($target, null, 0, 0, [
                new Instruction(0, 'FETCH_OBJ_R', Operand::unused(), Operand::unused(), $name),
            ]);
        };

        expect($call('validateFieldRedirectTypes', $field('MissingInjectorClass::method', Operand::constant('value', 0)), $handler, new MatchResult(0, 0, 'field', fieldMode: 'read')))
            ->toBeNull()
            ->and($call('validateFieldRedirectTypes', $field(InjectorHelperCoverageTarget::class . '::method', Operand::constant('missing', 0)), $handler, new MatchResult(0, 0, 'field', fieldMode: 'read')))
            ->toBeNull()
            ->and($call('validateFieldRedirectTypes', $field(InjectorTypedFieldCoverageTarget::class . '::method', Operand::constant('untyped', 0)), $handler, new MatchResult(0, 0, 'field', fieldMode: 'read')))
            ->toBeNull()
            ->and($call('validateFieldRedirectTypes', $field('not-a-method', Operand::unused()), $handler, new MatchResult(0, 0, 'field', fieldMode: 'read')))
            ->toBeNull()
            ->and($call('validateFieldRedirectTypes', $field(InjectorTypedFieldCoverageTarget::class . '::method', Operand::unused()), $handler, new MatchResult(0, 0, 'field', fieldMode: 'read')))
            ->toBeNull();
    });

    it('skips constructor validation without a class operand or value return', function (): void {
        $method = new ReflectionMethod(Injector::class, 'validateConstructorRedirectType');
        $target = new MethodBody('target', null, 0, 0, [
            new Instruction(0, 'NEW', Operand::unused(), Operand::unused(), Operand::unused()),
        ]);

        expect($method->invoke(null, $target, new MethodBody('injectorHelperCallback', null, 0, 0, []), new MatchResult(0, 0, 'new')))
            ->toBeNull();
    });

    it('resolves static arrays and rejects malformed array constructions', function (): void {
        $call = static function (string $name, mixed ...$arguments): mixed {
            return (new ReflectionMethod(Injector::class, $name))->invoke(null, ...$arguments);
        };
        $unused = Operand::unused();
        $array = Operand::constant(['value'], 0);
        $assign = new Instruction(0, 'ASSIGN', $unused, Operand::cv(1), $array);

        expect($call('staticArrayOperand', [$assign], $array, 1))->toBe(['value'])
            ->and($call('staticArrayOperand', [$assign], Operand::cv(2), 1))->toBeNull()
            ->and($call('staticArrayOperand', [], Operand::variable(1), 0))->toBeNull()
            ->and($call('literalArrayReturn', new MethodBody('array', null, 0, 0, [
                new Instruction(0, 'RETURN', $unused, Operand::constant(['key' => 'value'], 0), $unused),
            ])))->toBeNull()
            ->and($call('arrayConstructionOperands', [
                new Instruction(0, 'INIT_ARRAY', Operand::temporary(16), Operand::constant('value', 0), $unused),
                new Instruction(0, 'ADD_ARRAY_ELEMENT', Operand::temporary(16), Operand::constant('other', 0), Operand::constant('key', 0)),
            ], Operand::temporary(16)))->toBeNull()
            ->and($call('arrayConstructionTemporary', [], Operand::variable(1)))->toBeNull();

        $functionInstructions = [
            new Instruction(0, 'INIT_FCALL', $unused, $unused, Operand::constant('injectorLiteralArray', 0)),
            new Instruction(0, 'DO_FCALL', Operand::temporary(16), $unused, $unused),
            new Instruction(0, 'ASSIGN', $unused, Operand::cv(1), Operand::temporary(16)),
        ];
        expect($call('staticArrayOperand', $functionInstructions, Operand::cv(1), 3))->toBe(['value']);

        $staticInstructions = [
            new Instruction(0, 'INIT_STATIC_METHOD_CALL', $unused, Operand::constant(ReflectionClass::class, 0), Operand::constant('getName', 0)),
            new Instruction(0, 'DO_FCALL', Operand::temporary(16), $unused, $unused),
            new Instruction(0, 'ASSIGN', $unused, Operand::cv(1), Operand::temporary(16)),
        ];
        expect($call('staticArrayOperand', $staticInstructions, Operand::cv(1), 3))->toBeNull();

        $internalInstructions = [
            new Instruction(0, 'INIT_FCALL', $unused, $unused, Operand::constant('strlen', 0)),
            new Instruction(0, 'DO_FCALL', Operand::temporary(16), $unused, $unused),
            new Instruction(0, 'ASSIGN', $unused, Operand::cv(1), Operand::temporary(16)),
        ];
        expect($call('staticArrayOperand', $internalInstructions, Operand::cv(1), 3))->toBeNull();
    });

    it('validates constructor replacement receive mappings', function (): void {
        $call = static function (string $name, mixed ...$arguments): mixed {
            return (new ReflectionMethod(Injector::class, $name))->invoke(null, ...$arguments);
        };
        $unused = Operand::unused();
        $match = new MatchResult(0, 1, 'new');
        $target = static function (Operand $class) use ($unused): MethodBody {
            return new MethodBody('target', null, 0, 0, [
                new Instruction(0, 'NEW', $unused, $class, $unused),
            ]);
        };
        $receive = static function (Operand $result) use ($unused): Instruction {
            return new Instruction(0, 'RECV', $result, $unused, $unused);
        };

        expect(static fn() => $call('constructorReplacementInputMap', $target(Operand::constant(stdClass::class, 0)), new MethodBody('handler', null, 0, 0, [$receive($unused)]), $match))
            ->toThrow(InvalidArgumentException::class, 'has no CV operand')
            ->and($call('constructorReplacementInputMap', $target($unused), new MethodBody('handler', null, 0, 0, [$receive(Operand::cv(0))]), $match))
            ->toBe([])
            ->and($call('constructorReplacementInputMap', $target(Operand::constant('MissingInjectorConstructor', 0)), new MethodBody('handler', null, 0, 0, [$receive(Operand::cv(0))]), $match))
            ->toBe([])
            ->and(static fn() => $call('constructorReplacementInputMap', $target(Operand::constant(stdClass::class, 0)), new MethodBody('handler', null, 0, 0, [$receive(Operand::cv(0)), $receive(Operand::cv(1))]), $match))
            ->toThrow(InvalidArgumentException::class, 'expects 2 argument(s)');
    });

    it('returns default values and handles helper metadata without bytecode', function (): void {
        $call = static function (string $name, mixed ...$arguments): mixed {
            $method = new ReflectionMethod(Injector::class, $name);

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

        expect($call('localAvailableAt', $target, Operand::constant('value', 0), 0))->toBeFalse()
            ->and($call('localAvailableAt', $target, Operand::cv(0), 0))->toBeTrue()
            ->and($call('localAvailableAt', new MethodBody('injectorHelperString', null, 0, 0, [
                new Instruction(0, 'ASSIGN', Operand::unused(), Operand::cv(1), Operand::constant('value', 0)),
            ], [1 => 'local']), Operand::cv(1), 1))->toBeTrue();

        $capturedType = new ReflectionFunction('injectorIntParameter')->getParameters()[0];
        $untyped = new ReflectionFunction('injectorNoTypeParameter')->getParameters()[0];
        expect($call('validateCapturedLocalType', $capturedType, null, 'handler', false))->toBeNull()
            ->and($call('validateCapturedLocalType', $untyped, 'object', 'handler', false))->toBeNull();

        $targetWithParameter = new MethodBody(
            'injectorHelperString',
            null,
            0,
            0,
            [new Instruction(0, 'RECV', Operand::cv(0), $unused, $unused)],
            [0 => 'value'],
        );
        $handlerReceives = [
            new Instruction(0, 'RECV', Operand::cv(0), $unused, $unused),
            new Instruction(0, 'RECV', Operand::cv(1), $unused, $unused),
        ];
        expect(static fn() => $call('callbackContext', $targetWithParameter, new MethodBody('injectorHelperCallbackBoth', null, 0, 0, $handlerReceives), $inject, new MatchResult(0, 0, 'head')))
            ->toThrow(CaptureException::class, 'both Local and Parameter')
            ->and(static fn() => $call('callbackContext', $targetWithParameter, new MethodBody('injectorHelperCallbackExplicitLocal', null, 0, 0, $handlerReceives), new Inject('injectorHelperString', new At('HEAD'), locals: LocalCapture::NO_CAPTURE), new MatchResult(0, 0, 'head')))
            ->toThrow(CaptureException::class, 'local while local capture is disabled')
            ->and(static fn() => $call('callbackContext', $targetWithParameter, new MethodBody('injectorHelperCallbackExplicitLocal', null, 0, 0, $handlerReceives), $inject, new MatchResult(0, 0, 'head')))
            ->toThrow(CaptureException::class, 'names a target parameter')
            ->and(static fn() => $call('callbackContext', $targetWithParameter, new MethodBody('injectorHelperCallbackMissingLocal', null, 0, 0, $handlerReceives), $inject, new MatchResult(0, 0, 'head')))
            ->toThrow(CaptureException::class, 'cannot be captured')
            ->and(static fn() => $call('callbackContext', $targetWithParameter, new MethodBody('injectorHelperCallbackMissingParameter', null, 0, 0, $handlerReceives), $inject, new MatchResult(0, 0, 'head')))
            ->toThrow(CaptureException::class, 'cannot resolve its target parameter')
            ->and(static fn() => $call('callbackContext', new MethodBody('injectorHelperString', null, 0, 0, [new Instruction(0, 'RECV', Operand::cv(0), $unused, $unused)]), new MethodBody('injectorHelperCallbackNamedValueParameter', null, 0, 0, $handlerReceives), $inject, new MatchResult(0, 0, 'head')))
            ->toThrow(CaptureException::class, 'cannot capture target parameter')
            ->and(static fn() => $call('callbackContext', new MethodBody('injectorHelperString', null, 0, 0, [
                new Instruction(0, 'ASSIGN', Operand::cv(1), Operand::cv(1), Operand::constant('local', 0)),
                new Instruction(0, 'NOP', $unused, $unused, $unused),
            ], [0 => 'value', 1 => 'other']), new MethodBody('injectorHelperCallbackWithOtherLocal', null, 0, 0, $handlerReceives), new Inject('injectorHelperString', new At('HEAD'), locals: LocalCapture::NO_CAPTURE), new MatchResult(0, 1, 'head', action: 'after')))
            ->toThrow(CaptureException::class, 'local and local capture is disabled');

        $localAfterTarget = new MethodBody('injectorHelperString', null, 0, 0, [
            new Instruction(0, 'ASSIGN', Operand::cv(1), Operand::cv(1), Operand::constant('local', 0)),
            new Instruction(0, 'NOP', $unused, $unused, $unused),
        ], [0 => 'value', 1 => 'other']);
        expect($call('callbackContext', $localAfterTarget, new MethodBody('injectorHelperCallbackExplicitOtherLocal', null, 0, 0, $handlerReceives), $inject, new MatchResult(0, 1, 'head', action: 'after')))
            ->toBeArray();

        $positionalReceives = [
            new Instruction(0, 'RECV', Operand::cv(0), $unused, $unused),
            new Instruction(0, 'RECV', Operand::cv(1), $unused, $unused),
            new Instruction(0, 'RECV', Operand::cv(2), $unused, $unused),
        ];
        $positionalTarget = new MethodBody('injectorHelperString', null, 0, 0, [
            new Instruction(0, 'ASSIGN', Operand::cv(1), Operand::cv(1), Operand::constant('local', 0)),
            new Instruction(0, 'NOP', $unused, $unused, $unused),
        ], [0 => 'value', 1 => 'local']);
        expect($call('callbackContext', $positionalTarget, new MethodBody('injectorHelperCallbackWithPositionalLocal', null, 0, 0, $positionalReceives), $inject, new MatchResult(0, 1, 'head', action: 'after')))
            ->toBeArray();

        expect($call('callbackContext', $targetWithParameter, new MethodBody('injectorHelperCallback', null, 0, 0, [
            new Instruction(0, 'RECV', $unused, $unused, $unused),
        ]), $inject, new MatchResult(0, 0, 'head')))->toBeNull();
    });

    it('covers virtual CallbackInfo lowering guards', function (): void {
        $call = static function (string $name, mixed ...$arguments): mixed {
            $method = new ReflectionMethod(Injector::class, $name);

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

        expect($call('prepareArgsHandler', $target, new MethodBody('injectorArgsHelper', null, 0, 0, [
            new Instruction(0, 'RECV', Operand::cv(0), $unused, $unused),
        ]), new MatchResult(0, 0, 'invoke'), 0, 0, 0))->toBeArray();

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

    it('rejects duplicate callback binding annotations', function (): void {
        $call = static function (string $name, mixed ...$arguments): mixed {
            return (new ReflectionMethod(Injector::class, $name))->invoke(null, ...$arguments);
        };
        $target = new MethodBody(
            'injectorHelperString',
            null,
            0,
            0,
            [new Instruction(0, 'RECV', Operand::cv(0), Operand::unused(), Operand::unused())],
            [0 => 'value'],
        );
        $handler = new MethodBody(
            'injectorHelperDuplicateLocal',
            null,
            0,
            0,
            [
                new Instruction(0, 'RECV', Operand::cv(0), Operand::unused(), Operand::unused()),
                new Instruction(0, 'RECV', Operand::cv(1), Operand::unused(), Operand::unused()),
            ],
        );

        expect(static fn() => $call('callbackContext', $target, $handler, new Inject('run', new At('HEAD')), new MatchResult(0, 0, 'head')))
            ->toThrow(CaptureException::class, 'duplicate binding declarations');
    });

    it('rejects malformed modifier receive operands', function (): void {
        $call = static function (string $name, mixed ...$arguments): mixed {
            return (new ReflectionMethod(Injector::class, $name))->invoke(null, ...$arguments);
        };
        $unused = Operand::unused();
        $handler = new MethodBody('injectorHelperString', null, 0, 0, [
            new Instruction(0, 'RECV', Operand::cv(0), $unused, $unused),
            new Instruction(0, 'RECV', Operand::constant('not-a-cv', 0), $unused, $unused),
        ]);
        $arrayTarget = new MethodBody('not-a-method', null, 0, 0, [
            new Instruction(0, 'ASSIGN_DIM', $unused, Operand::cv(0), Operand::constant('key', 0)),
            new Instruction(0, 'OP_DATA', $unused, Operand::constant('value', 0), $unused),
        ]);

        expect(static fn() => $call('modifierInputMap', $arrayTarget, $handler, new MatchResult(0, 1, 'field', fieldMode: 'array-write')))
            ->toThrow(InvalidArgumentException::class, 'no CV operand');

        $constantTarget = new MethodBody('not-a-method', null, 0, 0, [
            new Instruction(0, 'NOP', $unused, $unused, $unused),
        ]);
        $validHandler = new MethodBody('injectorHelperString', null, 0, 0, [
            new Instruction(0, 'RECV', Operand::cv(0), $unused, $unused),
        ]);
        expect(static fn() => $call('modifierInputMap', $constantTarget, $validHandler, new MatchResult(0, 1, 'constant')))
            ->toThrow(InvalidArgumentException::class, 'no literal operand');
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

    final class InjectorTypedFieldCoverageTarget
    {
        public int $integer;
        public float $float;
        public bool $boolean;
        /** @var array<int, mixed> */
        public array $array;
        public string $string;
        /** @var mixed */
        public $untyped;
    }
});
