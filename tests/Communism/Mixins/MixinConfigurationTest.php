<?php

declare(strict_types=1);

use Communism\Mixin\MixinConfiguration;

describe('MixinConfiguration', function (): void {
    covers(MixinConfiguration::class);

    it('matches target and environment policies', function (): void {
        $all = new MixinConfiguration('Mixin');
        $scoped = new MixinConfiguration('Mixin', ['Example\Target'], environment: 'test');
        $future = new MixinConfiguration('Mixin', compatibilityVersion: '99.0.0');

        expect($all->appliesTo('Anything'))->toBeTrue()
            ->and($scoped->appliesTo('example\target', 'test'))->toBeTrue()
            ->and($scoped->appliesTo('Other', 'test'))->toBeFalse()
            ->and($scoped->appliesTo('Example\Target', 'prod'))->toBeFalse()
            ->and($future->appliesTo('Example\Target'))->toBeFalse();
    });

    it('orders configurations by priority while preserving ties', function (): void {
        $late = new MixinConfiguration('Late', priority: 20);
        $first = new MixinConfiguration('First', priority: 10);
        $tie = new MixinConfiguration('Tie', priority: 10);

        expect(array_map(
            static fn(MixinConfiguration $config): string => $config->mixin,
            MixinConfiguration::ordered([$late, $first, $tie]),
        ))->toBe(['First', 'Tie', 'Late']);
    });

    it('rejects malformed configuration entries', function (): void {
        expect(static fn(): MixinConfiguration => new MixinConfiguration(''))->toThrow(InvalidArgumentException::class)
            ->and(static fn(): MixinConfiguration => new MixinConfiguration('Mixin', ['']))->toThrow(InvalidArgumentException::class)
            ->and(static fn(): MixinConfiguration => new MixinConfiguration('Mixin', compatibilityVersion: 'not-a-version'))->toThrow(InvalidArgumentException::class)
            ->and(static fn(): array => MixinConfiguration::ordered([new stdClass()]))->toThrow(InvalidArgumentException::class);
    });
});
