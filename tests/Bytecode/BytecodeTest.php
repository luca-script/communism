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
}
