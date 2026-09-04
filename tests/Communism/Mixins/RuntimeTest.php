<?php

declare(strict_types=1);

use Communism\Mixin\Manifest;
use Communism\Mixin\Runtime;

final class InvalidRuntimeManifest extends Manifest
{
    public function getTransforms(): array
    {
        return [];
    }
}

describe('Runtime', function (): void {
    covers(Runtime::class);

    it('returns the installed runtime singleton and starts empty', function (): void {
        $runtime = Runtime::installOrGet();

        expect($runtime)->toBe(Runtime::installOrGet())
            ->and($runtime->manifests())->toBeArray();
    });

    it('rejects unknown manifest classes', function (): void {
        expect(static fn(): Runtime => Runtime::installOrGet()->add('MissingRuntimeManifest'))
            ->toThrow(InvalidArgumentException::class, 'must be a declared Manifest subclass');
    });

    it('rejects a manifest instance when it declares singleton semantics', function (): void {
        expect(static fn(): Runtime => Runtime::installOrGet()->add(new InvalidRuntimeManifest()))
            ->toThrow(InvalidArgumentException::class, 'requires static isSingle() to return false');
    });
});
