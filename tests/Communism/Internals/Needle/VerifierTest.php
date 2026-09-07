<?php

declare(strict_types=1);

use Communism\Internals\Needle\Instruction;
use Communism\Internals\Needle\MethodBody;
use Communism\Internals\Needle\Operand;
use Communism\Internals\Needle\Verifier;

describe('Verifier', function (): void {
    covers(Verifier::class);

    it('rejects invalid instruction metadata before assembly', function (): void {
        $instruction = new Instruction(0, 'TEST', Operand::unused(), Operand::unused(), Operand::unused());
        $body = new MethodBody('invalid', null, 0, 0, [$instruction]);

        expect(static fn() => Verifier::verify($body->replace(
            0,
            $instruction->withOpcode(0, "RETURN\0"),
        )))->toThrow(RuntimeException::class, 'invalid opcode metadata');

        $negativeOriginalIndex = new Instruction(
            $instruction->opcode,
            $instruction->name,
            $instruction->result,
            $instruction->operand1,
            $instruction->operand2,
            $instruction->extendedValue,
            $instruction->line,
            $instruction->handler,
            -1,
            $instruction->invocationTarget,
        );
        expect(static fn() => Verifier::verify($body->replace(0, $negativeOriginalIndex)))
            ->toThrow(RuntimeException::class, 'negative original index');

        expect(static fn() => Verifier::verify(new MethodBody('invalid', null, 0, 0, [
            new Instruction(-1, 'TEST', Operand::unused(), Operand::unused(), Operand::unused()),
        ])))->toThrow(RuntimeException::class, 'invalid opcode metadata');

        expect(static fn() => Verifier::verify(new MethodBody('invalid', null, 0, 0, [
            new Instruction(0, 'TEST', Operand::unused(), Operand::unused(), Operand::unused(), -1),
        ])))->toThrow(RuntimeException::class, 'negative metadata');

        expect(static fn() => Verifier::verify(new MethodBody('invalid', null, 0, 0, [
            new Instruction(0, 'TEST', Operand::unused(), Operand::unused(), Operand::unused(), 0, -1),
        ])))->toThrow(RuntimeException::class, 'negative metadata');
    });

    it('rejects negative allocation metadata', function (): void {
        expect(static fn() => Verifier::verify(new MethodBody('invalid', null, 1, 1, [], [], -1)))
            ->toThrow(RuntimeException::class, 'negative allocation');
    });

    it('accepts every supported operand representation', function (): void {
        Verifier::verify(new MethodBody('valid-operands', null, 0, 0, [
            new Instruction(0, 'TEST', Operand::unused(), Operand::constant('value', 0), Operand::temporary(1)),
            new Instruction(0, 'TEST', Operand::variable(2), Operand::cv(3), Operand::raw(16, 4)),
        ]));

        expect(true)->toBeTrue();
    });

    it('rejects malformed operand metadata', function (): void {
        $malformed = static function (string $replacement): Operand {
            $serialized = serialize(Operand::unused());
            $operand = unserialize(str_replace(
                's:4:"kind";s:6:"unused";s:5:"value";i:0;s:4:"type";i:0;s:8:"rawValue";i:0;s:12:"literalIndex";N;',
                $replacement,
                $serialized,
            ));
            expect($operand)->toBeInstanceOf(Operand::class);
            if (!$operand instanceof Operand) {
                throw new LogicException('Expected an Operand.');
            }

            return $operand;
        };

        $verify = static function (Operand $operand): void {
            Verifier::verify(new MethodBody('invalid', null, 0, 0, [
                new Instruction(0, 'TEST', $operand, Operand::unused(), Operand::unused()),
            ]));
        };

        expect(static fn() => $verify($malformed('s:4:"kind";s:3:"bad";s:5:"value";i:0;s:4:"type";i:0;s:8:"rawValue";i:0;s:12:"literalIndex";N;')))
            ->toThrow(RuntimeException::class, 'unknown result operand kind');
        expect(static fn() => $verify($malformed('s:4:"kind";s:6:"unused";s:5:"value";i:0;s:4:"type";i:1;s:8:"rawValue";i:0;s:12:"literalIndex";N;')))
            ->toThrow(RuntimeException::class, 'mismatched result operand type');
        expect(static fn() => $verify($malformed('s:4:"kind";s:6:"unused";s:5:"value";s:3:"bad";s:4:"type";i:0;s:8:"rawValue";i:0;s:12:"literalIndex";N;')))
            ->toThrow(RuntimeException::class, 'non-integer result operand value');
        expect(static fn() => $verify($malformed('s:4:"kind";s:6:"unused";s:5:"value";i:0;s:4:"type";i:0;s:8:"rawValue";i:0;s:12:"literalIndex";i:0;')))
            ->toThrow(RuntimeException::class, 'literal index on non-constant result operand');
    });
});
