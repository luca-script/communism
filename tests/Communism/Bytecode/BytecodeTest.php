<?php

declare(strict_types=1);

namespace Tests\Unit\BytecodeFixtures {
    function sample_bytecode_function(int $value): int
    {
        return $value + 1;
    }

    final class SampleBytecodeClass
    {
        public function greet(string $name): string
        {
            return $name . '.';
        }

        public static function staticGreet(string $name): string
        {
            return $name . '!';
        }
    }

    final class InvokableBytecodeObject
    {
        public function __invoke(int $value): int
        {
            return $value * 2;
        }
    }
}

namespace {
    use Communism\Bytecode\Bytecode;
    use Tests\Unit\BytecodeFixtures\InvokableBytecodeObject;
    use Tests\Unit\BytecodeFixtures\SampleBytecodeClass;
    use Zendful\OpcodeHandle;
    use Zendful\OperandHandle;

    describe('Bytecode', function (): void {
        covers(\Communism\Bytecode\Bytecode::class);

        it('disassembles a named function', function (): void {
            $dump = Bytecode::disassemble('Tests\\Unit\\BytecodeFixtures\\sample_bytecode_function');

            expect($dump)->toContain('sample_bytecode_function');
            expect($dump)->toContain('RECV');
            expect($dump)->toContain('ADD');
            expect($dump)->toContain('RETURN');
        });

        it('disassembles a method', function (): void {
            $dump = Bytecode::disassemble([new SampleBytecodeClass(), 'greet']);

            expect($dump)->toContain('SampleBytecodeClass::greet');
            expect($dump)->toContain('RECV');
            expect($dump)->toContain('CONCAT');
            expect($dump)->toContain('RETURN');
        });

        it('disassembles a method name', function (): void {
            $dump = Bytecode::disassemble(SampleBytecodeClass::class . '::greet');

            expect($dump)->toContain('SampleBytecodeClass::greet');
            expect($dump)->toContain('RETURN');
        });

        it('disassembles an invokable object and a static method', function (): void {
            $invokableDump = Bytecode::disassemble(new InvokableBytecodeObject());
            $staticDump = Bytecode::disassemble(SampleBytecodeClass::class . '::staticGreet');

            expect($invokableDump)->toContain('InvokableBytecodeObject::__invoke')
                ->and($invokableDump)->toContain('MUL')
                ->and($staticDump)->toContain('SampleBytecodeClass::staticGreet')
                ->and($staticDump)->toContain('CONCAT');
        });

        it('rejects closures, missing callables, and internal functions', function (): void {
            expect(fn(): string => Bytecode::disassemble(static fn(): string => 'closure'))
                ->toThrow(InvalidArgumentException::class, 'Closures cannot be disassembled');
            expect(fn(): string => Bytecode::disassemble('Tests\\Unit\\BytecodeFixtures\\missing'))
                ->toThrow(InvalidArgumentException::class);
            expect(fn(): string => Bytecode::disassemble(SampleBytecodeClass::class . '::missing'))
                ->toThrow(InvalidArgumentException::class);
            expect(fn(): string => Bytecode::disassemble('strlen'))
                ->toThrow(InvalidArgumentException::class, 'not a userland function');
        });

        it('formats every supported operand shape', function (): void {
            $call = static function (string $name, mixed ...$arguments): mixed {
                $method = new ReflectionMethod(Bytecode::class, $name);
                $method->setAccessible(true);

                return $method->invoke(null, ...$arguments);
            };
            $operand = static fn(int $type, int $constant, int $variable, int $number, string $description): OperandHandle => new OperandHandle($type, $constant, $variable, $number, $description);
            expect($call('formatOperand', $operand(OperandHandle::TYPE_UNUSED, 0, 0, 0, '')))->toBeNull()
                ->and($call('formatOperand', $operand(OperandHandle::TYPE_UNUSED, 0, 0, 3, '')))->toBe('NUM(3)')
                ->and($call('formatOperand', $operand(OperandHandle::TYPE_CONST, 0, 0, 0, 'CONST')))->toBe('CONST')
                ->and($call('formatOperand', $operand(OperandHandle::TYPE_TMP_VAR, 0, 5, 0, '')))->toBe('TMP@5')
                ->and($call('formatOperand', $operand(OperandHandle::TYPE_VAR, 0, 6, 0, '')))->toBe('VAR@6')
                ->and($call('formatOperand', $operand(OperandHandle::TYPE_CV, 0, 7, 0, '')))->toBe('CV@7')
                ->and($call('formatOperand', $operand(16, 0, 0, 8, '')))->toBe('TYPE(16)@8');

            $opcode = new OpcodeHandle([
                'opcode' => 123,
                'name' => 'ZEND_ADD',
                'extendedValue' => 4,
                'line' => 1,
                'result' => $operand(OperandHandle::TYPE_CV, 0, 1, 0, ''),
                'operand1' => $operand(OperandHandle::TYPE_CONST, 0, 0, 0, 'ONE'),
                'operand2' => $operand(OperandHandle::TYPE_UNUSED, 0, 0, 2, ''),
            ]);
            expect($call('formatOpcode', $opcode))->toContain('CV@1 = ADD ONE , NUM(2) [ext=4]');

        });
    });
}
