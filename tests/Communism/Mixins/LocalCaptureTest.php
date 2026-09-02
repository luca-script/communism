<?php

declare(strict_types=1);

use Communism\Mixin\At;
use Communism\Mixin\CallbackInjectionException;
use Communism\Mixin\CallbackInfo;
use Communism\Mixin\Inject;
use Communism\Mixin\LocalCapture;
use Communism\Mixin\Mixin;

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
    (new Communism\Reflect\ReflectionClass(LocalCaptureSoftTarget::class))->inject(LocalCaptureSoftMixin::class);

    expect((new LocalCaptureSoftTarget())->greet())->toBe('original');
});

it('fails before mutation when CAPTURE_FAILHARD cannot capture a local', function (): void {
    try {
        (new Communism\Reflect\ReflectionClass(LocalCaptureHardTarget::class))->inject(LocalCaptureHardMixin::class);
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
    (new Communism\Reflect\ReflectionClass(LocalCaptureNoCaptureTarget::class))->inject(LocalCaptureNoCaptureMixin::class);

    $target = new LocalCaptureNoCaptureTarget();
    expect($target->greet('allowed'))->toBe('hello allowed')
        ->and($target->greet('blocked'))->toBe('');
});
