<?php

declare(strict_types=1);

use Communism\Internals\Needle\Assembler;
use Communism\Internals\Needle\Decompiler;
use Communism\Internals\Needle\Operand;
use Communism\Internals\Zend;

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
        $function = Zend::lookupMethod(AssemblerLiteralTarget::class, $method);

        if ($function === null) {
            throw new RuntimeException(sprintf('Method %s was not found', $method));
        }

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
            Assembler::write($modified, $function->op_array);

            $actual = match ($method) {
                'stringValue' => (new AssemblerLiteralTarget())->stringValue(),
                'integerValue' => (new AssemblerLiteralTarget())->integerValue(),
                'floatValue' => (new AssemblerLiteralTarget())->floatValue(),
                'boolValue' => (new AssemblerLiteralTarget())->boolValue(),
                'nullValue' => (new AssemblerLiteralTarget())->nullValue(),
            };

            expect($actual)->toBe($expected);
        } finally {
            Assembler::write($body, $function->op_array);
        }
    }
});

it('restores rewritten literal storage without retaining replacement strings', function (): void {
    $body = Decompiler::decompile(AssemblerLiteralTarget::class . '::stringValue');
    $function = Zend::lookupMethod(AssemblerLiteralTarget::class, 'stringValue');

    if ($function === null) {
        throw new RuntimeException('Method stringValue was not found');
    }

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
                Assembler::write($modified, $function->op_array);
                expect((new AssemblerLiteralTarget())->stringValue())->toBe($replacement);
                Assembler::write($body, $function->op_array);
                expect((new AssemblerLiteralTarget())->stringValue())->toBe('before');
            }
        } finally {
            Assembler::write($body, $function->op_array);
        }

        return;
    }

    throw new RuntimeException('Could not find the string literal');
});
