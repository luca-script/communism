<?php

declare(strict_types=1);

use Communism\Mixin\At;
use Communism\Mixin\CallbackInfo;
use Communism\Mixin\CallbackInfoReturnable;
use Communism\Mixin\Inject;
use Communism\Mixin\Local;
use Communism\Mixin\Mixin;
use Communism\Mixin\Parameter;
use Communism\Internals\Needle\Decompiler;
use Communism\Reflect\ReflectionClass;

#[Mixin(CallbackInjectionTarget::class)]
final class CallbackInjectionTrait
{
    private function __construct() {}
    #[Inject('greet', new At('HEAD'))]
    public function betterGreet(CallbackInfo $info, string $person): void
    {
        if (!callbackCanGreet($person)) {
            $info->cancel('This person cannot be greeted');
        }

        $GLOBALS['callback_greet_handler_ran'] = true;
    }
}


final class CallbackInjectionTarget
{
    public function greet(string $person): void
    {
        $GLOBALS['callback_greeted'] = $person;
    }
}

function callbackCanGreet(string $person): bool
{
    return $person !== 'blocked';
}

function callbackGlobalWasSet(string $name): bool
{
    return isset($GLOBALS[$name]);
}

function unsetCallbackGlobals(): void
{
    unset($GLOBALS['callback_greeted'], $GLOBALS['callback_greet_handler_ran']);
}

it('lowers CallbackInfo and cancels a target without creating a callback object', function (): void {
    unsetCallbackGlobals();
    (new ReflectionClass(CallbackInjectionTarget::class))->inject(CallbackInjectionTrait::class);

    $target = new CallbackInjectionTarget();
    $target->greet('allowed');
    expect(callbackGlobalWasSet('callback_greeted'))->toBeTrue();
    expect(callbackGlobalWasSet('callback_greet_handler_ran'))->toBeTrue();

    unsetCallbackGlobals();
    $target->greet('blocked');
    expect(callbackGlobalWasSet('callback_greeted'))->toBeFalse();
    expect(callbackGlobalWasSet('callback_greet_handler_ran'))->toBeFalse();
});
#[Mixin(CallbackReturnableTarget::class)]
final class CallbackReturnableInjectionTrait
{
    private function __construct() {}
    /** @param CallbackInfoReturnable<int> $info */
    #[Inject('value', new At('RETURN'))]
    public function replaceReturn(CallbackInfoReturnable $info): void
    {
        if ($info->isCancellable() && !$info->isCancelled()) {
            $info->setReturnValue($info->getReturnValue() + 6);
        }
    }
}


final class CallbackReturnableTarget
{
    public function value(): int
    {
        return 3;
    }
}

it('lowers CallbackInfoReturnable setReturnValue into a target return', function (): void {
    (new ReflectionClass(CallbackReturnableTarget::class))->inject(CallbackReturnableInjectionTrait::class);

    expect((new CallbackReturnableTarget())->value())->toBe(9);
});

#[Mixin(MixinSurfaceTarget::class)]
final class MixinSurfaceTrait
{
    private function __construct() {}
    /** @param CallbackInfoReturnable<int> $info */
    #[Inject('value', new At('RETURN'))]
    public function adjustReturn(CallbackInfoReturnable $info): void
    {
        $info->setReturnValue($info->getReturnValue() + 4);
    }
}


final class MixinSurfaceTarget
{
    public function value(): int
    {
        return 2;
    }
}

it('accepts the Mixin-shaped target and callback injection surface', function (): void {
    (new ReflectionClass(MixinSurfaceTarget::class))->inject(MixinSurfaceTrait::class);

    expect((new MixinSurfaceTarget())->value())->toBe(6);
});

#[Mixin(CallbackLocalCaptureTarget::class)]
final class CallbackLocalCaptureMixin
{
    private function __construct() {}
    /** @param CallbackInfoReturnable<int> $info */
    #[Inject('score', new At('RETURN'))]
    public function addLocal(CallbackInfoReturnable $info, int $scoreBase): void
    {
        $info->setReturnValue($info->getReturnValue() + $scoreBase);
    }
}


final class CallbackLocalCaptureTarget
{
    public function score(int $base): int
    {
        $scoreBase = $base + 2;

        return $scoreBase;
    }
}

it('captures a named target local in a virtual callback', function (): void {
    (new ReflectionClass(CallbackLocalCaptureTarget::class))->inject(CallbackLocalCaptureMixin::class);

    expect((new CallbackLocalCaptureTarget())->score(3))->toBe(10);
});

#[Mixin(CallbackLocalFailureTarget::class)]
final class CallbackLocalFailureMixin
{
    private function __construct() {}
    #[Inject('ping', new At('HEAD'))]
    public function missingLocal(CallbackInfo $info, int $notPresent): void {}
}


final class CallbackLocalFailureTarget
{
    public function ping(): void {}
}

it('rejects a virtual callback whose local cannot be captured', function (): void {
    expect(function (): void {
        (new ReflectionClass(CallbackLocalFailureTarget::class))->inject(CallbackLocalFailureMixin::class);
    })
        ->toThrow(InvalidArgumentException::class, 'cannot be captured');
});

#[Mixin(EscapingCallbackTarget::class)]
final class EscapingCallbackMixin
{
    private function __construct() {}
    #[Inject('greet', new At('HEAD'))]
    public function escapeCallback(CallbackInfo $info): mixed
    {
        return $info;
    }
}


final class EscapingCallbackTarget
{
    public function greet(): string
    {
        return 'original';
    }
}

it('rejects a virtual CallbackInfo value escaping through a handler return', function (): void {
    expect(function (): void {
        (new ReflectionClass(EscapingCallbackTarget::class))->inject(EscapingCallbackMixin::class);
    })->toThrow(InvalidArgumentException::class, 'CallbackInfo value escaped');

    expect((new EscapingCallbackTarget())->greet())->toBe('original');
});

#[Mixin(ExplicitCallbackBindingTarget::class)]
final class ExplicitCallbackBindingMixin
{
    private function __construct() {}

    /** @param CallbackInfoReturnable<int> $info */
    #[Inject('score', new At('RETURN'), id: 'score-callback')]
    public function bind(CallbackInfoReturnable $info, #[Parameter(ordinal: 1)] int $renamed, #[Local(name: 'local')] int $other): void
    {
        if ($info->getId() === 'score-callback' && $info->getMethodName() === 'score') {
            $info->setReturnValue($info->getReturnValue() + $renamed + $other);
        }
    }
}

final class ExplicitCallbackBindingTarget
{
    public function score(string $name, int $value): int
    {
        $local = $value + 1;

        return $local;
    }
}

it('supports explicit local names, parameter ordinals, and virtual method names', function (): void {
    (new ReflectionClass(ExplicitCallbackBindingTarget::class))->inject(ExplicitCallbackBindingMixin::class);

    expect((new ExplicitCallbackBindingTarget())->score('person', 3))->toBe(11);
});

#[Mixin(NamedCallbackBindingTarget::class)]
final class NamedCallbackBindingMixin
{
    private function __construct() {}

    /** @param CallbackInfoReturnable<string> $info */
    #[Inject('score', new At('RETURN'))]
    public function bind(CallbackInfoReturnable $info, #[Parameter(name: 'foo')] string $renamed): void
    {
        $info->setReturnValue($info->getReturnValue() . $renamed);
    }
}

final class NamedCallbackBindingTarget
{
    public function score(string $foo): string
    {
        return 'value';
    }
}

it('supports explicit parameter names independent of handler names', function (): void {
    (new ReflectionClass(NamedCallbackBindingTarget::class))->inject(NamedCallbackBindingMixin::class);

    expect((new NamedCallbackBindingTarget())->score('!'))->toBe('value!');
});

it('rejects contradictory callback binding declarations', function (): void {
    expect(static fn() => new Parameter(ordinal: -1))
        ->toThrow(InvalidArgumentException::class, 'ordinal')
        ->and(static fn() => new Parameter(ordinal: 0, name: 'foo'))
        ->toThrow(InvalidArgumentException::class, 'ordinal or name')
        ->and(static fn() => new Parameter(name: ''))
        ->toThrow(InvalidArgumentException::class, 'name')
        ->and(static fn() => new Local(''))
        ->toThrow(InvalidArgumentException::class, 'name');
});

describe('Parameter', function (): void {
    covers([Parameter::class, ...COMMUNISM_INJECTOR_COVERAGE_CLASSES]);

    it('rejects an empty explicit callback parameter name', function (): void {

        expect(static fn() => new Parameter(name: ''))
            ->toThrow(InvalidArgumentException::class, 'name');
    });
});

final class CallbackConstantOptimizationTarget
{
    public function run(): void {}
}

#[Mixin(CallbackConstantOptimizationTarget::class)]
final class CallbackConstantOptimizationMixin
{
    private function __construct() {}

    #[Inject('run', new At('HEAD'), id: 'foo')]
    public function check(CallbackInfo $info): void
    {
        if ($info->getId() === 'foo') {
            $GLOBALS['callback_constant_optimization_ran'] = true;
        }
    }
}

it('folds virtual callback getter comparisons during inlining', function (): void {
    unset($GLOBALS['callback_constant_optimization_ran']);
    (new ReflectionClass(CallbackConstantOptimizationTarget::class))->inject(CallbackConstantOptimizationMixin::class);

    (new CallbackConstantOptimizationTarget())->run();
    expect(callbackGlobalWasSet('callback_constant_optimization_ran'))->toBeTrue();

    $comparisons = array_filter(
        Decompiler::decompile(CallbackConstantOptimizationTarget::class . '::run')->instructions(),
        static fn($instruction): bool => in_array($instruction->name, ['IS_EQUAL', 'IS_IDENTICAL'], true)
            && $instruction->operand1->kind === 'constant'
            && $instruction->operand2->kind === 'constant',
    );
    expect($comparisons)->toBeEmpty();
});
