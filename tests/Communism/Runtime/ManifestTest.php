<?php

declare(strict_types=1);

use Communism\Mixin\Manifest;
use Communism\Mixin\Mixin;
use Communism\Mixin\Runtime;
use Communism\Mixin\Applies;
use Communism\Reflect\ReflectionClass;

/**
 * @method string manifestMarker()
 * @method string repeatedMarker()
 * @method string selectorMarker()
 */
final class ManifestRuntimeTarget {}

#[Mixin(ManifestRuntimeTarget::class)]
final class ManifestRuntimeMixin
{
    private function __construct() {}

    public function manifestMarker(): string
    {
        return 'manifest';
    }
}

final class ManifestRuntimeDefinition extends Manifest
{
    /** @return list<string> */
    #[Override]
    public function getTransforms(): array
    {
        return [ManifestRuntimeMixin::class];
    }
}

it('installs a class manifest and applies its declared transforms', function (): void {
    $runtime = Runtime::installOrGet();
    $runtime->add(ManifestRuntimeDefinition::class);

    expect((new ManifestRuntimeTarget())->manifestMarker())->toBe('manifest')
        ->and(array_filter($runtime->manifests(), static fn(Manifest $manifest): bool => $manifest instanceof ManifestRuntimeDefinition))->not->toBeEmpty();
});

it('does not apply a single manifest class more than once', function (): void {
    $runtime = Runtime::installOrGet();
    $runtime->add(ManifestRuntimeDefinition::class);
    $before = count($runtime->manifests());
    $runtime->add(ManifestRuntimeDefinition::class);

    expect(count($runtime->manifests()))->toBe($before);
});

final class ManifestRuntimeInstanceDefinition extends Manifest
{
    public static function isSingle(): bool
    {
        return false;
    }

    /** @return list<string> */
    #[Override]
    public function getTransforms(): array
    {
        return [];
    }
}

it('accepts non-single manifest instances', function (): void {
    $runtime = Runtime::installOrGet();
    $manifest = new ManifestRuntimeInstanceDefinition();
    $runtime->add($manifest);

    expect($runtime->manifests())->toContain($manifest);
});

it('rejects a single manifest supplied as an instance', function (): void {
    expect(static fn() => Runtime::installOrGet()->add(new ManifestRuntimeDefinition()))
        ->toThrow(InvalidArgumentException::class, 'isSingle()');
});

final class ManifestRuntimeInvalidDefinition extends Manifest
{
    /** @return list<string> */
    #[Override]
    public function getTransforms(): array
    {
        return ['MissingManifestRuntimeMixin'];
    }
}

it('rejects missing required transforms before applying anything', function (): void {
    expect(static fn() => Runtime::installOrGet()->add(ManifestRuntimeInvalidDefinition::class))
        ->toThrow(InvalidArgumentException::class, 'references missing mixin');
});

final class ManifestOptionalDefinition extends Manifest
{
    public function isRequired(): bool
    {
        return false;
    }

    /** @return list<string> */
    #[Override]
    public function getTransforms(): array
    {
        return ['MissingOptionalManifestMixin'];
    }
}

it('skips missing transforms for optional manifests', function (): void {
    $runtime = Runtime::installOrGet();
    $before = count($runtime->manifests());
    $runtime->add(ManifestOptionalDefinition::class);

    expect(count($runtime->manifests()))->toBe($before + 1);
});

final class ManifestUnannotatedTransform {}

final class ManifestUnannotatedDefinition extends Manifest
{
    /** @return list<string> */
    #[Override]
    public function getTransforms(): array
    {
        return [ManifestUnannotatedTransform::class];
    }
}

it('rejects manifest transforms without a Mixin declaration', function (): void {
    expect(static fn() => Runtime::installOrGet()->add(ManifestUnannotatedDefinition::class))
        ->toThrow(InvalidArgumentException::class, 'at least one #[Mixin]');
});

final class ManifestPrivateConstructorDefinition extends Manifest
{
    private function __construct() {}

    /** @return list<string> */
    #[Override]
    public function getTransforms(): array
    {
        return [];
    }
}

it('rejects class manifests that cannot be publicly instantiated', function (): void {
    expect(static fn() => Runtime::installOrGet()->add(ManifestPrivateConstructorDefinition::class))
        ->toThrow(InvalidArgumentException::class, 'instantiable without required constructor arguments');
});

/** @method string repeatedMarker() */
final class ManifestSecondTarget {}

#[Mixin(ManifestRuntimeTarget::class)]
#[Mixin(ManifestSecondTarget::class)]
final class ManifestRepeatedMixin
{
    private function __construct() {}

    public function repeatedMarker(): string
    {
        return 'repeated';
    }
}

it('allows a mixin to declare multiple target classes', function (): void {
    (new ReflectionClass(ManifestRuntimeTarget::class))->inject(ManifestRepeatedMixin::class);
    (new ReflectionClass(ManifestSecondTarget::class))->inject(ManifestRepeatedMixin::class);

    expect((new ManifestRuntimeTarget())->repeatedMarker())->toBe('repeated')
        ->and((new ManifestSecondTarget())->repeatedMarker())->toBe('repeated');
});

/** @method string selectorMarker() */
final class ManifestRegexTarget {}
/** @method string selectorMarker() */
final class ManifestGlobTarget {}
final class ManifestUnmatchedTarget {}

#[Mixin(ManifestRuntimeTarget::class)]
#[Applies(ManifestRegexTarget::class, ManifestGlobTarget::class)]
#[Applies('REGEX', 'ManifestRegexTarget', 'NeverMatchedTarget')]
#[Applies('GLOB', 'ManifestGlob*')]
final class ManifestSelectorMixin
{
    private function __construct() {}

    public function selectorMarker(): string
    {
        return 'selected';
    }
}

it('accepts repeatable multi-pattern exact, regex, and glob Applies selectors', function (): void {
    foreach ([ManifestRuntimeTarget::class, ManifestRegexTarget::class, ManifestGlobTarget::class] as $target) {
        (new ReflectionClass($target))->inject(ManifestSelectorMixin::class);
    }

    expect((new ManifestRuntimeTarget())->selectorMarker())->toBe('selected')
        ->and((new ManifestRegexTarget())->selectorMarker())->toBe('selected')
        ->and((new ManifestGlobTarget())->selectorMarker())->toBe('selected')
        ->and((new \ReflectionClass(ManifestUnmatchedTarget::class))->hasMethod('selectorMarker'))->toBeFalse();
});

it('rejects empty Applies selectors and invalid regular expressions', function (): void {
    expect(static fn() => new Applies())
        ->toThrow(InvalidArgumentException::class, 'at least one selector')
        ->and(static fn() => new Applies('REGEX', '['))
        ->toThrow(InvalidArgumentException::class, 'REGEX selector is invalid');
});

it('treats glob metacharacters other than wildcards literally', function (): void {
    $selector = new Applies('GLOB', 'Vendor\\Package.+');

    expect($selector->matches('Vendor\\Package.+'))->toBeTrue()
        ->and($selector->matches('Vendor\\PackageXYZ'))->toBeFalse();
});

it('requires a non-empty Mixin declaration', function (): void {
    expect(static fn() => new Mixin())
        ->toThrow(InvalidArgumentException::class, 'at least one target');
});
