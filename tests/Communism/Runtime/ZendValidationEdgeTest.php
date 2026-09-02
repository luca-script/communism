<?php

declare(strict_types=1);

use Communism\Internals\Zend;
use Communism\Internals\TransformationSnapshot;
use Communism\Mixin\Accessor;
use Communism\Mixin\At;
use Communism\Mixin\Dynamic;
use Communism\Mixin\DebugOptions;
use Communism\Mixin\Final_;
use Communism\Mixin\Group;
use Communism\Mixin\Inject;
use Communism\Mixin\Invoker;
use Communism\Mixin\Mixin;
use Communism\Mixin\MixinConfiguration;
use Communism\Mixin\ModifyArg;
use Communism\Mixin\ModifyArgs;
use Communism\Mixin\ModifyConstant;
use Communism\Mixin\ModifyVariable;
use Communism\Mixin\Mutable;
use Communism\Mixin\Overwrite;
use Communism\Mixin\Shadow;
use Communism\Mixin\Surrogate;
use Communism\Mixin\Unique;
use Communism\Mixin\Redirect;
use Communism\Reflect\ReflectionClass;

class ZendValidationTarget
{
    public string $value = 'value';

    public function run(): string
    {
        return 'run';
    }

    public function __unique_zendvalidationuniquecollision_run(): string
    {
        return 'existing unique name';
    }

    public function getValue(): string
    {
        return $this->value;
    }
}

final class ZendValidationDebugTarget {}

#[Mixin(ZendValidationDebugTarget::class)]
final class ZendValidationDebugMixin
{
    private function __construct() {}

    public function debugMarker(): string
    {
        return 'debug';
    }
}

final class ZendValidationNoMixin
{
    private function __construct() {}
}

#[Mixin(ZendValidationTarget::class)]
final class ZendValidationInjectMissing
{
    private function __construct() {}

    #[Inject('missing', new At('HEAD'))]
    public function handler(): void {}
}

#[Mixin(ZendValidationTarget::class)]
final class ZendValidationDynamicMissing
{
    private function __construct() {}

    #[Dynamic('provided by an upstream transformation')]
    #[Inject('missing', new At('HEAD'))]
    public function handler(): void {}
}

it('reports Dynamic context when an annotated injection target is missing', function (): void {
    expect(static function (): void {
        (new ReflectionClass(ZendValidationTarget::class))->inject(ZendValidationDynamicMissing::class);
    })->toThrow(InvalidArgumentException::class, 'dynamic: provided by an upstream transformation');

    $attribute = (new ReflectionMethod(ZendValidationDynamicMissing::class, 'handler'))->getAttributes(Dynamic::class)[0] ?? null;
    expect($attribute?->newInstance()->description())->toBe('provided by an upstream transformation');
});

#[Mixin(ZendValidationTarget::class)]
final class ZendValidationUniqueCollision
{
    private function __construct() {}

    #[Unique]
    public function run(): string
    {
        return 'unique';
    }
}

#[Mixin(ZendValidationTarget::class)]
final class ZendValidationMutableShadow
{
    private function __construct() {}

    #[Shadow]
    #[Mutable]
    public function run(): string
    {
        return 'mutable';
    }
}

#[Mixin('OtherTarget')]
final class ZendValidationNotAllowed
{
    private function __construct() {}
}

#[Mixin(ZendValidationTarget::class)]
final class ZendValidationSurrogateCombined
{
    private function __construct() {}

    #[Surrogate]
    #[Inject('run', new At('HEAD'))]
    public function runSurrogate(): void {}
}

#[Mixin(ZendValidationTarget::class)]
final class ZendValidationSurrogateMissingHandler
{
    private function __construct() {}

    #[Surrogate]
    public function missingSurrogate(): void {}
}

#[Mixin(ZendValidationTarget::class)]
final class ZendValidationSurrogateBadName
{
    private function __construct() {}

    #[Surrogate]
    public function orphan(): void {}
}

#[Mixin(ZendValidationTarget::class)]
final class ZendValidationSurrogateNonInjectHandler
{
    private function __construct() {}

    public function run(): void {}

    #[Surrogate]
    public function runSurrogate(): void {}
}

#[Mixin(ZendValidationTarget::class)]
final class ZendValidationAccessorInvoker
{
    private function __construct() {}

    #[Accessor]
    #[Invoker]
    public function generated(): void {}
}

#[Mixin(ZendValidationTarget::class)]
final class ZendValidationGeneratedCombined
{
    private function __construct() {}

    #[Accessor]
    #[Unique]
    public function generated(): void {}
}

#[Mixin(ZendValidationTarget::class)]
final class ZendValidationFinalInjection
{
    private function __construct() {}

    #[Final_]
    #[Inject('run', new At('HEAD'))]
    public function handler(): void {}
}

#[Mixin(ZendValidationTarget::class)]
final class ZendValidationMutableNoShadow
{
    private function __construct() {}

    #[Mutable]
    public function handler(): void {}
}

#[Mixin(ZendValidationTarget::class)]
final class ZendValidationFinalMutable
{
    private function __construct() {}

    #[Final_]
    #[Mutable]
    #[Shadow]
    public function run(): void {}
}

#[Mixin(ZendValidationTarget::class)]
final class ZendValidationOverwriteShadow
{
    private function __construct() {}

    #[Overwrite]
    #[Shadow]
    public function run(): void {}
}

#[Mixin(ZendValidationTarget::class)]
final class ZendValidationShadowInjection
{
    private function __construct() {}

    #[Shadow]
    #[Inject('run', new At('HEAD'))]
    public function handler(): void {}
}

#[Mixin(ZendValidationTarget::class)]
final class ZendValidationGroupWithoutInjection
{
    private function __construct() {}

    #[Group('invalid')]
    public function handler(): void {}
}

#[Mixin(ZendValidationTarget::class)]
final class ZendValidationSelectedMethodMissing
{
    private function __construct() {}

    public function handler(): void {}
}

#[Mixin(ZendValidationTarget::class)]
final class ZendValidationEmptyOverwrite
{
    private function __construct() {}

    #[Overwrite(method: '')]
    public function replacement(): void {}
}

#[Mixin(ZendValidationTarget::class)]
final class ZendValidationExistingMethod
{
    private function __construct() {}

    public function run(): void {}
}

#[Mixin(ZendValidationTarget::class)]
final class ZendValidationMissingOverride
{
    private function __construct() {}

    #[Overwrite(method: 'missing')]
    public function replacement(): void {}
}

final class ZendValidationOverwriteSignatureTarget
{
    public function run(int $value): string
    {
        return (string) $value;
    }
}

#[Mixin(ZendValidationOverwriteSignatureTarget::class)]
final class ZendValidationOverwriteSignatureMismatch
{
    private function __construct() {}

    #[Overwrite]
    public function run(): string
    {
        return 'replacement';
    }
}

it('rejects incompatible Overwrite signatures before mutation', function (): void {
    expect(static function (): void {
        (new ReflectionClass(ZendValidationOverwriteSignatureTarget::class))->inject(ZendValidationOverwriteSignatureMismatch::class);
    })->toThrow(InvalidArgumentException::class, 'incompatible signature');

    expect((new ZendValidationOverwriteSignatureTarget())->run(9))->toBe('9');
});

#[Mixin(ZendValidationTarget::class)]
final class ZendValidationGeneratedCollision
{
    private function __construct() {}

    #[Accessor]
    public function getValue(): string
    {
        return '';
    }
}

#[Mixin(ZendValidationTarget::class)]
final class ZendValidationPropertyWithoutShadow
{
    private function __construct() {}

    public string $value = 'value';
}

#[Mixin(ZendValidationTarget::class)]
final class ZendValidationMissingProperty
{
    private function __construct() {}

    #[Shadow]
    public string $missing = 'missing';
}

#[Mixin(ZendValidationTarget::class)]
final class ZendValidationPropertyMismatch
{
    private function __construct() {}

    #[Shadow]
    public int $value = 1;
}

#[Mixin(ZendValidationTarget::class)]
final class ZendValidationPropertyFinalMutable
{
    private function __construct() {}

    #[Shadow]
    #[Final_]
    #[Mutable]
    public string $value = 'value';
}

#[Mixin(ZendValidationTarget::class)]
final class ZendValidationMutableProperty
{
    private function __construct() {}

    #[Shadow]
    #[Mutable]
    public string $value = 'value';
}

#[Mixin(ZendValidationTarget::class)]
final class ZendValidationModifyConstantAt
{
    private function __construct() {}

    #[ModifyConstant('run', new At('HEAD'))]
    public function handler(): void {}
}

#[Mixin(ZendValidationTarget::class)]
final class ZendValidationModifyVariableAt
{
    private function __construct() {}

    #[ModifyVariable('run', new At('HEAD'))]
    public function handler(): void {}
}

#[Mixin(ZendValidationTarget::class)]
final class ZendValidationModifyArgAt
{
    private function __construct() {}

    #[ModifyArg('run', new At('HEAD'))]
    public function handler(): void {}
}

#[Mixin(ZendValidationTarget::class)]
final class ZendValidationModifyArgsAt
{
    private function __construct() {}

    #[ModifyArgs('run', new At('HEAD'))]
    public function handler(): void {}
}

#[Mixin(ZendValidationTarget::class)]
final class ZendValidationRedirectAt
{
    private function __construct() {}

    #[Redirect('run', new At('HEAD'))]
    public function handler(): void {}
}

#[Mixin(ZendValidationTarget::class)]
final class ZendValidationModifyConstantMissing
{
    private function __construct() {}

    #[ModifyConstant('missing', new At('CONSTANT'))]
    public function handler(): void {}
}

#[Mixin(ZendValidationTarget::class)]
final class ZendValidationModifyVariableMissing
{
    private function __construct() {}

    #[ModifyVariable('missing', new At('STORE'))]
    public function handler(): void {}
}

#[Mixin(ZendValidationTarget::class)]
final class ZendValidationModifyArgMissing
{
    private function __construct() {}

    #[ModifyArg('missing', new At('INVOKE', 'run'))]
    public function handler(): void {}
}

#[Mixin(ZendValidationTarget::class)]
final class ZendValidationModifyArgsMissing
{
    private function __construct() {}

    #[ModifyArgs('missing', new At('INVOKE', 'run'))]
    public function handler(): void {}
}

#[Mixin(ZendValidationTarget::class)]
final class ZendValidationRedirectMissing
{
    private function __construct() {}

    #[Redirect('missing', new At('INVOKE', 'run'))]
    public function handler(): void {}
}

it('rejects invalid Zend mixin declarations before mutation', function (): void {
    $cases = [
        ZendValidationNoMixin::class,
        ZendValidationInjectMissing::class,
        ZendValidationNotAllowed::class,
        ZendValidationSurrogateCombined::class,
        ZendValidationSurrogateMissingHandler::class,
        ZendValidationSurrogateBadName::class,
        ZendValidationSurrogateNonInjectHandler::class,
        ZendValidationAccessorInvoker::class,
        ZendValidationGeneratedCombined::class,
        ZendValidationFinalInjection::class,
        ZendValidationMutableNoShadow::class,
        ZendValidationFinalMutable::class,
        ZendValidationOverwriteShadow::class,
        ZendValidationShadowInjection::class,
        ZendValidationGroupWithoutInjection::class,
        ZendValidationEmptyOverwrite::class,
        ZendValidationExistingMethod::class,
        ZendValidationMissingOverride::class,
        ZendValidationGeneratedCollision::class,
        ZendValidationPropertyWithoutShadow::class,
        ZendValidationMissingProperty::class,
        ZendValidationPropertyMismatch::class,
        ZendValidationPropertyFinalMutable::class,
        ZendValidationMutableProperty::class,
        ZendValidationModifyConstantAt::class,
        ZendValidationModifyVariableAt::class,
        ZendValidationModifyArgAt::class,
        ZendValidationModifyArgsAt::class,
        ZendValidationRedirectAt::class,
        ZendValidationModifyConstantMissing::class,
        ZendValidationModifyVariableMissing::class,
        ZendValidationModifyArgMissing::class,
        ZendValidationModifyArgsMissing::class,
        ZendValidationRedirectMissing::class,
    ];

    $rejected = 0;
    foreach ($cases as $mixin) {
        try {
            Zend::injectMixinMethods(ZendValidationTarget::class, $mixin);
        } catch (InvalidArgumentException) {
            $rejected++;
            continue;
        }

        throw new RuntimeException(sprintf('%s was accepted', $mixin));
    }

    expect($rejected)->toBe(count($cases));
});

it('rejects missing selected methods and missing targets', function (): void {
    expect(static fn() => Zend::injectMixinMethods(
        ZendValidationTarget::class,
        ZendValidationSelectedMethodMissing::class,
        ['missing'],
    ))->toThrow(InvalidArgumentException::class)
        ->and(static fn() => Zend::injectMixinMethods(
            'ZendValidationMissingTarget',
            ZendValidationSelectedMethodMissing::class,
        ))->toThrow(InvalidArgumentException::class);

    expect(static fn() => Zend::injectMixinMethods(ZendValidationTarget::class, ZendValidationModifyArgMissing::class))
        ->toThrow(InvalidArgumentException::class, 'has no method missing')
        ->and(static fn() => Zend::injectMixinMethods(ZendValidationTarget::class, ZendValidationModifyArgsMissing::class))
        ->toThrow(InvalidArgumentException::class, 'has no method missing')
        ->and(static fn() => Zend::injectMixinMethods(ZendValidationTarget::class, ZendValidationRedirectMissing::class))
        ->toThrow(InvalidArgumentException::class, 'has no method missing');
});

it('handles unique collisions and mutable shadows', function (): void {
    Zend::injectMixinMethods(ZendValidationTarget::class, ZendValidationUniqueCollision::class);
    Zend::injectMixinMethods(ZendValidationTarget::class, ZendValidationMutableShadow::class);

    expect(method_exists(ZendValidationTarget::class, '__unique_run_0'))->toBeTrue();
});

it('validates required declarative configurations before applying them', function (): void {
    expect(static fn() => Zend::applyMixinConfigurations(
        ZendValidationTarget::class,
        [new MixinConfiguration('MissingOptionalMixin', required: false)],
    ))->not->toThrow(InvalidArgumentException::class)
        ->and(static fn() => Zend::applyMixinConfigurations(
            ZendValidationTarget::class,
            [new MixinConfiguration('MissingRequiredMixin')],
        ))->toThrow(InvalidArgumentException::class, 'Required mixin MissingRequiredMixin is not declared');
});

it('applies debug options to export and strict configuration policy', function (): void {
    $before = count(Zend::transformationSnapshots());
    Zend::setDebugOptions(new DebugOptions(export: false));
    try {
        Zend::injectMixinMethods(ZendValidationDebugTarget::class, ZendValidationDebugMixin::class);
        expect(count(Zend::transformationSnapshots()))->toBe($before)
            ->and((new \ReflectionClass(ZendValidationDebugTarget::class))->hasMethod('debugMarker'))->toBeTrue();

        Zend::setDebugOptions(new DebugOptions(strict: false));
        expect(static fn() => Zend::applyMixinConfigurations(
            ZendValidationTarget::class,
            [new MixinConfiguration('MissingRequiredMixin')],
        ))->not->toThrow(InvalidArgumentException::class);
    } finally {
        Zend::setDebugOptions(new DebugOptions());
    }
});

#[Mixin('ZendValidationPreloadTarget')]
final class ZendValidationPreloadMixin
{
    private function __construct() {}

    public function preloadMarker(): string
    {
        return 'preloaded';
    }
}

it('applies registered configurations after a target autoloads and rejects late registration', function (): void {
    expect(static fn() => Zend::registerPreloadConfigurations([
        new MixinConfiguration(ZendValidationPreloadMixin::class, [ZendValidationTarget::class]),
    ]))->toThrow(InvalidArgumentException::class, 'already declared');

    $autoload = static function (string $class): void {
        if ($class === 'ZendValidationPreloadTarget') {
            eval('class ZendValidationPreloadTarget {}');
        }
    };
    spl_autoload_register($autoload);
    try {
        Zend::registerPreloadConfigurations([
            new MixinConfiguration(ZendValidationPreloadMixin::class, ['ZendValidationPreloadTarget']),
        ]);
        $targetClass = 'ZendValidationPreloadTarget';
        if (!class_exists($targetClass)) {
            throw new RuntimeException('The preload target was not autoloaded');
        }
        $target = new $targetClass();
        if (!is_object($target)) {
            throw new RuntimeException('The preload target was not instantiated');
        }
        $targetReflection = new \ReflectionClass($target);
        expect($targetReflection->hasMethod('preloadMarker'))->toBeTrue()
            ->and($targetReflection->getMethod('preloadMarker')->invoke($target))->toBe('preloaded');
    } finally {
        Zend::clearPreloadConfigurations();
        spl_autoload_unregister($autoload);
    }
});
