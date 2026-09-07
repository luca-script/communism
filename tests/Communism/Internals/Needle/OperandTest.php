<?php

declare(strict_types=1);

use Communism\Internals\Needle\Operand;

describe('Operand', function (): void {
    covers(Operand::class);

    it('creates each operand representation with its Zend metadata', function (): void {
        expect(Operand::unused())->toMatchObject([
            'kind' => Operand::UNUSED,
            'value' => 0,
            'type' => 0,
            'rawValue' => 0,
            'literalIndex' => null,
        ])
            ->and(Operand::unused(4)->rawValue)->toBe(4)
            ->and(Operand::constant('value', -2, 7))->toMatchObject([
                'kind' => Operand::CONSTANT,
                'type' => 1,
                'rawValue' => -2,
                'literalIndex' => 7,
            ])
            ->and(Operand::temporary(3))->toMatchObject(['kind' => Operand::TEMPORARY, 'type' => 2, 'rawValue' => 3])
            ->and(Operand::variable(3))->toMatchObject(['kind' => Operand::VARIABLE, 'type' => 4, 'rawValue' => 3])
            ->and(Operand::cv(3))->toMatchObject(['kind' => Operand::CV, 'type' => 8, 'rawValue' => 3])
            ->and(Operand::raw(32, 9))->toMatchObject(['kind' => Operand::RAW, 'type' => 32, 'rawValue' => 9]);
    });

    it('identifies unused operands and creates value replacements', function (): void {
        $operand = Operand::constant('before', 12, 2);
        $replacement = $operand->withValue('after');

        expect(Operand::unused()->isUnused())->toBeTrue()
            ->and($operand->isUnused())->toBeFalse()
            ->and($replacement)->toMatchObject([
                'kind' => Operand::CONSTANT,
                'value' => 'after',
                'type' => 1,
                'rawValue' => null,
                'literalIndex' => null,
            ])
            ->and($replacement)->not->toBe($operand);
    });
});
