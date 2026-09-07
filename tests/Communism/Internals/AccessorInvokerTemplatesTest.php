<?php

declare(strict_types=1);

use Communism\Internals\AccessorInvokerTemplates;

describe('AccessorInvokerTemplates', function (): void {
    covers(AccessorInvokerTemplates::class);

    it('reads and writes instance accessor placeholders', function (): void {
        $templates = new AccessorInvokerTemplates();

        $templates->accessorSet('value');

        expect($templates->accessorGet())->toBe('value');
    });

    it('reads and writes static accessor placeholders', function (): void {
        AccessorInvokerTemplates::accessorStaticSet('value');

        expect(AccessorInvokerTemplates::accessorStaticGet())->toBe('value');
    });
});
