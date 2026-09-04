<?php

declare(strict_types=1);

use Communism\Mixin\Desc;

describe('Desc', function (): void {
    covers(Desc::class);

    it('builds selectors and signatures from PHP-shaped metadata', function (): void {
        $method = new Desc('run');
        $static = new Desc('find', 'Example\Service', ['int', 'string'], 'bool', 'lookup');

        expect($method->selector())->toBe('run')
            ->and($method->signature())->toBe(['parameters' => [], 'return' => 'void'])
            ->and($static->selector())->toBe('Example\Service::find')
            ->and($static->signature())->toBe(['parameters' => ['int', 'string'], 'return' => 'bool'])
            ->and($static->id)->toBe('lookup');
    });

    it('rejects empty and whitespace-containing selector metadata', function (): void {
        expect(static fn(): Desc => new Desc(''))->toThrow(InvalidArgumentException::class)
            ->and(static fn(): Desc => new Desc('has space'))->toThrow(InvalidArgumentException::class)
            ->and(static fn(): Desc => new Desc('run', args: ['']))->toThrow(InvalidArgumentException::class)
            ->and(static fn(): Desc => new Desc('run', args: ['two words']))->toThrow(InvalidArgumentException::class)
            ->and(static fn(): Desc => new Desc('run', returnType: ''))->toThrow(InvalidArgumentException::class);
    });
});
