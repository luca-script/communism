<?php

declare(strict_types=1);

use Zendful\CompiledOpArrayHandle;
use Zendful\OpcodeHandle;
use Zendful\OperandHandle;

describe('CompiledOpArrayHandle', function (): void {
    covers([CompiledOpArrayHandle::class, OpcodeHandle::class, OperandHandle::class]);

    function compiledOpArrayTestOpcode(): OpcodeHandle
    {
        $operand = new OperandHandle(OperandHandle::TYPE_UNUSED, 0, 0, 0, 'unused');

        return new OpcodeHandle([
            'opcode' => 0,
            'name' => 'NOP',
            'extendedValue' => 0,
            'line' => 1,
            'result' => $operand,
            'operand1' => $operand,
            'operand2' => $operand,
        ]);
    }

    it('exposes every detached op-array metadata accessor', function (): void {
        $opcode = compiledOpArrayTestOpcode();
        $handle = new CompiledOpArrayHandle([
            'instructionCount' => 1,
            'filename' => 'fixture.php',
            'lineStart' => 1,
            'lineEnd' => 2,
            'variableNames' => [0 => 'value'],
            'temporaryCount' => 1,
            'cacheSize' => 1,
        ], [$opcode]);

        expect($handle->instructionCount())->toBe(1)
            ->and($handle->filename())->toBe('fixture.php')
            ->and($handle->lineStart())->toBe(1)
            ->and($handle->lineEnd())->toBe(2)
            ->and($handle->variableNames())->toBe([0 => 'value'])
            ->and($handle->temporaryCount())->toBe(1)
            ->and($handle->cacheSize())->toBe(1)
            ->and($handle->opcode(0))->toBe($opcode);

        expect(fn(): OpcodeHandle => $handle->opcode(1))->toThrow(OutOfRangeException::class);
    });

    it('rejects malformed detached op-array metadata', function (): void {
        $valid = [
            'instructionCount' => 0,
            'filename' => null,
            'lineStart' => 0,
            'lineEnd' => 0,
            'variableNames' => [],
            'temporaryCount' => 0,
            'cacheSize' => 0,
        ];

        $reflection = new ReflectionClass(CompiledOpArrayHandle::class);

        foreach ([
            ['instructionCount' => -1],
            ['filename' => "bad\0name"],
            ['lineEnd' => -1],
            ['lineStart' => 1, 'lineEnd' => 0],
            ['variableNames' => ['bad-key' => 'value']],
            ['temporaryCount' => -1],
            ['cacheSize' => -1],
        ] as $change) {
            expect(fn(): object => $reflection->newInstanceArgs([[...$valid, ...$change], []]))
                ->toThrow(InvalidArgumentException::class);
        }

        expect(fn(): object => $reflection->newInstanceArgs([$valid, [new stdClass()]]))
            ->toThrow(InvalidArgumentException::class);
    });
});
