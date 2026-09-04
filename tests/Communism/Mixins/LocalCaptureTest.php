<?php

declare(strict_types=1);

use Communism\Mixin\At;
use Communism\Mixin\CallbackInjectionException;
use Communism\Mixin\CallbackInfo;
use Communism\Mixin\Coerce;
use Communism\Mixin\Inject;
use Communism\Mixin\Local;
use Communism\Mixin\LocalCapture;
use Communism\Mixin\Mixin;
use Communism\Reflect\ReflectionClass;

#[Mixin(LocalCaptureSoftTarget::class)]
final class LocalCaptureSoftMixin
{
    private function __construct() {}
    #[Inject('greet', new At('HEAD'), locals: LocalCapture::FAILSOFT)]
    public function softCallback(CallbackInfo $info, string $missing): void
    {
        $info->cancel('should not run');
    }
}


final class LocalCaptureSoftTarget
{
    public function greet(): string
    {
        return 'original';
    }
}

#[Mixin(LocalCaptureHardTarget::class)]
final class LocalCaptureHardMixin
{
    private function __construct() {}
    #[Inject('greet', new At('HEAD'))]
    public function hardCallback(CallbackInfo $info, string $missing): void
    {
        $info->cancel('should not run');
    }
}


final class LocalCaptureHardTarget
{
    public function greet(): string
    {
        return 'original';
    }
}

#[Mixin(LocalCaptureNoCaptureTarget::class)]
final class LocalCaptureNoCaptureMixin
{
    private function __construct() {}
    #[Inject('greet', new At('HEAD'), locals: LocalCapture::NO_CAPTURE)]
    public function callback(CallbackInfo $info, string $person): void
    {
        if ($person === 'blocked') {
            $info->cancel('blocked');
        }
    }
}


final class LocalCaptureNoCaptureTarget
{
    public function greet(string $person): string
    {
        return 'hello ' . $person;
    }
}

it('skips a callback when FAILSOFT cannot capture a local', function (): void {
    (new ReflectionClass(LocalCaptureSoftTarget::class))->inject(LocalCaptureSoftMixin::class);

    expect((new LocalCaptureSoftTarget())->greet())->toBe('original');
});

it('fails before mutation when CAPTURE_FAILHARD cannot capture a local', function (): void {
    try {
        (new ReflectionClass(LocalCaptureHardTarget::class))->inject(LocalCaptureHardMixin::class);
        throw new \RuntimeException('Hard local capture unexpectedly succeeded');
    } catch (CallbackInjectionException $exception) {
        expect($exception->point)->toBe('HEAD')
            ->and($exception->targetMethod)->toBe('LocalCaptureHardTarget::greet')
            ->and($exception->handlerMethod)->toBe('LocalCaptureHardMixin::hardCallback')
            ->and($exception->start)->toBe(0)
            ->and($exception->end)->toBe(0)
            ->and($exception->getPrevious())->toBeInstanceOf(\InvalidArgumentException::class)
            ->and($exception->getMessage())->toContain('cannot be captured');
    }

    expect((new LocalCaptureHardTarget())->greet())->toBe('original')
        ->and(in_array('hardCallback', get_class_methods(LocalCaptureHardTarget::class), true))->toBeFalse();
});

it('allows target arguments while NO_CAPTURE rejects locals', function (): void {
    (new ReflectionClass(LocalCaptureNoCaptureTarget::class))->inject(LocalCaptureNoCaptureMixin::class);

    $target = new LocalCaptureNoCaptureTarget();
    expect($target->greet('allowed'))->toBe('hello allowed')
        ->and($target->greet('blocked'))->toBe('');
});

#[Mixin(LocalCaptureUninitializedTarget::class)]
final class LocalCaptureUninitializedMixin
{
    private function __construct() {}

    #[Inject('greet', new At('HEAD'))]
    public function callback(CallbackInfo $info, #[Local] string $later): void
    {
        $info->cancel($later);
    }
}

final class LocalCaptureUninitializedTarget
{
    public function greet(): string
    {
        $later = 'initialized';

        return $later;
    }
}

it('rejects locals that are declared but not initialized at the injection point', function (): void {
    expect(static function (): void {
        (new ReflectionClass(LocalCaptureUninitializedTarget::class))->inject(LocalCaptureUninitializedMixin::class);
    })->toThrow(CallbackInjectionException::class, 'not initialized at HEAD');

    expect((new LocalCaptureUninitializedTarget())->greet())->toBe('initialized');
});

#[Mixin(PositionalLocalCaptureTarget::class)]
final class PositionalLocalCaptureMixin
{
    private function __construct() {}

    #[Inject('greet', new At('TAIL'), locals: LocalCapture::CAPTURE_FAILHARD, cancellable: true)]
    public function capture(CallbackInfo $info, string $person, string $firstLocal, int $secondLocal): void
    {
        $GLOBALS['positional_local_capture'] = [$person, $firstLocal, $secondLocal];
    }
}

final class PositionalLocalCaptureTarget
{
    public function greet(string $person): string
    {
        $greeting = 'hello ' . $person;
        $count = 7;

        return $greeting . $count;
    }
}

it('captures initialized locals positionally after target parameters', function (): void {
    unset($GLOBALS['positional_local_capture']);
    (new ReflectionClass(PositionalLocalCaptureTarget::class))->inject(PositionalLocalCaptureMixin::class);

    expect((new PositionalLocalCaptureTarget())->greet('comrade'))->toBe('hello comrade7');
    expect($GLOBALS)->toMatchArray([
        'positional_local_capture' => ['comrade', 'hello comrade', 7],
    ]);
});

#[Mixin(PositionalNoCaptureTarget::class)]
final class PositionalNoCaptureMixin
{
    private function __construct() {}

    #[Inject('run', new At('TAIL'), locals: LocalCapture::NO_CAPTURE)]
    public function capture(CallbackInfo $info, string $unboundLocal): void {}
}

final class PositionalNoCaptureTarget
{
    public function run(): string
    {
        $local = 'original';

        return $local;
    }
}

it('rejects positional local capture when NO_CAPTURE is requested', function (): void {
    expect(static function (): void {
        (new ReflectionClass(PositionalNoCaptureTarget::class))->inject(PositionalNoCaptureMixin::class);
    })
        ->toThrow(CallbackInjectionException::class, 'local capture is disabled');

    expect((new PositionalNoCaptureTarget())->run())->toBe('original');
});

#[Mixin(TypedLocalCaptureTarget::class)]
final class TypedLocalCaptureMixin
{
    private function __construct() {}

    #[Inject('run', new At('TAIL'))]
    public function capture(CallbackInfo $info, #[Local] string $count): void {}
}

final class TypedLocalCaptureTarget
{
    public function run(): int
    {
        $count = 7;

        return $count;
    }
}

it('rejects a known captured local with an incompatible callback type', function (): void {
    expect(static fn() => (new ReflectionClass(TypedLocalCaptureTarget::class))->inject(TypedLocalCaptureMixin::class))
        ->toThrow(CallbackInjectionException::class, 'target local provides int');
});

#[Mixin(CoercedLocalCaptureTarget::class)]
final class CoercedLocalCaptureMixin
{
    private function __construct() {}

    #[Inject('run', new At('TAIL'))]
    public function capture(CallbackInfo $info, #[Local, Coerce] float $count): void
    {
        CoercedLocalCaptureObservation::record($count + 0.5);
    }
}

final class CoercedLocalCaptureObservation
{
    public static ?float $value = null;

    public static function record(float $value): void
    {
        self::$value = $value;
    }
}

final class CoercedLocalCaptureTarget
{
    public function run(): int
    {
        $count = 7;

        return $count;
    }
}

it('coerces a known captured local for a callback parameter', function (): void {
    CoercedLocalCaptureObservation::$value = null;
    (new ReflectionClass(CoercedLocalCaptureTarget::class))->inject(CoercedLocalCaptureMixin::class);

    $result = (new CoercedLocalCaptureTarget())->run();
    expect($result)->toBe(7)
        ->and(CoercedLocalCaptureObservation::$value)->toBe(7.5);
});
