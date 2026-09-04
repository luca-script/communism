<?php

declare(strict_types=1);

use Communism\Mixin\Args;
use Communism\Mixin\CallbackInfo;
use Communism\Mixin\CallbackInfoReturnable;

describe('Args', function (): void {
    covers([Args::class, CallbackInfo::class, CallbackInfoReturnable::class]);

    it('keeps virtual Args and callback APIs unavailable at runtime', function (): void {
        $virtualArgs = (new ReflectionClass(Args::class))->newInstanceWithoutConstructor();
        $args = [
            static fn(): mixed => new Args(),
            static fn(): mixed => $virtualArgs->get(0),
            static function () use ($virtualArgs): mixed {
                $virtualArgs->set(0, 'value');
                return null;
            },
            static function () use ($virtualArgs): mixed {
                $virtualArgs->getCount();
                return null;
            },
            static function () use ($virtualArgs): mixed {
                $virtualArgs->setAll([]);
                return null;
            },
            static function () use ($virtualArgs): mixed {
                $virtualArgs->count();
                return null;
            },
            static function () use ($virtualArgs): mixed {
                $virtualArgs->offsetExists(0);
                return null;
            },
            static function () use ($virtualArgs): mixed {
                $virtualArgs->offsetGet(0);
                return null;
            },
            static function () use ($virtualArgs): mixed {
                $virtualArgs->offsetSet(0, 'value');
                return null;
            },
            static function () use ($virtualArgs): mixed {
                $virtualArgs->offsetUnset(0);
                return null;
            },
        ];

        foreach ($args as $call) {
            expect($call)->toThrow(LogicException::class);
        }

        $virtual = (new ReflectionClass(CallbackInfo::class))->newInstanceWithoutConstructor();
        foreach ([
            static fn() => $virtual->getId(),
            static fn() => $virtual->isCancellable(),
            static fn() => $virtual->isCancelled(),
            static fn() => $virtual->cancel(),
            static fn() => (string) $virtual,
        ] as $call) {
            expect($call)->toThrow(LogicException::class);
        }

        $returnable = (new ReflectionClass(CallbackInfoReturnable::class))->newInstanceWithoutConstructor();
        expect(static fn() => $returnable->getReturnValue())->toThrow(LogicException::class)
            ->and(static fn() => $returnable->setReturnValue(null))->toThrow(LogicException::class)
            ->and(static fn() => $returnable->setReturnValue('value'))->toThrow(LogicException::class);
    });
});
