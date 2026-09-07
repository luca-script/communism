<?php

declare(strict_types=1);

use Communism\Internals\Needle\Instruction;
use Communism\Internals\Needle\MethodBody;
use Communism\Internals\Needle\Operand;

describe('MethodBody', function (): void {
    covers([Instruction::class, MethodBody::class]);

    it('supports MethodBody metadata and immutable instruction transformations', function (): void {
        $instruction = new Instruction(1, 'TEST', Operand::unused(), Operand::cv(0), Operand::raw(4, 3));
        $body = new MethodBody('run', 'test.php', 1, 2, [$instruction], [0 => 'value'], 2, 3, [0 => 'int']);

        $variableOperand = $body->variableOperand('value');

        expect($body->instructions())->toBe([$instruction])
            ->and($body->count())->toBe(1)
            ->and($body->instruction(0))->toBe($instruction)
            ->and($body->variableType(Operand::cv(0)))->toBe('int')
            ->and($body->variableType(Operand::temporary(0)))->toBeNull()
            ->and($body->variableName(Operand::cv(0)))->toBe('value')
            ->and($body->variableName(Operand::cv(1)))->toBeNull()
            ->and($body->variableName(Operand::temporary(0)))->toBeNull()
            ->and($variableOperand)->not->toBeNull()
            ->and($variableOperand?->value)->toBe(0)
            ->and($body->variableOperand('missing'))->toBeNull()
            ->and($body->variableIndex(Operand::cv(0)))->toBe(0)
            ->and($body->variableIndex(Operand::cv(1)))->toBeNull()
            ->and($body->variableIndex(Operand::temporary(0)))->toBeNull()
            ->and($body->withTemporaryCount(5)->temporaryCount)->toBe(5)
            ->and($body->withCacheSize(6)->cacheSize)->toBe(6)
            ->and($body->withInstructions([])->count())->toBe(0)
            ->and($body->replace(0, $instruction->withOpcode(2, 'REPLACED'))->instruction(0)->name)->toBe('REPLACED')
            ->and($body->insertBefore(0, $instruction)->count())->toBe(2)
            ->and($body->insertAfter(0, $instruction)->count())->toBe(2)
            ->and($body->remove(0)->count())->toBe(0)
            ->and($body->replaceRange(0, 1, [])->count())->toBe(0);
    });

    it('rejects invalid MethodBody instruction indexes and ranges', function (): void {
        $body = new MethodBody('run', null, 0, 0, []);

        expect(fn(): mixed => $body->instruction(0))->toThrow(OutOfBoundsException::class)
            ->and(fn(): mixed => $body->replace(0, new Instruction(1, 'TEST', Operand::unused(), Operand::unused(), Operand::unused())))
            ->toThrow(OutOfBoundsException::class)
            ->and(fn(): mixed => $body->remove(0))->toThrow(OutOfBoundsException::class)
            ->and(fn(): mixed => $body->replaceRange(-1, 0, []))->toThrow(OutOfBoundsException::class)
            ->and(fn(): mixed => $body->replaceRange(0, 1, []))->toThrow(OutOfBoundsException::class);
    });
});
