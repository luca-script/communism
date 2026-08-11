<?php

declare(strict_types=1);

use Communism\Mixin\At;
use Communism\Mixin\CallbackInfo;
use Communism\Mixin\Inject;
use Communism\Mixin\Mixin;
use Communism\Reflect\ReflectionClass;
use Communism\Mixin\Surrogate;

final class SurrogateFallbackTarget
{
    public function greet(string $person): string
    {
        $suffix = '!';

        return $person . $suffix;
    }
}

#[Mixin(SurrogateFallbackTarget::class)]
final class SurrogateFallbackMixin
{
    private function __construct() {}
    #[Inject('greet', new At('HEAD'))]
    public function greetCallback(CallbackInfo $info, string $person, string $unavailableLocal): void
    {
        $info->cancel('The primary handler should not be selected');
    }

    #[Surrogate]
    public function greetCallbackSurrogate(CallbackInfo $info, string $person): void
    {
        if ($person === 'blocked') {
            $info->cancel('blocked');
        }
    }
}


it('selects a Mixin-style surrogate when the primary callback cannot capture locals', function (): void {
    (new ReflectionClass(SurrogateFallbackTarget::class))->inject(SurrogateFallbackMixin::class);

    expect((new SurrogateFallbackTarget())->greet('Alice'))->toBe('Alice!')
        ->and((new SurrogateFallbackTarget())->greet('blocked'))->toBe('');
});

#[Mixin(SurrogateInvalidTarget::class)]
final class SurrogateInvalidMixin
{
    private function __construct() {}
    #[Surrogate]
    public function orphanSurrogate(): void {}
}

final class SurrogateInvalidTarget
{
    public function run(): void {}
}


it('rejects a surrogate without a handler before changing the target', function (): void {
    expect(static function (): void {
        (new ReflectionClass(SurrogateInvalidTarget::class))->inject(SurrogateInvalidMixin::class);
    })
        ->toThrow(InvalidArgumentException::class, 'has no handler method orphan');

    expect(get_class_methods(SurrogateInvalidTarget::class))->not->toContain('orphanSurrogate');
});
