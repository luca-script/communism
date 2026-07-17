<?php

declare(strict_types=1);

use Communism\Visibility;

it('exposes the expected visibility values', function (): void {
    expect(Visibility::cases())->toHaveCount(3);
    expect(Visibility::Public->value)->toBe(1);
    expect(Visibility::Protected->value)->toBe(2);
    expect(Visibility::Private->value)->toBe(4);
});
