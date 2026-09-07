<?php

declare(strict_types=1);

use Zendful\AssemblyPlanHandle;
use Zendful\ClassHandle;
use Zendful\FunctionHandle;
use Zendful\MethodHandle;
use Zendful\PropertyHandle;
use Zendful\Internals\Executor as ZendfulExecutor;
use Zendful\Internals\Natives;

describe('Executor', function (): void {
    covers([ZendfulExecutor::class, Natives::class]);

    function executorCoverageFirst(): string
    {
        return 'first';
    }

    function executorCoverageSecond(): string
    {
        return 'second';
    }

    function executorCoverageAssemblyTarget(): string
    {
        return 'assembly';
    }

    function executorCoverageNoLiteralAssemblyTarget(): string
    {
        return 'no-literal-assembly';
    }

    function executorCoverageWithVariable(string $value): string
    {
        $local = $value;

        return $local;
    }

    /** @return array{int, bool} */
    function executorCoverageLiteralArray(): array
    {
        return [1, true];
    }

    it('rejects oversized Zend strings before reading their payload', function (): void {
        $ffi = Natives::ffi();
        $string = new class {
            public int $len;
        };
        $string->len = Natives::ZEND_MAX_SAFE_STRING_LENGTH + 1;
        $method = new ReflectionMethod(ZendfulExecutor::class, 'zendString');

        expect(static function () use ($method, $ffi, $string): void { $method->invoke(null, $ffi, $string); })
            ->toThrow(RuntimeException::class);
    });

    it('exercises callable and member queries without requiring OPcache', function (): void {
        $function = new FunctionHandle('executorCoverageFirst');
        $method = new MethodHandle(ExecutorFinalTarget::class, 'itIsNotARealMethod');

        expect(ZendfulExecutor::opcodeName(0))->toBeString()
            ->and(ZendfulExecutor::opcodeId('NOP'))->toBeInt()
            ->and(ZendfulExecutor::framelessFunction(-1))->toBeNull()
            ->and(ZendfulExecutor::functionExists($function))->toBeTrue()
            ->and(ZendfulExecutor::isUserDefined($function))->toBeTrue()
            ->and(ZendfulExecutor::methodExists($method))->toBeFalse()
            ->and(ZendfulExecutor::propertyExists(new PropertyHandle(ExecutorFinalTarget::class, 'missing')))->toBeFalse()
            ->and(ZendfulExecutor::classExists(new ClassHandle(ExecutorFinalTarget::class)))->toBeTrue();

        ZendfulExecutor::disableJitForFunction($function);
        ZendfulExecutor::disableJitForMethod($method);
        ZendfulExecutor::disableJitForClass(new ClassHandle(ExecutorFinalTarget::class));
    });

    it('covers literal encoding, constant decoding, and operand formatting', function (): void {
        $ffi = Natives::ffi();
        $invoke = static function (string $name, mixed ...$arguments): mixed {
            $method = new ReflectionMethod(ZendfulExecutor::class, $name);

            return $method->invoke(null, ...$arguments);
        };

        foreach ([null, false, true, 42, 4.5] as $value) {
            $literal = $ffi->new('zval');
            expect($invoke('writeLiteral', $ffi, $literal, $value))->toBeNull();
        }

        $stringLiteral = $ffi->new('zval');
        $string = $invoke('writeLiteral', $ffi, $stringLiteral, 'literal');
        if (!is_object($string)) {
            throw new LogicException('Expected a Zend string allocation.');
        }
        expect($invoke('constantValue', $ffi, $stringLiteral))->toBe('literal');
        $ffi->free_estring(FFI::addr($string));

        expect(static function () use ($invoke, $ffi): void { $invoke('writeLiteral', $ffi, $ffi->new('zval'), new stdClass()); })
            ->toThrow(RuntimeException::class)
            ->and(static function () use ($invoke, $ffi): void { $invoke('writeLiteral', $ffi, $ffi->new('zval'), "bad\0literal"); })
            ->toThrow(InvalidArgumentException::class);

        $types = [
            Natives::ZEND_TYPE_NULL => null,
            Natives::ZEND_TYPE_FALSE => false,
            Natives::ZEND_TYPE_TRUE => true,
            Natives::ZEND_TYPE_LONG => 42,
            Natives::ZEND_TYPE_DOUBLE => 4.5,
        ];
        foreach ($types as $type => $value) {
            $literal = $ffi->new('zval');
            $literal->u1->v->type = $type;
            if (is_int($value)) {
                $literal->value->lval = $value;
            } elseif (is_float($value)) {
                $literal->value->dval = $value;
            }
            expect($invoke('constantValue', $ffi, $literal))->toBe($value);
        }

        $arrayLiteral = $ffi->new('zval');
        $arrayLiteral->u1->v->type = Natives::ZEND_TYPE_ARRAY;
        expect($invoke('constantValue', $ffi, $arrayLiteral))->toBe([]);

        $array = $ffi->new('HashTable');
        $arrayLiteral->value->ptr = FFI::addr($array);
        expect($invoke('constantValue', $ffi, $arrayLiteral))->toBe('ARRAY');

        /** @var \Zendful_FFI\zval_pointer $packedValues */
        $packedValues = $ffi->new('zval[2]');
        $packedValues[0]->u1->v->type = Natives::ZEND_TYPE_LONG;
        $packedValues[0]->value->lval = 1;
        $packedValues[1]->u1->v->type = Natives::ZEND_TYPE_TRUE;
        /** @var \Zendful_FFI\HashTable $packedArray */
        $packedArray = $ffi->new('HashTable');
        $packedArray->u->flags = Natives::HASH_FLAG_PACKED;
        $packedArray->nNumOfElements = 2;
        $packedArray->arPacked = $ffi->cast('zval *', FFI::addr($packedValues[0]));
        $arrayLiteral->value->ptr = FFI::addr($packedArray);
        expect($invoke('constantValue', $ffi, $arrayLiteral))->toBe([1, true]);

        $unknown = $ffi->new('zval');
        $unknown->u1->v->type = Natives::ZEND_TYPE_OBJECT;
        expect($invoke('constantValue', $ffi, $unknown))->toBe('TYPE(' . Natives::ZEND_TYPE_OBJECT . ')');
        expect($invoke('align', 9, 8))->toBe(16);
    });

    it('validates detached assembly data and operand semantics defensively', function (): void {
        $invoke = static function (string $name, mixed ...$arguments): mixed {
            $method = new ReflectionMethod(ZendfulExecutor::class, $name);

            return $method->invoke(null, ...$arguments);
        };
        $operand = static function (int $type, string $kind, mixed $value = 0, ?int $rawValue = null, ?int $literalSlot = null): array {
            return [
                'type' => $type,
                'kind' => $kind,
                'value' => $value,
                'rawValue' => $rawValue,
                'literalSlot' => $literalSlot,
            ];
        };
        $instruction = [
            'opcode' => 0,
            'extendedValue' => 0,
            'line' => 0,
            'originalIndex' => 0,
            'result' => $operand(Natives::ZEND_IS_UNUSED, 'unused'),
            'operand1' => $operand(Natives::ZEND_IS_CONST, 'constant', 'literal', null, 0),
            'operand2' => $operand(Natives::ZEND_IS_CV, 'cv', 1),
        ];

        expect($invoke('validateAssemblyData', [$instruction], [0 => 'literal'], 1, 1))
            ->toBe(1)
            ->and($invoke('validateInstruction', $instruction, 1))->toBeArray()
            ->and($invoke('validateOperand', $operand(Natives::ZEND_IS_VAR, 'variable', 2)))->toBeArray()
            ->and($invoke('validateOperand', $operand(Natives::ZEND_IS_TMP_VAR, 'temporary', 3)))->toBeArray()
            ->and($invoke('validateOperand', $operand(Natives::ZEND_IS_CV, 'cv', 4)))->toBeArray()
            ->and($invoke('validateOperand', $operand(Natives::ZEND_IS_UNUSED, 'raw', 5)))->toBeArray()
            ->and($invoke('isValidOperandType', Natives::ZEND_IS_UNUSED, false))->toBeTrue()
            ->and($invoke('isValidOperandType', Natives::ZEND_IS_UNUSED | Natives::ZEND_IS_SMART_BRANCH_JMPZ, true))->toBeTrue()
            ->and($invoke('isValidOperandType', Natives::ZEND_OP_TYPE_MAX, false))->toBeFalse();

        expect(static function () use ($invoke, $instruction): void { $invoke('validateAssemblyData', [[...$instruction, 'originalIndex' => 2]], [], 1, 1); })
            ->toThrow(InvalidArgumentException::class)
            ->and(static function () use ($invoke, $instruction, $operand): void { $invoke('validateAssemblyData', [[...$instruction, 'operand1' => $operand(Natives::ZEND_IS_CONST, 'constant', 'missing', null, 2)]], [], 1, 1); })
            ->toThrow(InvalidArgumentException::class)
            ->and(static function () use ($invoke, $operand): void { $invoke('validateOperand', $operand(Natives::ZEND_IS_CONST, 'constant', new stdClass(), null, 0)); })
            ->toThrow(InvalidArgumentException::class)
            ->and(static function () use ($invoke, $operand): void { $invoke('validateOperand', $operand(Natives::ZEND_IS_UNUSED, 'constant', null, null, null)); })
            ->toThrow(InvalidArgumentException::class)
            ->and(static function () use ($invoke, $operand): void { $invoke('validateOperand', $operand(Natives::ZEND_IS_VAR, 'variable', PHP_INT_MAX)); })
            ->toThrow(InvalidArgumentException::class)
            ->and(static function () use ($invoke): void { $invoke('validateAssemblyData', 'invalid', [], 1, 1); })
            ->toThrow(InvalidArgumentException::class)
            ->and(static function () use ($invoke): void { $invoke('validateAssemblyData', [], 'invalid', 1, 1); })
            ->toThrow(InvalidArgumentException::class)
            ->and(static function () use ($invoke): void { $invoke('validateAssemblyData', [], [-1 => null], 1, 1); })
            ->toThrow(InvalidArgumentException::class)
            ->and(static function () use ($invoke): void { $invoke('validateAssemblyData', [], [0 => new stdClass()], 1, 1); })
            ->toThrow(InvalidArgumentException::class)
            ->and(static function () use ($invoke): void { $invoke('validateAssemblyData', [], [0 => "bad\0literal"], 1, 1); })
            ->toThrow(InvalidArgumentException::class)
            ->and(static function () use ($invoke): void { $invoke('validateInstruction', [], 1); })
            ->toThrow(InvalidArgumentException::class)
            ->and(static function () use ($invoke, $instruction): void { $invoke('validateInstruction', [...$instruction, 'opcode' => -1], 1); })
            ->toThrow(InvalidArgumentException::class)
            ->and(static function () use ($invoke, $instruction): void { $invoke('validateInstruction', [...$instruction, 'originalIndex' => -1], 1); })
            ->toThrow(InvalidArgumentException::class)
            ->and(static function () use ($invoke): void { $invoke('validateOperand', [], false); })
            ->toThrow(InvalidArgumentException::class)
            ->and(static function () use ($invoke, $operand): void { $invoke('validateOperand', [...$operand(Natives::ZEND_IS_VAR, 'raw', 1), 'rawValue' => 'invalid'], false); })
            ->toThrow(InvalidArgumentException::class)
            ->and(static function () use ($invoke, $operand): void { $invoke('validateOperand', $operand(Natives::ZEND_IS_CONST, 'variable', 1), false); })
            ->toThrow(InvalidArgumentException::class)
            ->and(static function () use ($invoke, $operand): void { $invoke('validateOperand', $operand(Natives::ZEND_IS_VAR, 'variable', 'not-an-int'), false); })
            ->toThrow(InvalidArgumentException::class);
    });

    it('rejects invalid allocation sizes and missing literal storage before FFI writes', function (): void {
        $invoke = static function (string $name, mixed ...$arguments): mixed {
            $method = new ReflectionMethod(ZendfulExecutor::class, $name);

            return $method->invoke(null, ...$arguments);
        };
        $validPlan = new AssemblyPlanHandle(0, 0, [], []);
        $oversizedPlan = new AssemblyPlanHandle(Natives::ZEND_UINT32_MAX + 1, 0, [], []);

        expect($invoke('validateAssemblyPlan', $validPlan, 0, 0))->toBe(0)
            ->and($invoke('requireLiteralPointer', $literalStorage = new stdClass()))->toBe($literalStorage)
            ->and(static function () use ($invoke): void { $invoke('requireLiteralPointer', null); })
            ->toThrow(RuntimeException::class)
            ->and(static function () use ($invoke, $oversizedPlan): void { $invoke('validateAssemblyPlan', $oversizedPlan, 0, 0); })
            ->toThrow(InvalidArgumentException::class);
    });

    it('exercises the typed runtime queries and reversible flag operations', function (): void {
        $function = new \Zendful\FunctionHandle('executorCoverageFirst');
        $missingFunction = new \Zendful\FunctionHandle('missingExecutorFunction');
        $class = new \Zendful\ClassHandle(ExecutorInterfaceInstallTarget::class);
        $method = new \Zendful\MethodHandle(ExecutorMutableTarget::class, 'run');
        $property = new \Zendful\PropertyHandle(ExecutorMutableTarget::class, 'value');

        expect(ZendfulExecutor::opcodeName(0))->toBeString()
            ->and(ZendfulExecutor::opcodeId('NOP'))->toBeInt()
            ->and(ZendfulExecutor::functionExists($function))->toBeTrue()
            ->and(ZendfulExecutor::functionExists($missingFunction))->toBeFalse()
            ->and(ZendfulExecutor::isUserDefined($function))->toBeTrue()
            ->and(ZendfulExecutor::isUserDefined($missingFunction))->toBeFalse()
            ->and(ZendfulExecutor::methodExists($method))->toBeTrue()
            ->and(ZendfulExecutor::propertyExists($property))->toBeTrue()
            ->and(ZendfulExecutor::classExists($class))->toBeTrue()
            ->and(ZendfulExecutor::isUserDefinedMethod($method))->toBeTrue()
            ->and(ZendfulExecutor::methodHasBytecode($method))->toBeTrue()
            ->and(ZendfulExecutor::methodHasBytecode(new \Zendful\MethodHandle(\DateTime::class, 'format')))->toBeFalse()
            ->and(ZendfulExecutor::hasBytecode(new \Zendful\FunctionHandle('strlen')))->toBeFalse()
            ->and(ZendfulExecutor::propertyHasHooks($property))->toBeFalse()
            ->and(ZendfulExecutor::classIsImmutable($class))->toBeFalse()
            ->and(ZendfulExecutor::isUserDefinedMethod(new \Zendful\MethodHandle(ExecutorMutableTarget::class, 'missing')))->toBeFalse();

        expect(static function (): void { ZendfulExecutor::clearPropertyVisibility(new \Zendful\PropertyHandle(ExecutorMutableTarget::class, 'missing')); })
            ->toThrow(InvalidArgumentException::class)
            ->and(static function (): void { ZendfulExecutor::setMethodVisibility(new \Zendful\MethodHandle(ExecutorMutableTarget::class, 'missing'), 'public'); })
            ->toThrow(InvalidArgumentException::class);

        ZendfulExecutor::setClassFinal($class, true);
        ZendfulExecutor::setClassFinal($class, false);
        ZendfulExecutor::setClassAbstract($class, true);
        ZendfulExecutor::setClassAbstract($class, false);
        ZendfulExecutor::setClassKind($class, 'interface');
        ZendfulExecutor::setClassKind($class, null);
        ZendfulExecutor::setClassAnonymous($class, false);
        ZendfulExecutor::setPropertyVisibility($property, 'private');
        ZendfulExecutor::clearPropertyVisibility($property);
        ZendfulExecutor::setPropertyReadonly($property, false);
        ZendfulExecutor::withPropertyVisibility($property, 'public', static fn(): string => 'property');
        ZendfulExecutor::setMethodVisibility($method, 'protected');
        ZendfulExecutor::clearMethodVisibility($method);
        ZendfulExecutor::setMethodStatic($method, true);
        ZendfulExecutor::setMethodStatic($method, false);
        ZendfulExecutor::setMethodFinal($method, true);
        ZendfulExecutor::setMethodFinal($method, false);

        expect(ZendfulExecutor::withMethodVisibility($method, 'public', static fn(): string => 'method'))
            ->toBe('method');
    });

    it('exercises class static initialization and rejected readonly transitions', function (): void {
        $class = new \Zendful\ClassHandle(ExecutorReadonlyTarget::class);
        $property = new \Zendful\PropertyHandle(ExecutorReadonlyTarget::class, 'value');

        expect(ZendfulExecutor::classStaticsInitialized($class))->toBeBool();
        ZendfulExecutor::initializeClassStatics($class);

        expect(static function () use ($class): mixed {
            ZendfulExecutor::setClassReadonly($class, true);

        })
            ->toThrow(InvalidArgumentException::class)
            ->and(static function () use ($property): mixed {
                ZendfulExecutor::setPropertySetVisibility($property, 'public', true);

            })
            ->toThrow(InvalidArgumentException::class);
    });

    it('installs declared interfaces and reports missing class metadata', function (): void {
        $class = new \Zendful\ClassHandle(ExecutorInterfaceInstallTarget::class);
        $interface = new \Zendful\ClassHandle(ExecutorContract::class);

        ZendfulExecutor::implementInterface($class, $interface);
        ZendfulExecutor::implementInterface($class, $interface);
        $class->implementInterface($interface);

        expect(static function (): void { ZendfulExecutor::classIsImmutable(new \Zendful\ClassHandle('ExecutorNotLoadedTarget')); })
            ->toThrow(InvalidArgumentException::class)
            ->and(static function (): void { ZendfulExecutor::setClassFinal(new \Zendful\ClassHandle('ExecutorNotLoadedTarget'), true); })
            ->toThrow(InvalidArgumentException::class)
            ->and(static function (): void { ZendfulExecutor::classStaticsInitialized(new \Zendful\ClassHandle('ExecutorNotLoadedTarget')); })
            ->toThrow(InvalidArgumentException::class)
            ->and(static function (): void { ZendfulExecutor::implementInterface(
                new \Zendful\ClassHandle(ExecutorInterfaceInstallTarget::class),
                new \Zendful\ClassHandle(\DateTime::class),
            ); })->toThrow(InvalidArgumentException::class)
            ->and(static function () use ($class): void { ZendfulExecutor::setClassKind($class, 'enum'); })
            ->toThrow(InvalidArgumentException::class);
    });

    it('covers invalid runtime metadata and mutation combinations', function (): void {
        $staticProperty = new \Zendful\PropertyHandle(ExecutorStaticPropertyTarget::class, 'value');
        $abstractMethod = new \Zendful\MethodHandle(ExecutorAbstractMethodTarget::class, 'run');
        $missing = new \Zendful\FunctionHandle('executorFunctionThatDoesNotExist');

        expect(static function () use ($staticProperty): void { ZendfulExecutor::setPropertyReadonly($staticProperty, true); })
            ->toThrow(InvalidArgumentException::class)
            ->and(static function () use ($abstractMethod): void { ZendfulExecutor::setMethodFinal($abstractMethod, true); })
            ->toThrow(InvalidArgumentException::class)
            ->and(static function () use ($missing): void { ZendfulExecutor::opArrayMetadata($missing); })
            ->toThrow(InvalidArgumentException::class)
            ->and(static function (): void { ZendfulExecutor::installMethod(
                new \Zendful\MethodHandle(\DateTime::class, 'format'),
                new \Zendful\ClassHandle(ExecutorInstallTarget::class),
                'internalMethod',
                false,
            ); })->toThrow(InvalidArgumentException::class);
    });

    it('rejects untyped properties, unrelated method swaps, and function mutations', function (): void {
        $untyped = new \Zendful\PropertyHandle(ExecutorUntypedPropertyTarget::class, 'value');

        expect(static function () use ($untyped): void { ZendfulExecutor::setPropertyReadonly($untyped, true); })
            ->toThrow(InvalidArgumentException::class)
            ->and(static function (): void { ZendfulExecutor::setPropertyReadonly(new \Zendful\PropertyHandle(ExecutorMutableTarget::class, 'missing'), true); })
            ->toThrow(InvalidArgumentException::class)
            ->and(static function (): void { ZendfulExecutor::swapMethods(
                new \Zendful\MethodHandle(ExecutorMutableTarget::class, 'run'),
                new \Zendful\MethodHandle(ExecutorInstallSource::class, 'source'),
            ); })->toThrow(InvalidArgumentException::class)
            ->and(static function (): void { ZendfulExecutor::swapFunctions(
                new \Zendful\FunctionHandle('strlen'),
                new \Zendful\FunctionHandle('executorCoverageFirst'),
            ); })->toThrow(InvalidArgumentException::class);

        $writableClassInfo = new ReflectionMethod(ZendfulExecutor::class, 'writableClassInfo');
        expect(static function () use ($writableClassInfo): void { $writableClassInfo->invoke(null, new \Zendful\ClassHandle('ExecutorNotLoadedTarget')); })
            ->toThrow(InvalidArgumentException::class)
            ->and(ZendfulExecutor::methodHasBytecode(new \Zendful\MethodHandle(ExecutorMutableTarget::class, 'run')))
            ->toBeTrue();

        ZendfulExecutor::swapFunctions(
            new \Zendful\FunctionHandle('executorCoverageFirst'),
            new \Zendful\FunctionHandle('executorCoverageSecond'),
        );
        ZendfulExecutor::swapFunctions(
            new \Zendful\FunctionHandle('executorCoverageFirst'),
            new \Zendful\FunctionHandle('executorCoverageSecond'),
        );
    });

    it('rejects renaming a method onto an existing method', function (): void {
        expect(static function (): void { ZendfulExecutor::renameMethod(
            new \Zendful\MethodHandle(ExecutorRenameTarget::class, 'first'),
            'second',
        ); })->toThrow(InvalidArgumentException::class);
    });

    it('replaces an inherited method entry when installing a method', function (): void {
        ZendfulExecutor::installMethod(
            new \Zendful\MethodHandle(ExecutorInstallSource::class, 'source'),
            new \Zendful\ClassHandle(ExecutorInheritedTarget::class),
            'existing',
            false,
        );

        expect((new \Zendful\MethodHandle(ExecutorInheritedTarget::class, 'existing'))->exists())->toBeTrue();
    });

    it('rejects installing over a directly declared method', function (): void {
        expect(static function (): void { ZendfulExecutor::installMethod(
            new \Zendful\MethodHandle(ExecutorInstallSource::class, 'source'),
            new \Zendful\ClassHandle(ExecutorDirectCollisionTarget::class),
            'existing',
            false,
        ); })->toThrow(InvalidArgumentException::class);
    });

    it('accepts a valid readonly class transition', function (): void {
        $class = new \Zendful\ClassHandle(ExecutorReadonlyValidTarget::class);

        ZendfulExecutor::setClassReadonly($class, true);

        expect((new ReflectionClass(ExecutorReadonlyValidTarget::class))->isReadOnly())->toBeTrue();
    });

    it('installs, renames, and generates user methods through handles', function (): void {
        $source = new \Zendful\MethodHandle(ExecutorInstallSource::class, 'source');
        $target = new \Zendful\ClassHandle(ExecutorInstallTarget::class);

        ZendfulExecutor::installMethod($source, $target, 'installed', true);
        expect((new \Zendful\MethodHandle(ExecutorInstallTarget::class, 'installed'))->exists())
            ->toBeTrue();

        ZendfulExecutor::renameMethod(
            new \Zendful\MethodHandle(ExecutorInstallTarget::class, 'installed'),
            'renamed',
        );
        expect((new \Zendful\MethodHandle(ExecutorInstallTarget::class, 'renamed'))->exists())
            ->toBeTrue();

        ZendfulExecutor::installGeneratedMethod(
            $source,
            $source,
            $target,
            'generated',
        );
        expect((new \Zendful\MethodHandle(ExecutorInstallTarget::class, 'generated'))->exists())
            ->toBeTrue();

        expect(static function () use ($source, $target): void { ZendfulExecutor::installGeneratedMethod(
            $source,
            $source,
            $target,
            'generated',
        ); })->toThrow(InvalidArgumentException::class);

        expect(static function () use ($target): void { ZendfulExecutor::installMethod(
            new \Zendful\MethodHandle(ExecutorInstallTarget::class, 'missing'),
            $target,
            'missing',
            false,
        ); })->toThrow(InvalidArgumentException::class)
            ->and(static function () use ($source, $target): void { ZendfulExecutor::installGeneratedMethod(
                new \Zendful\MethodHandle(ExecutorInstallTarget::class, 'missing'),
                $source,
                $target,
                'missing',
            ); })->toThrow(InvalidArgumentException::class);

        expect(static function (): void { ZendfulExecutor::renameMethod(
            new \Zendful\MethodHandle(ExecutorInstallTarget::class, 'missing'),
            'renamed',
        ); })->toThrow(InvalidArgumentException::class);
    });

    it('covers executor validation entrypoints and no-op branches', function (): void {
        $method = new \Zendful\MethodHandle(ExecutorMutableTarget::class, 'run');
        $function = new \Zendful\FunctionHandle('executorCoverageFirst');
        $class = new \Zendful\ClassHandle(ExecutorMutableTarget::class);
        $property = new \Zendful\PropertyHandle(ExecutorMutableTarget::class, 'value');

        $blacklistedMethods = new ReflectionProperty(ZendfulExecutor::class, 'blacklistedMethods');
        $blacklistedMethods->setValue(null, []);
        $blacklistedClasses = new ReflectionProperty(ZendfulExecutor::class, 'blacklistedClasses');
        $blacklistedClasses->setValue(null, []);

        ZendfulExecutor::disableJitForMethod($method);
        ZendfulExecutor::disableJitForFunction($function);
        ZendfulExecutor::disableJitForClass($class);

        expect(static function (): void { ZendfulExecutor::opcodeId('not-an-opcode'); })
            ->toThrow(InvalidArgumentException::class)
            ->and(static function (): void {
                ZendfulExecutor::setClassFinal(new \Zendful\ClassHandle(ExecutorAbstractTarget::class), true);

            })
            ->toThrow(InvalidArgumentException::class)
            ->and(static function (): void {
                ZendfulExecutor::setClassAbstract(new \Zendful\ClassHandle(ExecutorFinalTarget::class), true);

            })
            ->toThrow(InvalidArgumentException::class)
            ->and(static function () use ($class): mixed {
                ZendfulExecutor::setClassKind($class, 'invalid');

            })
            ->toThrow(InvalidArgumentException::class)
            ->and(static function () use ($class): mixed {
                ZendfulExecutor::setClassAnonymous($class, true);

            })
            ->toThrow(InvalidArgumentException::class)
            ->and(static function () use ($property): mixed {
                ZendfulExecutor::setPropertyVisibility($property, 'invalid');

            })
            ->toThrow(InvalidArgumentException::class)
            ->and(static function () use ($method): mixed {
                ZendfulExecutor::setMethodVisibility($method, 'invalid');

            })
            ->toThrow(InvalidArgumentException::class)
            ->and(static function () use ($property): mixed {
                ZendfulExecutor::setPropertySetVisibility($property, 'public', false);

            })
            ->toThrow(InvalidArgumentException::class);

        ZendfulExecutor::setClassKind($class, null);
        ZendfulExecutor::setClassKind($class, 'interface');
        expect(static function () use ($class): mixed {
            ZendfulExecutor::setClassKind($class, 'trait');

        })->toThrow(InvalidArgumentException::class);
        ZendfulExecutor::setClassKind($class, null);

        ZendfulExecutor::swapMethods($method, $method);
        ZendfulExecutor::swapFunctions($function, $function);
        ZendfulExecutor::renameMethod($method, 'run');

        expect(static function () use ($method): mixed {
            ZendfulExecutor::swapMethods(
                $method,
                new \Zendful\MethodHandle(ExecutorMissingTarget::class, 'missing'),
            );

        })->toThrow(InvalidArgumentException::class);
    });

    it('assembles a detached allocation with copied and new literals', function (): void {
        $opArray = new \Zendful\FunctionHandle('executorCoverageAssemblyTarget')->opArray();
        $instructions = [];
        $operand = static function (\Zendful\OperandHandle $operand): array {
            $kind = match ($operand->type()) {
                Natives::ZEND_IS_UNUSED => 'unused',
                Natives::ZEND_IS_CONST => 'constant',
                Natives::ZEND_IS_TMP_VAR => 'temporary',
                Natives::ZEND_IS_VAR => 'variable',
                Natives::ZEND_IS_CV => 'cv',
                default => 'raw',
            };

            return [
                'type' => $operand->type(),
                'kind' => $kind,
                'value' => $kind === 'constant' ? $operand->value() : $operand->number(),
                'rawValue' => null,
                'literalSlot' => $operand->literalIndex(),
            ];
        };

        for ($index = 0; $index < $opArray->instructionCount(); $index++) {
            $opcode = $opArray->opcode($index);
            $instructions[] = [
                'opcode' => $opcode->opcode(),
                'extendedValue' => $opcode->extendedValue(),
                'line' => $opcode->line(),
                'originalIndex' => $index,
                'result' => $operand($opcode->result()),
                'operand1' => $operand($opcode->operand1()),
                'operand2' => $operand($opcode->operand2()),
            ];
        }

        $newLiteralSlot = $opArray->literalCount();
        $extra = $instructions[0];
        $extra['originalIndex'] = 0;
        $extra['operand1'] = [
            'type' => Natives::ZEND_IS_CONST,
            'kind' => 'constant',
            'value' => 'added literal',
            'rawValue' => null,
            'literalSlot' => $newLiteralSlot,
        ];
        $instructions[] = $extra;

        $plan = new AssemblyPlanHandle(
            $opArray->temporaryCount(),
            max(1, $opArray->cacheSize()),
            $instructions,
            [$newLiteralSlot => 'added literal'],
        );

        expect(static function () use ($opArray, $plan): mixed {
            $opArray->assemble($plan);

        })->not->toThrow(Throwable::class);
    });

    it('rewrites an existing opcode allocation without replacing its storage', function (): void {
        $opArray = new \Zendful\FunctionHandle('executorCoverageFirst')->opArray();
        $instructions = [];

        $operand = static function (\Zendful\OperandHandle $operand): array {
            $kind = match ($operand->type()) {
                Natives::ZEND_IS_UNUSED => 'unused',
                Natives::ZEND_IS_CONST => 'constant',
                Natives::ZEND_IS_TMP_VAR => 'temporary',
                Natives::ZEND_IS_VAR => 'variable',
                Natives::ZEND_IS_CV => 'cv',
                default => 'raw',
            };

            return [
                'type' => $operand->type(),
                'kind' => $kind,
                'value' => $kind === 'constant' ? $operand->value() : $operand->number(),
                'rawValue' => null,
                'literalSlot' => $operand->literalIndex(),
            ];
        };

        for ($index = 0; $index < $opArray->instructionCount(); $index++) {
            $opcode = $opArray->opcode($index);
            $instructions[] = [
                'opcode' => $opcode->opcode(),
                'extendedValue' => $opcode->extendedValue(),
                'line' => $opcode->line(),
                'originalIndex' => $index,
                'result' => $operand($opcode->result()),
                'operand1' => $operand($opcode->operand1()),
                'operand2' => $operand($opcode->operand2()),
            ];
        }

        $plan = new AssemblyPlanHandle(
            $opArray->temporaryCount(),
            $opArray->cacheSize(),
            $instructions,
            [],
        );

        expect(static function () use ($opArray, $plan): mixed {
            $opArray->assemble($plan);

        })->not->toThrow(Throwable::class);
    });

    it('allocates a changed instruction stream without allocating literals', function (): void {
        $opArray = new \Zendful\FunctionHandle('executorCoverageNoLiteralAssemblyTarget')->opArray();
        $operand = static function (\Zendful\OperandHandle $operand): array {
            $kind = match ($operand->type()) {
                Natives::ZEND_IS_UNUSED => 'unused',
                Natives::ZEND_IS_CONST => 'constant',
                Natives::ZEND_IS_TMP_VAR => 'temporary',
                Natives::ZEND_IS_VAR => 'variable',
                Natives::ZEND_IS_CV => 'cv',
                default => 'raw',
            };

            return [
                'type' => $operand->type(),
                'kind' => $kind,
                'value' => $kind === 'constant' ? $operand->value() : $operand->number(),
                'rawValue' => null,
                'literalSlot' => $operand->literalIndex(),
            ];
        };
        $opcode = $opArray->opcode(0);
        $unused = [
            'type' => Natives::ZEND_IS_UNUSED,
            'kind' => 'unused',
            'value' => 0,
            'rawValue' => null,
            'literalSlot' => null,
        ];
        $instructions = [[
            'opcode' => $opcode->opcode(),
            'extendedValue' => $opcode->extendedValue(),
            'line' => $opcode->line(),
            'originalIndex' => 0,
            'result' => $unused,
            'operand1' => $unused,
            'operand2' => $unused,
        ]];

        $opArray->assemble(new AssemblyPlanHandle($opArray->temporaryCount(), 0, $instructions, []));

        expect($opArray->instructionCount())->toBe(count($instructions));
    });

    it('resolves inherited and interface method hierarchies', function (): void {
        $method = new ReflectionMethod(ZendfulExecutor::class, 'classDerivesFrom');

        expect($method->invoke(null, ExecutorMutableTarget::class, ExecutorMutableTarget::class))->toBeTrue()
            ->and($method->invoke(null, ExecutorDerivedTarget::class, ExecutorMutableTarget::class))->toBeTrue()
            ->and($method->invoke(null, ExecutorInterfaceTarget::class, ExecutorContract::class))->toBeTrue()
            ->and($method->invoke(null, ExecutorMutableTarget::class, ExecutorContract::class))->toBeFalse();
    });

    it('captures named local variables from op-array metadata', function (): void {
        $metadata = ZendfulExecutor::opArrayMetadata(
            new \Zendful\FunctionHandle('executorCoverageWithVariable'),
        );

        expect($metadata['variableCount'])->toBeGreaterThan(0)
            ->and($metadata['variableNames'])->not->toBeEmpty();
    });

    it('formats array literals through real opcode metadata', function (): void {
        $opArray = new \Zendful\FunctionHandle('executorCoverageLiteralArray')->opArray();
        $descriptions = [];
        for ($index = 0; $index < $opArray->instructionCount(); $index++) {
            $opcode = $opArray->opcode($index);
            $descriptions[] = $opcode->result()->constantDescription();
            $descriptions[] = $opcode->operand1()->constantDescription();
            $descriptions[] = $opcode->operand2()->constantDescription();
        }

        expect(array_filter(
            $descriptions,
            static fn(string $description): bool => str_contains($description, 'ARRAY'),
        ))->not->toBeEmpty();
    });

    it('formats object and resource constants without dereferencing them', function (): void {
        $ffi = Natives::ffi();
        $operandMethod = new ReflectionMethod(ZendfulExecutor::class, 'operand');
        /** @var \Zendful_FFI\zend_op_array $opArray */
        $opArray = $ffi->new('zend_op_array');
        $opline = $ffi->new('zend_op');
        $opArray->opcodes = $ffi->cast('zend_op *', FFI::addr($opline));

        foreach ([Natives::ZEND_TYPE_OBJECT => 'OBJECT', Natives::ZEND_TYPE_RESOURCE => 'RESOURCE'] as $type => $description) {
            $literal = $ffi->new('zval');
            $literal->u1->v->type = $type;
            $oplineAddress = $ffi->cast('uintptr_t', FFI::addr($opline))->cdata;
            $literalAddress = $ffi->cast('uintptr_t', FFI::addr($literal))->cdata;
            $rawOperand = $ffi->new('znode_op');
            $rawOperand->constant = $literalAddress - $oplineAddress;

            /** @var \Zendful\OperandHandle $result */
            $result = $operandMethod->invoke(
                null,
                $ffi,
                $opArray,
                0,
                $opline,
                Natives::ZEND_IS_CONST,
                $rawOperand,
            );

            expect($result->constantDescription())->toContain($description);
        }
    });

    it('formats scalar constants through opcode operand metadata', function (): void {
        $ffi = Natives::ffi();
        $operandMethod = new ReflectionMethod(ZendfulExecutor::class, 'operand');
        /** @var \Zendful_FFI\zend_op_array $opArray */
        $opArray = $ffi->new('zend_op_array');
        $opline = $ffi->new('zend_op');
        $opArray->opcodes = $ffi->cast('zend_op *', FFI::addr($opline));

        foreach ([
            Natives::ZEND_TYPE_NULL => 'NULL',
            Natives::ZEND_TYPE_FALSE => 'false',
            Natives::ZEND_TYPE_TRUE => 'true',
            Natives::ZEND_TYPE_LONG => '7',
            Natives::ZEND_TYPE_DOUBLE => '1.5',
        ] as $type => $description) {
            $literal = $ffi->new('zval');
            $literal->u1->v->type = $type;
            if ($type === Natives::ZEND_TYPE_LONG) {
                $literal->value->lval = 7;
            }
            if ($type === Natives::ZEND_TYPE_DOUBLE) {
                $literal->value->dval = 1.5;
            }
            $offset = $ffi->cast('uintptr_t', FFI::addr($literal))->cdata
                - $ffi->cast('uintptr_t', FFI::addr($opline))->cdata;
            $rawOperand = $ffi->new('znode_op');
            $rawOperand->constant = $offset;

            /** @var \Zendful\OperandHandle $result */
            $result = $operandMethod->invoke(null, $ffi, $opArray, 0, $opline, Natives::ZEND_IS_CONST, $rawOperand);

            expect($result->constantDescription())->toContain($description);
        }
    });

    it('covers empty Zend strings, unchanged metadata, and released hash keys', function (): void {
        $ffi = Natives::ffi();
        $invoke = static function (string $name, mixed ...$arguments): mixed {
            $method = new ReflectionMethod(ZendfulExecutor::class, $name);

            return $method->invoke(null, ...$arguments);
        };
        /** @var \Zendful_FFI\zend_string $empty */
        $empty = $ffi->new('zend_string');
        $empty->len = 0;

        expect($invoke('zendString', $ffi, $empty))->toBe('')
            ->and($invoke('relocateMetadata', $ffi->new('zend_op_array'), [], 0))->toBeNull();

        $key = $ffi->zend_strpprintf(16, '%s', 'released-key');
        expect($invoke('releaseKey', $ffi, $key))->toBeNull();
    });

    it('relocates live-range metadata and chooses the next mapped instruction', function (): void {
        $ffi = Natives::ffi();
        $invoke = static function (string $name, mixed ...$arguments): mixed {
            $method = new ReflectionMethod(ZendfulExecutor::class, $name);

            return $method->invoke(null, ...$arguments);
        };
        /** @var \Zendful_FFI\zend_op_array $opArray */
        $opArray = $ffi->new('zend_op_array');
        $opArray->last_live_range = 1;
        $liveRangeStorage = $ffi->new('zend_live_range[1]');
        /** @var \Zendful_FFI\zend_live_range_pointer $liveRange */
        $liveRange = $ffi->cast('zend_live_range *', $liveRangeStorage);
        $opArray->live_range = $liveRange;
        $opArray->live_range[0]->start = 1;
        $opArray->live_range[0]->end = 0;

        $instructions = [
            [
                'opcode' => 0,
                'extendedValue' => 0,
                'line' => 1,
                'originalIndex' => 1,
                'result' => ['type' => Natives::ZEND_IS_UNUSED, 'kind' => 'unused', 'value' => null, 'rawValue' => null, 'literalSlot' => null],
                'operand1' => ['type' => Natives::ZEND_IS_UNUSED, 'kind' => 'unused', 'value' => null, 'rawValue' => null, 'literalSlot' => null],
                'operand2' => ['type' => Natives::ZEND_IS_UNUSED, 'kind' => 'unused', 'value' => null, 'rawValue' => null, 'literalSlot' => null],
            ],
            [
                'opcode' => 0,
                'extendedValue' => 0,
                'line' => 1,
                'originalIndex' => null,
                'result' => ['type' => Natives::ZEND_IS_UNUSED, 'kind' => 'unused', 'value' => null, 'rawValue' => null, 'literalSlot' => null],
                'operand1' => ['type' => Natives::ZEND_IS_UNUSED, 'kind' => 'unused', 'value' => null, 'rawValue' => null, 'literalSlot' => null],
                'operand2' => ['type' => Natives::ZEND_IS_UNUSED, 'kind' => 'unused', 'value' => null, 'rawValue' => null, 'literalSlot' => null],
            ],
        ];

        expect($invoke('relocateMetadata', $opArray, $instructions, 3))->toBeNull()
            ->and($opArray->live_range[0]->start)->toBe(0)
            ->and($opArray->live_range[0]->end)->toBe(0);
    });

    it('reports an absent property on a loaded class', function (): void {
        $method = new ReflectionMethod(ZendfulExecutor::class, 'propertyInfo');

        expect($method->invoke(null, new \Zendful\PropertyHandle(ExecutorMutableTarget::class, 'missing')))
            ->toBeNull();
    });

    it('rejects assembly before touching unavailable or internal runtime callables', function (): void {
        $assembly = new AssemblyPlanHandle(0, 0, [], []);
        $source = new ReflectionProperty(\Zendful\OpArrayHandle::class, 'source');
        $make = static function (FunctionHandle|MethodHandle $callable) use ($source): \Zendful\OpArrayHandle {
            $handle = (new ReflectionClass(\Zendful\OpArrayHandle::class))->newInstanceWithoutConstructor();
            $source->setValue($handle, $callable);

            return $handle;
        };
        $assemble = new ReflectionMethod(ZendfulExecutor::class, 'assemble');

        expect(static function () use ($assemble, $make, $assembly): void { $assemble->invoke(
            null,
            $make(new FunctionHandle('ZendfulNeverLoadedAssemblyFunction')),
            $assembly,
        ); })->toThrow(InvalidArgumentException::class)
            ->and(static function () use ($assemble, $make, $assembly): void { $assemble->invoke(
                null,
                $make(new FunctionHandle('strlen')),
                $assembly,
            ); })->toThrow(RuntimeException::class);
    });

    it('fails closed for missing method, property, and class metadata', function (): void {
        $invoke = static function (string $name, mixed ...$arguments): mixed {
            $method = new ReflectionMethod(ZendfulExecutor::class, $name);

            return $method->invoke(null, ...$arguments);
        };
        $missingClass = new \Zendful\ClassHandle('ZendfulNeverLoadedForMetadata');
        $missingMethod = new \Zendful\MethodHandle('ZendfulNeverLoadedForMetadata', 'missing');
        $missingProperty = new \Zendful\PropertyHandle('ZendfulNeverLoadedForMetadata', 'missing');

        expect($invoke('methodEntry', $missingMethod))->toBeNull()
            ->and($invoke('propertyInfo', $missingProperty))->toBeNull()
            ->and($invoke('classInfo', $missingClass))->toBeNull()
            ->and(static function () use ($invoke): void { $invoke('assertLoadedClassName', 'ZendfulNeverLoadedForMetadata'); })
            ->toThrow(InvalidArgumentException::class);
    });

    it('rejects immutable functions before swapping their table entries', function (): void {
        $ffi = Natives::ffi();
        $entryMethod = new ReflectionMethod(ZendfulExecutor::class, 'entry');
        /** @var \Zendful_FFI\zval $entry */
        $entry = $entryMethod->invoke(null, new \Zendful\FunctionHandle('executorCoverageFirst'), $ffi);
        /** @var \Zendful_FFI\zend_function $function */
        $function = $ffi->cast('zend_function *', $entry->value->ptr);
        $originalFlags = $function->fn_flags;
        $function->fn_flags |= Natives::ZEND_ACC_IMMUTABLE;
        $assertWritable = new ReflectionMethod(ZendfulExecutor::class, 'assertWritableFunction');

        try {
            expect(static function () use ($assertWritable, $entry): void { $assertWritable->invoke(null, $entry, 'immutable'); })
                ->toThrow(InvalidArgumentException::class);
        } finally {
            $function->fn_flags = $originalFlags;
        }
    });

    it('rejects swapping a function that is absent from Zend function storage', function (): void {
        expect(static function (): void {
            ZendfulExecutor::swapFunctions(
                new \Zendful\FunctionHandle('executorCoverageFirst'),
                new \Zendful\FunctionHandle('ZendfulNeverLoadedFunction'),
            );


        })->toThrow(InvalidArgumentException::class);
    });

    it('rejects immutable methods and reports missing function bytecode', function (): void {
        $ffi = Natives::ffi();
        $method = new \Zendful\MethodHandle(ExecutorMutableTarget::class, 'run');
        $entryMethod = new ReflectionMethod(ZendfulExecutor::class, 'methodEntry');
        /** @var \Zendful_FFI\zval $entry */
        $entry = $entryMethod->invoke(null, $method);
        /** @var \Zendful_FFI\zend_function $function */
        $function = $ffi->cast('zend_function *', $entry->value->ptr);
        $originalFlags = $function->fn_flags;
        $function->fn_flags |= Natives::ZEND_ACC_IMMUTABLE;
        $assertWritable = new ReflectionMethod(ZendfulExecutor::class, 'assertWritableMethod');

        try {
            expect(static function () use ($assertWritable, $method, $entry): void { $assertWritable->invoke(null, $method, $entry); })
                ->toThrow(InvalidArgumentException::class);
        } finally {
            $function->fn_flags = $originalFlags;
        }

        expect(ZendfulExecutor::hasBytecode(new \Zendful\FunctionHandle('ZendfulNeverLoadedFunction')))
            ->toBeFalse();
    });

    it('rejects installation into a class that is not loaded', function (): void {
        $source = new \Zendful\MethodHandle(ExecutorInstallSource::class, 'source');
        $missing = new \Zendful\ClassHandle('ZendfulNeverLoadedInstallTarget');

        expect(static function () use ($source, $missing): mixed {
            ZendfulExecutor::installMethod($source, $missing, 'installed', false);


        })
            ->toThrow(InvalidArgumentException::class)
            ->and(static function () use ($source, $missing): mixed {
                ZendfulExecutor::installGeneratedMethod($source, $source, $missing, 'generated');


            })->toThrow(InvalidArgumentException::class);
    });

    it('reports unavailable bytecode for missing and internal callables', function (): void {
        expect(ZendfulExecutor::methodHasBytecode(
            new \Zendful\MethodHandle('ZendfulNeverLoadedBytecodeTarget', 'missing'),
        ))->toBeFalse()
            ->and(ZendfulExecutor::hasBytecode(new \Zendful\FunctionHandle('ZendfulNeverLoadedBytecodeFunction')))
            ->toBeFalse()
            ->and(static function (): void { ZendfulExecutor::opArrayMetadata(
                new \Zendful\MethodHandle(\DateTime::class, 'format'),
            ); })->toThrow(RuntimeException::class);
    });

    it('rejects immutable classes and non-readonly parents', function (): void {
        $class = new \Zendful\ClassHandle(ExecutorMutableTarget::class);
        $classInfoMethod = new ReflectionMethod(ZendfulExecutor::class, 'classInfo');
        /** @var \Zendful_FFI\zend_class_entry $classInfo */
        $classInfo = $classInfoMethod->invoke(null, $class);
        $originalFlags = $classInfo->ce_flags;
        $classInfo->ce_flags |= Natives::ZEND_ACC_IMMUTABLE;
        $writable = new ReflectionMethod(ZendfulExecutor::class, 'writableClassInfo');

        try {
            expect(static function () use ($writable, $class): void { $writable->invoke(null, $class); })
                ->toThrow(InvalidArgumentException::class);
        } finally {
            $classInfo->ce_flags = $originalFlags;
        }

        $readonly = new \Zendful\ClassHandle(ExecutorReadonlyChildTarget::class);
        expect(static function () use ($readonly): mixed {
            ZendfulExecutor::setClassReadonly($readonly, true);


        })
            ->toThrow(InvalidArgumentException::class);

        expect(static function (): void {
            ZendfulExecutor::setClassReadonly(new \Zendful\ClassHandle(ExecutorStaticPropertyTarget::class), true);


        })->toThrow(InvalidArgumentException::class);
    });

    it('rejects private flag access for missing runtime members', function (): void {
        $missingMethod = new \Zendful\MethodHandle('ZendfulNeverLoadedFlagsTarget', 'missing');
        $missingProperty = new \Zendful\PropertyHandle('ZendfulNeverLoadedFlagsTarget', 'missing');
        $methodFlags = new ReflectionMethod(ZendfulExecutor::class, 'methodFlags');
        $setMethodFlags = new ReflectionMethod(ZendfulExecutor::class, 'setMethodFlags');
        $setPropertyFlags = new ReflectionMethod(ZendfulExecutor::class, 'setPropertyFlags');

        expect(static function () use ($methodFlags, $missingMethod): void { $methodFlags->invoke(null, $missingMethod); })
            ->toThrow(InvalidArgumentException::class)
            ->and(static function () use ($setMethodFlags, $missingMethod): void { $setMethodFlags->invoke(null, $missingMethod, 0); })
            ->toThrow(InvalidArgumentException::class)
            ->and(static function () use ($setPropertyFlags, $missingProperty): void { $setPropertyFlags->invoke(null, $missingProperty, 0); })
            ->toThrow(InvalidArgumentException::class);
    });

    it('covers defensive opcode and clone entrypoints', function (): void {
        $ffi = Natives::ffi();
        $invoke = static function (string $name, mixed ...$arguments): mixed {
            $method = new ReflectionMethod(ZendfulExecutor::class, $name);

            return $method->invoke(null, ...$arguments);
        };
        $opArray = $ffi->new('zend_op_array');
        $raw = $ffi->new('znode_op');
        $opline = $ffi->new('zend_op');

        $entryMethod = new ReflectionMethod(ZendfulExecutor::class, 'entry');
        /** @var \Zendful_FFI\zval $entry */
        $entry = $entryMethod->invoke(null, new \Zendful\FunctionHandle('executorCoverageWithVariable'), $ffi);
        $function = $ffi->cast('zend_function *', $entry->value->ptr);
        expect(ZendfulExecutor::snapshotOpArray($function->op_array))->toBeInstanceOf(\Zendful\CompiledOpArrayHandle::class);

        expect(static function () use ($invoke, $opArray): void { $invoke('snapshotOpArray', $opArray); })
            ->toThrow(RuntimeException::class)
            ->and($invoke('forgetLiterals', $ffi, null, 0))->toBeNull()
            ->and(static function () use ($invoke, $ffi, $raw, $opline): void { $invoke(
                'writeOperand',
                $ffi,
                $raw,
                ['kind' => 'constant', 'literalSlot' => null, 'type' => Natives::ZEND_IS_CONST, 'value' => null, 'rawValue' => null],
                $opline,
                null,
            ); })->toThrow(RuntimeException::class)
            ->and(static function () use ($invoke, $ffi, $raw, $opline): void { $invoke(
                'writeOperand',
                $ffi,
                $raw,
                ['kind' => 'raw', 'literalSlot' => null, 'type' => Natives::ZEND_IS_UNUSED, 'value' => 'not-an-int', 'rawValue' => null],
                $opline,
                null,
            ); })->toThrow(InvalidArgumentException::class);

        expect($invoke(
            'writeOperand',
            $ffi,
            $raw,
            ['kind' => 'raw', 'literalSlot' => null, 'type' => Natives::ZEND_IS_VAR, 'value' => 3, 'rawValue' => null],
            $opline,
            null,
        ))->toBeNull();

        $internalMethod = new \Zendful\MethodHandle(\DateTime::class, 'format');
        $methodEntry = new ReflectionMethod(ZendfulExecutor::class, 'methodEntry');
        $internalEntry = $methodEntry->invoke(null, $internalMethod);
        $assertWritable = new ReflectionMethod(ZendfulExecutor::class, 'assertWritableMethod');

        expect(static function () use ($assertWritable, $internalMethod, $internalEntry): void { $assertWritable->invoke(null, $internalMethod, $internalEntry); })
            ->toThrow(InvalidArgumentException::class)
            ->and(static function () use ($invoke, $ffi): void { $invoke('cloneUserFunction', $ffi, $ffi->new('zend_function'), 'clone'); })
            ->toThrow(InvalidArgumentException::class)
            ->and(static function () use ($invoke, $internalMethod): void { $invoke('opArrayMetadata', $internalMethod); })
            ->toThrow(RuntimeException::class);
    });

    it('validates and changes hooked property setter visibility', function (): void {
        $property = new \Zendful\PropertyHandle(ExecutorHookTarget::class, 'value');

        expect(ZendfulExecutor::propertyHasHooks($property))->toBeTrue();
        ZendfulExecutor::setPropertySetVisibility($property, 'protected', true);
        ZendfulExecutor::setPropertySetVisibility($property, 'private', true);
        ZendfulExecutor::setPropertySetVisibility($property, 'public', false);
        expect(static function () use ($property): mixed {
            ZendfulExecutor::setPropertySetVisibility($property, 'invalid', true);

        })->toThrow(InvalidArgumentException::class);
    });

    class ExecutorMutableTarget
    {
        public string $value = 'value';

        public function run(): string
        {
            return 'run';
        }
    }

    class ExecutorReadonlyTarget
    {
        public string $value = 'value';
    }

    readonly class ExecutorReadonlyValidTarget
    {
        public int $value;

        public function __construct()
        {
            $this->value = 1;
        }
    }

    class ExecutorInstallSource
    {
        public function source(): string
        {
            return 'source';
        }
    }

    class ExecutorInstallTarget {}

    abstract class ExecutorAbstractTarget {}

    abstract class ExecutorAbstractMethodTarget
    {
        abstract public function run(): string;
    }

    final class ExecutorFinalTarget {}

    class ExecutorMissingTarget {}

    class ExecutorInterfaceInstallTarget {}

    interface ExecutorContract {}

    class ExecutorInterfaceTarget implements ExecutorContract {}

    class ExecutorDerivedTarget extends ExecutorMutableTarget {}

    class ExecutorReadonlyChildTarget extends ExecutorMutableTarget {}

    class ExecutorStaticPropertyTarget
    {
        public static int $value = 1;
    }

    class ExecutorUntypedPropertyTarget
    {
        /** @var mixed */
        public $value;
    }

    class ExecutorRenameTarget
    {
        public function first(): string
        {
            return 'first';
        }
        public function second(): string
        {
            return 'second';
        }
    }

    class ExecutorInheritedParent
    {
        public function existing(): string
        {
            return 'parent';
        }
    }

    class ExecutorInheritedTarget extends ExecutorInheritedParent {}

    class ExecutorDirectCollisionTarget
    {
        public function existing(): string
        {
            return 'existing';
        }
    }

    class ExecutorHookTarget
    {
        public string $value {
            set => $value;
        }
    }

});
