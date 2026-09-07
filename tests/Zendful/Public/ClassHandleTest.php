<?php

declare(strict_types=1);

use Zendful\ClassHandle;

describe('ClassHandle', function (): void {
    covers(ClassHandle::class);

    it('adds a declared interface through the public handle', function (): void {
        $class = new ClassHandle(ClassHandleCoverageTarget::class);
        $interface = new ClassHandle(ClassHandleCoverageContract::class);

        $class->implementInterface($interface);

        expect(ClassHandleCoverageTarget::class)->toImplement(ClassHandleCoverageContract::class);
    });

    interface ClassHandleCoverageContract {}

    class ClassHandleCoverageTarget {}
});
