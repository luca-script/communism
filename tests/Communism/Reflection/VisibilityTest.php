<?php

declare(strict_types=1);

use Communism\Reflect\Visibility;

describe('Visibility', function (): void {
    covers(Visibility::class);

    it('exposes the expected visibility values', function (): void {
        expect(Visibility::cases())->toHaveCount(3);
        expect(Visibility::Public->value)->toBe('public');
        expect(Visibility::Protected->value)->toBe('protected');
        expect(Visibility::Private->value)->toBe('private');
    });
});
