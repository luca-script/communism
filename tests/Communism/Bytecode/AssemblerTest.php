<?php

declare(strict_types=1);

use Communism\Internals\Needle\Assembler;
use Communism\Internals\Needle\Decompiler;
use Communism\Internals\Needle\Instruction;
use Communism\Internals\Needle\MethodBody;
use Communism\Internals\Needle\Operand;
use Zendful\Zendful;

describe('Assembler', function (): void {
    covers([
        Assembler::class,
        Decompiler::class,
        Instruction::class,
        MethodBody::class,
        ...COMMUNISM_INJECTOR_COVERAGE_CLASSES,
    ]);

    final class AssemblerLiteralTarget
    {
        public function stringValue(): string
        {
            return 'before';
        }

        public function integerValue(): int
        {
            return 1;
        }

        public function floatValue(): float
        {
            return 1.5;
        }

        public function boolValue(): bool
        {
            return true;
        }

        public function nullValue(): null
        {
            return null;
        }
    }

    it('writes every scalar zval literal kind into replacement opcode storage', function (): void {
        $cases = [
            ['stringValue', 'after', 'after'],
            ['integerValue', 42, 42],
            ['floatValue', 4.25, 4.25],
            ['boolValue', false, false],
            ['nullValue', null, null],
        ];

        foreach ($cases as [$method, $replacement, $expected]) {
            $body = Decompiler::decompile(AssemblerLiteralTarget::class . '::' . $method);
            $opArray = Zendful::method(AssemblerLiteralTarget::class, $method)->opArray();

            $literalIndex = null;
            foreach ($body->instructions() as $index => $instruction) {
                if ($instruction->result->kind === Operand::CONSTANT) {
                    $literalIndex = [$index, 'result'];
                    break;
                }
                if ($instruction->operand1->kind === Operand::CONSTANT) {
                    $literalIndex = [$index, 'operand1'];
                    break;
                }
                if ($instruction->operand2->kind === Operand::CONSTANT) {
                    $literalIndex = [$index, 'operand2'];
                    break;
                }
            }

            if ($literalIndex === null) {
                throw new RuntimeException(sprintf('No literal found in %s', $method));
            }

            [$index, $position] = $literalIndex;
            $instruction = $body->instruction($index);
            $result = $position === 'result' ? $instruction->result->withValue($replacement) : $instruction->result;
            $operand1 = $position === 'operand1' ? $instruction->operand1->withValue($replacement) : $instruction->operand1;
            $operand2 = $position === 'operand2' ? $instruction->operand2->withValue($replacement) : $instruction->operand2;
            $modified = $body->replace($index, $instruction->withOperands($operand1, $operand2, $result));

            try {
                Assembler::write($modified, $opArray);

                $actual = match ($method) {
                    'stringValue' => (new AssemblerLiteralTarget())->stringValue(),
                    'integerValue' => (new AssemblerLiteralTarget())->integerValue(),
                    'floatValue' => (new AssemblerLiteralTarget())->floatValue(),
                    'boolValue' => (new AssemblerLiteralTarget())->boolValue(),
                    'nullValue' => (new AssemblerLiteralTarget())->nullValue(),
                };

                expect($actual)->toBe($expected);
            } finally {
                Assembler::write($body, $opArray);
            }
        }
    });

    it('restores rewritten literal storage without retaining replacement strings', function (): void {
        $body = Decompiler::decompile(AssemblerLiteralTarget::class . '::stringValue');
        $opArray = Zendful::method(AssemblerLiteralTarget::class, 'stringValue')->opArray();

        foreach ($body->instructions() as $index => $instruction) {
            $position = match (true) {
                $instruction->result->kind === Operand::CONSTANT => 'result',
                $instruction->operand1->kind === Operand::CONSTANT => 'operand1',
                $instruction->operand2->kind === Operand::CONSTANT => 'operand2',
                default => null,
            };
            if ($position === null) {
                continue;
            }

            $replacement = str_repeat('replacement-', 64);
            $result = $position === 'result' ? $instruction->result->withValue($replacement) : $instruction->result;
            $operand1 = $position === 'operand1' ? $instruction->operand1->withValue($replacement) : $instruction->operand1;
            $operand2 = $position === 'operand2' ? $instruction->operand2->withValue($replacement) : $instruction->operand2;
            $modified = $body->replace(
                $index,
                $instruction->withOperands($operand1, $operand2, $result),
            );

            try {
                for ($iteration = 0; $iteration < 25; $iteration++) {
                    Assembler::write($modified, $opArray);
                    expect((new AssemblerLiteralTarget())->stringValue())->toBe($replacement);
                    Assembler::write($body, $opArray);
                    expect((new AssemblerLiteralTarget())->stringValue())->toBe('before');
                }
            } finally {
                Assembler::write($body, $opArray);
            }

            return;
        }

        throw new RuntimeException('Could not find the string literal');
    });

    it('rejects invalid literal references and non-scalar constants', function (): void {
        $body = Decompiler::decompile(AssemblerLiteralTarget::class . '::stringValue');
        $opArray = Zendful::method(AssemblerLiteralTarget::class, 'stringValue')->opArray();
        $instruction = $body->instruction(0);

        $invalidReference = $instruction->withOperands(
            $instruction->operand1,
            $instruction->operand2,
            Operand::constant('invalid', 0, -1),
        );
        expect(fn() => Assembler::write($body->replace(0, $invalidReference), $opArray))
            ->toThrow(RuntimeException::class, 'outside its original literal pool');

        $nonScalar = $instruction->withOperands(
            $instruction->operand1,
            $instruction->operand2,
            Operand::constant(new stdClass(), 0),
        );
        expect(fn() => Assembler::write($body->replace(0, $nonScalar), $opArray))
            ->toThrow(RuntimeException::class, 'Only scalar constants');
    });

    it('deduplicates literals and emits static-call name pairs', function (): void {
        $plan = new ReflectionMethod(Assembler::class, 'planLiterals');
        $shared = Operand::constant('Shared', 0);
        $body = new MethodBody('literalPlan', null, 0, 0, [
            new Instruction(0, 'INIT_STATIC_METHOD_CALL', Operand::unused(), $shared, Operand::constant('Method', 0)),
            new Instruction(0, 'RETURN', $shared, $shared, Operand::unused()),
        ]);

        $result = $plan->invoke(null, $body, 0);
        if (!is_array($result) || count($result) !== 2 || !is_array($result[0]) || !is_array($result[1])) {
            throw new RuntimeException('Unexpected literal plan shape');
        }
        [$slots, $values] = $result;
        expect($slots)->toHaveCount(2)
            ->and($values)->toContain('Shared', 'shared', 'Method', 'method');
    });

    it('recognizes opcode operands that require literal name pairs', function (): void {
        $method = new ReflectionMethod(Assembler::class, 'needsNamePair');
        $instruction = new Instruction(0, 'TEST', Operand::unused(), Operand::unused(), Operand::unused());

        expect($method->invoke(null, new Instruction(0, 'NEW', $instruction->result, $instruction->operand1, $instruction->operand2), 1))->toBeTrue()
            ->and($method->invoke(null, new Instruction(0, 'INIT_FCALL', $instruction->result, $instruction->operand1, $instruction->operand2), 2))->toBeTrue()
            ->and($method->invoke(null, new Instruction(0, 'INIT_METHOD_CALL', $instruction->result, $instruction->operand1, $instruction->operand2), 2))->toBeTrue()
            ->and($method->invoke(null, new Instruction(0, 'INIT_STATIC_METHOD_CALL', $instruction->result, $instruction->operand1, $instruction->operand2), 1))->toBeTrue()
            ->and($method->invoke(null, $instruction, 0))->toBeFalse();
    });
});
