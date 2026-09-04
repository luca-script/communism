<?php

declare(strict_types=1);

use Zendful\FunctionHandle;
use Zendful\AssemblyPlanHandle;
use Zendful\CompiledClassHandle;
use Zendful\CompiledFileHandle;
use Zendful\CompiledMethodHandle;
use Zendful\CompiledOpArrayHandle;
use Zendful\ClassHandle;
use Zendful\MethodHandle;
use Zendful\OpcodeHandle;
use Zendful\OperandHandle;
use Zendful\OpArrayHandle;
use Zendful\PropertyHandle;
use Zendful\Zendful;
use Zendful\Internals\Natives;

describe('Zendful', function (): void {
    covers([
        Zendful::class,
        FunctionHandle::class,
        AssemblyPlanHandle::class,
        CompiledClassHandle::class,
        CompiledFileHandle::class,
        CompiledMethodHandle::class,
        CompiledOpArrayHandle::class,
        ClassHandle::class,
        MethodHandle::class,
        OpcodeHandle::class,
        OperandHandle::class,
        OpArrayHandle::class,
        PropertyHandle::class,
        Natives::class,
    ]);

    function zendfulCoverageFirst(): string
    {
        return 'first';
    }

    function zendfulCoverageSecond(): string
    {
        return 'second';
    }

    function zendfulFramelessCoverage(string $value): string
    {
        return strtoupper($value);
    }

    it('creates opaque function handles', function (): void {
        $handle = Zendful::function('zendfulCoverageFirst');

        expect($handle)
            ->toBeInstanceOf(FunctionHandle::class)
            ->and($handle->name())->toBe('zendfulCoverageFirst');
    });

    it('resolves PHP 8.6 frameless dispatch entries through the public boundary', function (): void {
        $opArray = Zendful::function('zendfulFramelessCoverage')->opArray();
        $found = false;
        for ($index = 0; $index < $opArray->instructionCount(); $index++) {
            $opcode = $opArray->opcode($index);
            if (!str_contains($opcode->name(), 'FRAMELESS_ICALL_')) {
                continue;
            }

            $found = true;
            expect(Zendful::framelessFunction($opcode->extendedValue())?->name())->toBe('strtoupper');
        }

        if (!$found) {
            expect(Zendful::framelessFunction(0))->toBeNull();
        }
    });

    it('rejects empty function names', function (): void {
        expect(fn(): FunctionHandle => Zendful::function(''))
            ->toThrow(InvalidArgumentException::class);
    });

    it('rejects embedded NUL bytes at every string boundary', function (): void {
        expect(fn(): FunctionHandle => new FunctionHandle("function\0name"))
            ->toThrow(InvalidArgumentException::class)
            ->and(fn(): ClassHandle => new ClassHandle("Class\0name"))
            ->toThrow(InvalidArgumentException::class)
            ->and(fn(): MethodHandle => new MethodHandle('Class', "method\0name"))
            ->toThrow(InvalidArgumentException::class)
            ->and(fn(): PropertyHandle => new PropertyHandle('Class', "property\0name"))
            ->toThrow(InvalidArgumentException::class)
            ->and(fn(): FunctionHandle => Zendful::function("function\0name"))
            ->toThrow(InvalidArgumentException::class)
            ->and(fn(): ClassHandle => Zendful::class("Class\0name"))
            ->toThrow(InvalidArgumentException::class)
            ->and(fn(): MethodHandle => Zendful::method('Class', "method\0name"))
            ->toThrow(InvalidArgumentException::class)
            ->and(fn(): PropertyHandle => Zendful::property('Class', "property\0name"))
            ->toThrow(InvalidArgumentException::class)
            ->and(fn(): int => Zendful::opcodeId("NOP\0"))
            ->toThrow(InvalidArgumentException::class)
            ->and(fn(): int => Zendful::opcodeId(''))
            ->toThrow(InvalidArgumentException::class)
            ->and(fn(): mixed => Zendful::compileFile("file\0name.php"))
            ->toThrow(InvalidArgumentException::class);
    });

    it('validates fallback handle constructors and semantic values', function (): void {
        expect(fn(): FunctionHandle => new FunctionHandle(''))
            ->toThrow(InvalidArgumentException::class)
            ->and(fn(): ClassHandle => new ClassHandle(''))
            ->toThrow(InvalidArgumentException::class)
            ->and(fn(): MethodHandle => new MethodHandle('', 'value'))
            ->toThrow(InvalidArgumentException::class)
            ->and(fn(): PropertyHandle => new PropertyHandle('', 'value'))
            ->toThrow(InvalidArgumentException::class);

        $method = Zendful::method(ZendfulTestTarget::class, 'value');
        $property = Zendful::property(ZendfulTestTarget::class, 'value');
        $class = Zendful::class(ZendfulTestTarget::class);

        expect(function () use ($method): never {
            $method->setVisibility('invalid');
            throw new LogicException('Expected invalid method visibility to throw');
        })
            ->toThrow(InvalidArgumentException::class)
            ->and(function () use ($property): never {
                $property->setVisibility('invalid');
                throw new LogicException('Expected invalid property visibility to throw');
            })
            ->toThrow(InvalidArgumentException::class)
            ->and(function () use ($property): never {
                $property->setSetVisibility('invalid');
                throw new LogicException('Expected invalid property-set visibility to throw');
            })
            ->toThrow(InvalidArgumentException::class)
            ->and(function () use ($property): never {
                $property->setSetVisibility('public');
                throw new LogicException('Expected setter visibility without hooks to throw');
            })
            ->toThrow(InvalidArgumentException::class)
            ->and(function () use ($property): never {
                $property->setSetVisibility('public', false);
                throw new LogicException('Expected clearing setter visibility without hooks to throw');
            })
            ->toThrow(InvalidArgumentException::class)
            ->and(function () use ($class): never {
                $class->setKind('invalid');
                throw new LogicException('Expected invalid class kind to throw');
            })
            ->toThrow(InvalidArgumentException::class);
    });

    it('checks function existence without exposing runtime metadata', function (): void {
        expect(Zendful::function('zendfulCoverageFirst')->exists())->toBeTrue()
            ->and(fn(): FunctionHandle => Zendful::function('missingZendfulFunction'))
            ->toThrow(InvalidArgumentException::class);
    });

    it('does not invoke autoloaders while validating runtime handles', function (): void {
        $autoloads = 0;
        $autoloadedClass = null;
        $loader = static function (string $class) use (&$autoloads, &$autoloadedClass): void {
            $autoloads++;
            $autoloadedClass = $class;
        };
        spl_autoload_register($loader);

        try {
            (new ClassHandle('ZendfulNeverLoaded'))->disableJit();
            if ($autoloads !== 0) {
                throw new LogicException(sprintf('Unexpected autoload: %s', $autoloadedClass));
            }

            expect(fn(): ClassHandle => Zendful::class('ZendfulNeverLoaded'))
                ->toThrow(InvalidArgumentException::class)
                ->and($autoloads)->toBe(0)
                ->and(fn(): MethodHandle => Zendful::method('ZendfulNeverLoaded', 'run'))
                ->toThrow(InvalidArgumentException::class)
                ->and($autoloads)->toBe(0)
                ->and(fn(): PropertyHandle => Zendful::property('ZendfulNeverLoaded', 'value'))
                ->toThrow(InvalidArgumentException::class)
                ->and($autoloads)->toBe(0);

            $forgedMethod = new MethodHandle('ZendfulNeverLoaded', 'run');
            expect(static function () use ($forgedMethod): mixed {
                $forgedMethod->swapWith(Zendful::method(ZendfulTestTarget::class, 'value'));
            })
                ->toThrow(InvalidArgumentException::class)
                ->and($autoloads)->toBe(0);
        } finally {
            spl_autoload_unregister($loader);
        }
    });

    it('classifies user-defined functions without exposing runtime metadata', function (): void {
        expect(Zendful::function('zendfulCoverageFirst')->isUserDefined())->toBeTrue()
            ->and(Zendful::function('strlen')->isUserDefined())->toBeFalse()
            ->and(fn(): FunctionHandle => Zendful::function('missingZendfulFunction'))
            ->toThrow(InvalidArgumentException::class);
    });

    it('checks bytecode availability without exposing the op array', function (): void {
        expect(Zendful::function('zendfulCoverageFirst')->hasBytecode())->toBeTrue()
            ->and(Zendful::function('strlen')->hasBytecode())->toBeFalse()
            ->and(fn(): FunctionHandle => Zendful::function('missingZendfulFunction'))
            ->toThrow(InvalidArgumentException::class);
    });

    it('rejects internal callables before reading an op-array union', function (): void {
        $internalMethod = Zendful::method(DateTime::class, 'format');

        expect($internalMethod->hasBytecode())->toBeFalse()
            ->and(fn(): OpArrayHandle => Zendful::function('strlen')->opArray())
            ->toThrow(RuntimeException::class)
            ->and(fn(): OpArrayHandle => $internalMethod->opArray())
            ->toThrow(RuntimeException::class);
    });

    it('exposes validated op array metadata through an opaque handle', function (): void {
        $opArray = Zendful::function('zendfulCoverageFirst')->opArray();

        expect($opArray->instructionCount())->toBeGreaterThan(0)
            ->and($opArray->argumentCount())->toBe(0)
            ->and($opArray->filename())->toBeString()
            ->and($opArray->lineStart())->toBeGreaterThan(0)
            ->and($opArray->lineEnd())->toBeGreaterThanOrEqual($opArray->lineStart());

        expect($opArray->variableCount())->toBeInt()
            ->and($opArray->temporaryCount())->toBeInt()
            ->and($opArray->cacheSize())->toBeInt()
            ->and($opArray->literalCount())->toBeInt()
            ->and($opArray->isImmutable())->toBeBool()
            ->and($opArray->variableNames())->toBeArray()
            ->and($opArray->source())->toBeInstanceOf(FunctionHandle::class);
    });

    it('keeps detached compiled data in typed value objects', function (): void {
        $operand = new OperandHandle(1, 0, 2, 3, 'three', 3);
        $opcode = new OpcodeHandle([
            'opcode' => 1,
            'name' => 'NOP',
            'extendedValue' => 0,
            'line' => 1,
            'result' => $operand,
            'operand1' => $operand,
            'operand2' => $operand,
        ]);
        $compiledOpArray = new CompiledOpArrayHandle([
            'instructionCount' => 1,
            'filename' => 'fixture.php',
            'lineStart' => 1,
            'lineEnd' => 1,
            'variableNames' => [0 => 'value'],
            'temporaryCount' => 1,
            'cacheSize' => 1,
        ], [$opcode]);
        $compiledMethod = new CompiledMethodHandle('run', $compiledOpArray);
        $compiledClass = new CompiledClassHandle('Fixture', ['run' => $compiledMethod]);
        $compiledFile = new CompiledFileHandle($compiledOpArray, ['fixture'], [$compiledClass], [$compiledMethod]);
        $plan = new AssemblyPlanHandle(1, 1, [], []);

        expect($operand->type())->toBe(1)
            ->and($operand->constant())->toBe(0)
            ->and($operand->variable())->toBe(2)
            ->and($operand->number())->toBe(3)
            ->and($operand->constantDescription())->toBe('three')
            ->and($operand->value())->toBe(3)
            ->and($operand->literalIndex())->toBeNull()
            ->and($opcode->name())->toBe('NOP')
            ->and($compiledOpArray->variableNames())->toBe([0 => 'value'])
            ->and($compiledOpArray->opcode(0))->toBe($opcode)
            ->and($compiledClass->method('RUN'))->toBe($compiledMethod)
            ->and($compiledClass->method('missing'))->toBeNull()
            ->and($compiledClass->methods())->toHaveKey('run')
            ->and($compiledFile->declaredClasses)->toBe(['fixture'])
            ->and($plan->temporaryCount)->toBe(1);

        expect(fn(): OpcodeHandle => $compiledOpArray->opcode(1))
            ->toThrow(OutOfRangeException::class);
    });

    it('rejects malformed detached names before they can reach a backend', function (): void {
        $opArray = new CompiledOpArrayHandle([
            'instructionCount' => 0,
            'filename' => null,
            'lineStart' => 0,
            'lineEnd' => 0,
            'variableNames' => [],
            'temporaryCount' => 0,
            'cacheSize' => 0,
        ], []);
        $method = new CompiledMethodHandle('run', $opArray);
        $operand = new OperandHandle(0, 0, 0, 0, 'unused');

        expect(fn(): CompiledMethodHandle => new CompiledMethodHandle("run\0", $opArray))
            ->toThrow(InvalidArgumentException::class)
            ->and(fn(): CompiledClassHandle => new CompiledClassHandle("Class\0", []))
            ->toThrow(InvalidArgumentException::class)
            ->and(fn(): CompiledClassHandle => new CompiledClassHandle('Class', ["run\0" => $method]))
            ->toThrow(InvalidArgumentException::class)
            ->and(fn(): CompiledClassHandle => new CompiledClassHandle('Class', ['' => $method]))
            ->toThrow(InvalidArgumentException::class)
            ->and(fn(): OperandHandle => new OperandHandle(0, 0, 0, 0, "unused\0"))
            ->toThrow(InvalidArgumentException::class)
            ->and(fn(): OperandHandle => new OperandHandle(0, 0, 0, 0, 'unused', null, -1))
            ->toThrow(InvalidArgumentException::class)
            ->and(fn(): CompiledFileHandle => new CompiledFileHandle($opArray, ["Class\0"]))
            ->toThrow(InvalidArgumentException::class)
            ->and(fn(): CompiledOpArrayHandle => new CompiledOpArrayHandle([
                'instructionCount' => 0,
                'filename' => "file\0.php",
                'lineStart' => 0,
                'lineEnd' => 0,
                'variableNames' => [],
                'temporaryCount' => 0,
                'cacheSize' => 0,
            ], []))
            ->toThrow(InvalidArgumentException::class)
            ->and(fn(): OpcodeHandle => new OpcodeHandle([
                'opcode' => 1,
                'name' => "NOP\0",
                'extendedValue' => 0,
                'line' => 0,
                'result' => $operand,
                'operand1' => $operand,
                'operand2' => $operand,
            ]))
            ->toThrow(InvalidArgumentException::class);

        expect(fn(): CompiledOpArrayHandle => new CompiledOpArrayHandle([
            'instructionCount' => 0,
            'filename' => null,
            'lineStart' => -1,
            'lineEnd' => 0,
            'variableNames' => [],
            'temporaryCount' => 0,
            'cacheSize' => 0,
        ], []))->toThrow(InvalidArgumentException::class);
    });

    it('rejects malformed detached metadata before it can reach a backend', function (): void {
        $emptyOpArray = new CompiledOpArrayHandle([
            'instructionCount' => 0,
            'filename' => null,
            'lineStart' => 0,
            'lineEnd' => 0,
            'variableNames' => [],
            'temporaryCount' => 0,
            'cacheSize' => 0,
        ], []);
        $method = new CompiledMethodHandle('run', $emptyOpArray);
        $operand = new OperandHandle(0, 0, 0, 0, 'unused');
        $compiledClass = new ReflectionClass(CompiledClassHandle::class);
        $compiledFile = new ReflectionClass(CompiledFileHandle::class);
        $assemblyPlan = new ReflectionClass(AssemblyPlanHandle::class);

        expect(fn(): object => $compiledClass->newInstanceArgs(['Class', ['run' => new stdClass()]]))
            ->toThrow(InvalidArgumentException::class)
            ->and(fn(): object => $compiledFile->newInstanceArgs([$emptyOpArray, [new stdClass()]]))
            ->toThrow(InvalidArgumentException::class)
            ->and(fn(): object => $compiledFile->newInstanceArgs([$emptyOpArray, [], [new stdClass()]]))
            ->toThrow(InvalidArgumentException::class)
            ->and(fn(): object => $compiledFile->newInstanceArgs([$emptyOpArray, [], [], [new stdClass()]]))
            ->toThrow(InvalidArgumentException::class)
            ->and(fn(): CompiledOpArrayHandle => new CompiledOpArrayHandle([
                'instructionCount' => 0,
                'filename' => null,
                'lineStart' => 0,
                'lineEnd' => 0,
                'variableNames' => [0 => "name\0"],
                'temporaryCount' => 0,
                'cacheSize' => 0,
            ], []))
            ->toThrow(InvalidArgumentException::class)
            ->and(fn(): CompiledOpArrayHandle => new CompiledOpArrayHandle([
                'instructionCount' => 1,
                'filename' => null,
                'lineStart' => 0,
                'lineEnd' => 0,
                'variableNames' => [],
                'temporaryCount' => 0,
                'cacheSize' => 0,
            ], []))
            ->toThrow(InvalidArgumentException::class)
            ->and(fn(): OpcodeHandle => new OpcodeHandle([
                'opcode' => 1,
                'name' => 'NOP',
                'extendedValue' => -1,
                'line' => 0,
                'result' => $operand,
                'operand1' => $operand,
                'operand2' => $operand,
            ]))
            ->toThrow(InvalidArgumentException::class)
            ->and(fn(): AssemblyPlanHandle => new AssemblyPlanHandle(-1, 0, [], []))
            ->toThrow(InvalidArgumentException::class)
            ->and(fn(): AssemblyPlanHandle => new AssemblyPlanHandle(0, -1, [], []))
            ->toThrow(InvalidArgumentException::class)
            ->and(fn(): object => $assemblyPlan->newInstanceArgs([0, 0, [1], []]))
            ->toThrow(InvalidArgumentException::class)
            ->and(fn(): object => $assemblyPlan->newInstanceArgs([0, 0, [], ['invalid' => true]]))
            ->toThrow(InvalidArgumentException::class)
            ->and(fn(): object => $assemblyPlan->newInstanceArgs([0, 0, [[
                'opcode' => 'invalid',
                'extendedValue' => 0,
                'line' => 0,
                'originalIndex' => null,
                'result' => [],
                'operand1' => [],
                'operand2' => [],
            ]], []]))
            ->toThrow(InvalidArgumentException::class)
            ->and(fn(): object => $assemblyPlan->newInstanceArgs([0, 0, [[
                'opcode' => 0,
                'extendedValue' => 0,
                'line' => 0,
                'originalIndex' => null,
                'result' => [
                    'type' => 0,
                    'kind' => 'unused',
                    'value' => null,
                    'rawValue' => null,
                    'literalSlot' => -1,
                ],
                'operand1' => [],
                'operand2' => [],
            ]], []]))
            ->toThrow(InvalidArgumentException::class)
            ->and(fn(): OperandHandle => new OperandHandle(-1, 0, 0, 0, 'unused'))
            ->toThrow(InvalidArgumentException::class)
            ->and($method->name)->toBe('run');

        expect(fn(): CompiledOpArrayHandle => new CompiledOpArrayHandle([
            'instructionCount' => 0,
            'filename' => null,
            'lineStart' => 2,
            'lineEnd' => 1,
            'variableNames' => [],
            'temporaryCount' => 0,
            'cacheSize' => 0,
        ], []))->toThrow(InvalidArgumentException::class);
    });

    it('rejects adversarial detached container shapes', function (): void {
        $emptyOpArray = new CompiledOpArrayHandle([
            'instructionCount' => 0,
            'filename' => null,
            'lineStart' => 0,
            'lineEnd' => 0,
            'variableNames' => [],
            'temporaryCount' => 0,
            'cacheSize' => 0,
        ], []);
        $method = new CompiledMethodHandle('run', $emptyOpArray);
        $operand = new OperandHandle(OperandHandle::TYPE_UNUSED, 0, 0, 0, 'unused');
        $opcode = new OpcodeHandle([
            'opcode' => 0,
            'name' => 'NOP',
            'extendedValue' => 0,
            'line' => 0,
            'result' => $operand,
            'operand1' => $operand,
            'operand2' => $operand,
        ]);
        $compiledClass = new ReflectionClass(CompiledClassHandle::class);
        $compiledFile = new ReflectionClass(CompiledFileHandle::class);
        $compiledOpArray = new ReflectionClass(CompiledOpArrayHandle::class);
        $opcodeHandle = new ReflectionClass(OpcodeHandle::class);

        expect(fn(): object => $compiledClass->newInstanceArgs(['Class', [1 => $method]]))
            ->toThrow(InvalidArgumentException::class)
            ->and(fn(): object => $compiledClass->newInstanceArgs(['Class', ['run' => new stdClass()]]))
            ->toThrow(InvalidArgumentException::class)
            ->and(fn(): ?CompiledMethodHandle => (new CompiledClassHandle('Class', ['run' => $method]))->method("run\0"))
            ->toThrow(InvalidArgumentException::class)
            ->and(fn(): object => $compiledFile->newInstanceArgs([$emptyOpArray, [1 => 'Class']]))
            ->toThrow(InvalidArgumentException::class)
            ->and(fn(): object => $compiledFile->newInstanceArgs([$emptyOpArray, [], [$method]]))
            ->toThrow(InvalidArgumentException::class)
            ->and(fn(): object => $compiledFile->newInstanceArgs([$emptyOpArray, [], [], [new stdClass()]]))
            ->toThrow(InvalidArgumentException::class)
            ->and(fn(): object => $compiledOpArray->newInstanceArgs([[
                'instructionCount' => 0,
                'filename' => null,
                'lineStart' => 0,
                'lineEnd' => 0,
                'variableNames' => ['not-an-index' => 'name'],
                'temporaryCount' => 0,
                'cacheSize' => 0,
            ], []]))
            ->toThrow(InvalidArgumentException::class)
            ->and(fn(): object => $compiledOpArray->newInstanceArgs([[
                'instructionCount' => 1,
                'filename' => null,
                'lineStart' => 0,
                'lineEnd' => 0,
                'variableNames' => [],
                'temporaryCount' => 0,
                'cacheSize' => 0,
            ], [new stdClass()]]))
            ->toThrow(InvalidArgumentException::class)
            ->and(fn(): object => $opcodeHandle->newInstanceArgs([[
                'opcode' => 0,
                'name' => 'NOP',
                'extendedValue' => 0,
                'line' => 0,
                'result' => $operand,
                'operand1' => $operand,
                'operand2' => new stdClass(),
            ]]))
            ->toThrow(InvalidArgumentException::class)
            ->and(fn(): OperandHandle => new OperandHandle(0, 0, 0, 0, 'unused', null, PHP_INT_MIN))
            ->toThrow(InvalidArgumentException::class)
            ->and($opcode->name())->toBe('NOP')
            ->and($opcode->opcode())->toBe(0)
            ->and($opcode->extendedValue())->toBe(0)
            ->and($opcode->line())->toBe(0)
            ->and($opcode->result())->toBe($operand)
            ->and($opcode->operand1())->toBe($operand)
            ->and($opcode->operand2())->toBe($operand)
            ->and($method->opArray)->toBe($emptyOpArray);
    });

    it('exposes opcode operands through typed handles', function (): void {
        $opcode = Zendful::function('zendfulCoverageFirst')->opArray()->opcode(0);

        expect($opcode->opcode())->toBeInt()
            ->and($opcode->operand1()->type())->toBeInt()
            ->and($opcode->operand2()->number())->toBeInt();

        expect(fn(): OpcodeHandle => Zendful::function('zendfulCoverageFirst')->opArray()->opcode(-1))
            ->toThrow(OutOfRangeException::class)
            ->and(fn(): OpcodeHandle => Zendful::function('zendfulCoverageFirst')->opArray()->opcode(PHP_INT_MAX))
            ->toThrow(OutOfRangeException::class);

        expect(fn(): int => Zendful::opcodeId('NOT_A_REAL_OPCODE'))
            ->toThrow(InvalidArgumentException::class)
            ->and(fn(): int => OpcodeHandle::id(''))
            ->toThrow(InvalidArgumentException::class)
            ->and(fn(): int => OpcodeHandle::id("NOP\0"))
            ->toThrow(InvalidArgumentException::class);
    });

    it('routes root assembly through validation before touching Zend memory', function (): void {
        $operand = [
            'type' => OperandHandle::TYPE_UNUSED,
            'kind' => 'unused',
            'value' => 0,
            'rawValue' => 0,
            'literalSlot' => null,
        ];
        $plan = new AssemblyPlanHandle(0, 0, [[
            'opcode' => Natives::ZEND_OPCODE_MAX + 1,
            'extendedValue' => 0,
            'line' => 0,
            'originalIndex' => null,
            'result' => $operand,
            'operand1' => $operand,
            'operand2' => $operand,
        ]], []);

        expect(static function () use ($plan): void {
            Zendful::assemble(Zendful::function('zendfulCoverageFirst')->opArray(), $plan);
        })->toThrow(InvalidArgumentException::class);
    });

    it('rejects wrong runtime shapes at every root API boundary', function (): void {
        $cases = [
            ['function', [[]]],
            ['method', [[], 'value']],
            ['property', [ZendfulTestTarget::class, []]],
            ['class', [[]]],
            ['compileFile', [[]]],
            ['opcodeId', [[]]],
            ['assemble', [new stdClass(), new stdClass()]],
            ['disableJitForMethod', [new stdClass()]],
            ['disableJitForFunction', [new stdClass()]],
            ['disableJitForClass', [new stdClass()]],
        ];

        foreach ($cases as [$methodName, $arguments]) {
            $method = new ReflectionMethod(Zendful::class, $methodName);

            expect(static fn(): mixed => $method->invokeArgs(null, $arguments))
                ->toThrow(TypeError::class);
        }
    });

    it('rejects malformed assembly containers before backend validation', function (): void {
        $assemblyPlan = new ReflectionClass(AssemblyPlanHandle::class);

        expect(fn(): object => $assemblyPlan->newInstanceArgs([0, 0, [1 => []], []]))
            ->toThrow(InvalidArgumentException::class)
            ->and(fn(): object => $assemblyPlan->newInstanceArgs([0, 0, [], [0 => new stdClass()]]))
            ->toThrow(InvalidArgumentException::class)
            ->and(fn(): object => $assemblyPlan->newInstanceArgs([0, 0, [], [0 => "bad\0literal"]]))
            ->toThrow(InvalidArgumentException::class);
    });

    it('queries methods through a typed method handle', function (): void {
        $handle = Zendful::method(ZendfulTestTarget::class, 'value');

        expect($handle->exists())->toBeTrue()
            ->and($handle->isUserDefined())->toBeTrue()
            ->and($handle->hasBytecode())->toBeTrue()
            ->and(fn(): \Zendful\MethodHandle => Zendful::method(ZendfulTestTarget::class, 'missing'))
            ->toThrow(InvalidArgumentException::class);
    });

    it('validates class, property, and method handles', function (): void {
        expect(fn(): \Zendful\ClassHandle => Zendful::class(''))
            ->toThrow(InvalidArgumentException::class)
            ->and(fn(): \Zendful\PropertyHandle => Zendful::property('', 'value'))
            ->toThrow(InvalidArgumentException::class)
            ->and(fn(): \Zendful\MethodHandle => Zendful::method(ZendfulTestTarget::class, ''))
            ->toThrow(InvalidArgumentException::class);

        $class = Zendful::class(ZendfulTestTarget::class);
        expect($class->name())->toBe(ZendfulTestTarget::class)
            ->and($class->exists())->toBeTrue()
            ->and($class->isImmutable())->toBeFalse();
        $class->setKind(null);
        expect((new \ReflectionClass(ZendfulTestTarget::class))->isFinal())->toBeTrue();
        expect(get_class_methods($class))->not->toContain('flags');

        $property = Zendful::property(ZendfulTestTarget::class, 'value');
        expect($property->className())->toBe(ZendfulTestTarget::class)
            ->and($property->propertyName())->toBe('value')
            ->and($property->exists())->toBeTrue()
            ->and($property->hasHooks())->toBeFalse();

        $method = Zendful::method(ZendfulTestTarget::class, 'value');
        expect($method->className())->toBe(ZendfulTestTarget::class)
            ->and($method->methodName())->toBe('value')
            ->and($method->exists())->toBeTrue();
        expect(get_class_methods($method))->not->toContain('flags');
        expect(get_class_methods($property))->not->toContain('flags');

        expect($class->staticsInitialized())->toBeBool();
        $class->initializeStatics();
    });

    it('requires class kinds to be cleared before changing kind', function (): void {
        $class = Zendful::class(ZendfulTestTarget::class);
        $class->setKind('interface');

        try {
            expect(static function () use ($class): mixed {
                $class->setKind('enum');
            })
                ->toThrow(InvalidArgumentException::class);
        } finally {
            $class->setKind(null);
        }
    });

    it('rejects enum kind transitions that require unsupported metadata', function (): void {
        $class = Zendful::class(ZendfulTestTarget::class);

        expect(static function () use ($class): never {
            $class->setKind('enum');
            throw new LogicException('Expected enum synthesis to throw.');
        })
            ->toThrow(InvalidArgumentException::class)
            ->and(static function (): never {
                Zendful::class(ZendfulKindEnum::class)->setKind(null);
                throw new LogicException('Expected enum clearing to throw.');
            })
            ->toThrow(InvalidArgumentException::class);
    });

    it('rejects abstract and final flag combinations before mutation', function (): void {
        $class = Zendful::class(ZendfulAbstractTarget::class);
        $method = Zendful::method(ZendfulAbstractTarget::class, 'abstractMethod');

        expect(static function () use ($class): never {
            $class->setFinal();
            throw new LogicException('Expected abstract class validation to throw.');
        })
            ->toThrow(InvalidArgumentException::class)
            ->and(static function (): never {
                Zendful::class(ZendfulTestTarget::class)->setAbstract();
                throw new LogicException('Expected final class validation to throw.');
            })
            ->toThrow(InvalidArgumentException::class)
            ->and(static function () use ($method): never {
                $method->setFinal();
                throw new LogicException('Expected abstract method validation to throw.');
            })
            ->toThrow(InvalidArgumentException::class)
            ->and((new ReflectionClass(ZendfulAbstractTarget::class))->isAbstract())->toBeTrue()
            ->and((new ReflectionMethod(ZendfulAbstractTarget::class, 'abstractMethod'))->isFinal())->toBeFalse();
    });

    it('rejects invalid readonly property transitions before mutation', function (): void {
        expect(static function (): never {
            Zendful::property(ZendfulPropertySafetyTarget::class, 'staticValue')->setReadonly();
            throw new LogicException('Expected static readonly validation to throw.');
        })
            ->toThrow(InvalidArgumentException::class)
            ->and(static function (): never {
                Zendful::property(ZendfulPropertySafetyTarget::class, 'untypedValue')->setReadonly();
                throw new LogicException('Expected untyped readonly validation to throw.');
            })
            ->toThrow(InvalidArgumentException::class)
            ->and((new ReflectionProperty(ZendfulPropertySafetyTarget::class, 'staticValue'))->isReadOnly())->toBeFalse()
            ->and((new ReflectionProperty(ZendfulPropertySafetyTarget::class, 'untypedValue'))->isReadOnly())->toBeFalse();

        expect(static function (): never {
            Zendful::class(ZendfulTestTarget::class)->setReadonly();
            throw new LogicException('Expected readonly class validation to throw.');
        })
            ->toThrow(InvalidArgumentException::class)
            ->and((new ReflectionClass(ZendfulTestTarget::class))->isReadOnly())->toBeFalse();
    });

    it('rejects class anonymity transitions after declaration', function (): void {
        expect(static function (): never {
            Zendful::class(ZendfulTestTarget::class)->setAnonymous();
            throw new LogicException('Expected class anonymity validation to throw.');
        })
            ->toThrow(InvalidArgumentException::class)
            ->and((new ReflectionClass(ZendfulTestTarget::class))->isAnonymous())->toBeFalse();
    });

    it('rejects forged handles before unsafe operations begin', function (): void {
        $class = new ClassHandle('ZendfulMissingClass');
        $method = new MethodHandle('ZendfulMissingClass', 'missing');
        $property = new PropertyHandle('ZendfulMissingClass', 'missing');
        $function = new FunctionHandle('zendful_missing_function');

        expect($class->exists())->toBeFalse()
            ->and($method->exists())->toBeFalse()
            ->and($property->exists())->toBeFalse()
            ->and($function->exists())->toBeFalse();

        foreach ([
            static fn() => $class->setFinal(),
            static fn() => $class->setReadonly(),
            static fn() => $class->setAbstract(),
            static fn() => $class->setKind(null),
            static fn() => $class->setAnonymous(),
            static fn() => $class->staticsInitialized(),
            static fn() => $class->initializeStatics(),
            static fn() => $method->setVisibility('public'),
            static fn() => $method->clearVisibility(),
            static fn() => $method->setStatic(),
            static fn() => $method->setFinal(),
            static fn() => $method->withVisibility('public', static fn(): null => null),
            static fn() => $method->swapWith(Zendful::method(ZendfulTestTarget::class, 'value')),
            static fn() => $method->installInto($class, 'installed'),
            static fn() => $method->renameTo('renamed'),
            static fn() => $method->installGeneratedInto($class, $method, 'generated'),
            static fn() => $property->setVisibility('public'),
            static fn() => $property->clearVisibility(),
            static fn() => $property->setReadonly(),
            static fn() => $property->setSetVisibility('public'),
            static fn() => $property->withVisibility('public', static fn(): null => null),
            static fn() => $function->opArray(),
            static fn() => $method->opArray(),
        ] as $index => $operation) {
            try {
                $operation();
                $threw = false;
            } catch (InvalidArgumentException) {
                $threw = true;
            }

            expect($threw)->toBeTrue(sprintf('Forged operation %d was accepted.', $index));
        }
    });

    it('exposes function JIT and caller invalidation APIs without exposing unsafe state', function (): void {
        Zendful::function('zendfulCoverageFirst')->disableJit();
        Zendful::disableJitForFunction(Zendful::function('zendfulCoverageSecond'));
        Zendful::disableJitForMethod(Zendful::method(ZendfulTestTarget::class, 'value'));
        Zendful::disableJitForClass(Zendful::class(ZendfulTestTarget::class));
        expect(true)->toBeTrue();
    });

    it('rejects handles for missing runtime objects', function (): void {
        expect(fn(): \Zendful\FunctionHandle => Zendful::function('missingZendfulFunction'))
            ->toThrow(InvalidArgumentException::class)
            ->and(fn(): \Zendful\MethodHandle => Zendful::method(ZendfulTestTarget::class, 'missing'))
            ->toThrow(InvalidArgumentException::class)
            ->and(fn(): \Zendful\ClassHandle => Zendful::class('MissingZendfulClass'))
            ->toThrow(InvalidArgumentException::class)
            ->and(fn(): \Zendful\PropertyHandle => Zendful::property(ZendfulTestTarget::class, 'missing'))
            ->toThrow(InvalidArgumentException::class);
    });

    it('rejects mutations of internal Zend classes and functions', function (): void {
        $class = new ClassHandle(DateTime::class);
        $method = new MethodHandle(DateTime::class, 'format');

        expect(static function () use ($class): mixed {
            $class->setFinal();
        })
            ->toThrow(InvalidArgumentException::class)
            ->and(static function () use ($method): mixed {
                $method->setVisibility('public');
            })
            ->toThrow(InvalidArgumentException::class)
            ->and(static function (): mixed {
                Zendful::function('strlen')->swapWith(Zendful::function('zendfulCoverageFirst'));
            })
            ->toThrow(InvalidArgumentException::class);
    });

    it('validates method installation and renaming names', function (): void {
        $method = Zendful::method(ZendfulTestTarget::class, 'value');
        $class = Zendful::class(ZendfulTestTarget::class);

        expect(function () use ($method, $class): never {
            $method->installInto($class, '');
            throw new LogicException('Expected installation validation to throw');
        })
            ->toThrow(InvalidArgumentException::class)
            ->and(function () use ($method): never {
                $method->renameTo("renamed\0method");
                throw new LogicException('Expected rename validation to throw');
            })
            ->toThrow(InvalidArgumentException::class)
            ->and(function () use ($method, $class): never {
                $method->installGeneratedInto($class, $method, '');
                throw new LogicException('Expected generated installation validation to throw');
            })
            ->toThrow(InvalidArgumentException::class);

        $method->swapWith($method);
        expect(fn(): FunctionHandle => Zendful::function('missingZendfulFunction'))
            ->toThrow(InvalidArgumentException::class);
    });

    it('rejects live method-table collisions before mutating Zend', function (): void {
        $target = Zendful::class(ZendfulInstallTarget::class);
        $source = Zendful::method(ZendfulInstallSource::class, 'source');
        $existing = Zendful::method(ZendfulInstallTarget::class, 'existing');

        expect(static function () use ($source, $target): never {
            $source->installInto($target, 'existing');
            throw new LogicException('Expected installation collision to throw.');
        })
            ->toThrow(InvalidArgumentException::class)
            ->and(static function () use ($existing): never {
                $existing->renameTo('source');
                throw new LogicException('Expected rename collision to throw.');
            })
            ->toThrow(InvalidArgumentException::class)
            ->and(static function () use ($source, $target): never {
                $source->installGeneratedInto($target, $source, 'existing');
                throw new LogicException('Expected generated installation collision to throw.');
            })
            ->toThrow(InvalidArgumentException::class)
            ->and($existing->exists())->toBeTrue()
            ->and(Zendful::method(ZendfulInstallTarget::class, 'source')->exists())->toBeTrue();

        expect(static function () use ($target): never {
            (new MethodHandle(DateTime::class, 'format'))->installInto($target, 'internal');
            throw new LogicException('Expected internal method installation to throw.');
        })
            ->toThrow(InvalidArgumentException::class);
    });

    it('rejects initialization and compilation of missing runtime objects', function (): void {
        expect(fn(): \Zendful\ClassHandle => Zendful::class('MissingZendfulClass'))
            ->toThrow(InvalidArgumentException::class)
            ->and(fn(): mixed => Zendful::compileFile(__DIR__ . '/missing-zendful-source.php'))
            ->toThrow(InvalidArgumentException::class);
    });

    it('validates method swap hierarchy before mutating methods', function (): void {
        expect(function (): never {
            Zendful::method(ZendfulTestTarget::class, 'value')->swapWith(
                Zendful::method(ZendfulUnrelatedTarget::class, 'value'),
            );
            throw new LogicException('Expected method hierarchy validation to throw');
        })->toThrow(InvalidArgumentException::class);
    });

    final class ZendfulTestTarget
    {
        public int $value = 0;

        public function value(): string
        {
            return 'value';
        }
    }

    final class ZendfulUnrelatedTarget
    {
        public function value(): string
        {
            return 'unrelated';
        }
    }

    final class ZendfulInstallSource
    {
        public function source(): string
        {
            return 'source';
        }
    }

    final class ZendfulInstallTarget
    {
        public function source(): string
        {
            return 'target';
        }

        public function existing(): string
        {
            return 'existing';
        }
    }

    abstract class ZendfulAbstractTarget
    {
        abstract public function abstractMethod(): string;
    }

    enum ZendfulKindEnum
    {
        case Value;
    }

    final class ZendfulPropertySafetyTarget
    {
        public static string $staticValue = 'static';
        /** @var mixed */
        public $untypedValue = 'untyped';
    }

    it('swaps functions through the function handle API and restores them', function (): void {
        expect(zendfulCoverageFirst())->toBe('first')
            ->and(zendfulCoverageSecond())->toBe('second');

        Zendful::function('zendfulCoverageFirst')->swapWith(
            Zendful::function('zendfulCoverageSecond'),
        );

        try {
            expect(zendfulCoverageFirst())->toBe('second')
                ->and(zendfulCoverageSecond())->toBe('first');
        } finally {
            Zendful::function('zendfulCoverageFirst')->swapWith(
                Zendful::function('zendfulCoverageSecond'),
            );
        }
    });
});
