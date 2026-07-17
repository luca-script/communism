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
    }
}

namespace {
    use Communism\Bytecode;
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
}
