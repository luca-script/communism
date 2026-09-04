<?php

declare(strict_types=1);

use Communism\Reflect\ReflectionMethod;

describe('ReflectionMethod', function (): void {
    covers(ReflectionMethod::class);

    final class ReflectionMethodCoverageTarget
    {
        public function publicMethod(): string
        {
            return 'public';
        }

        protected function protectedMethod(): string
        {
            return 'protected';
        }

        public static function staticMethod(): string
        {
            return 'static';
        }
    }

    it('exposes method metadata and temporary visibility helpers', function (): void {
        $method = new ReflectionMethod(ReflectionMethodCoverageTarget::class, 'publicMethod');

        expect($method->getName())->toBe('publicMethod')
            ->and($method->getDeclaringClass()->getName())->toBe(ReflectionMethodCoverageTarget::class)
            ->and($method->isStatic())->toBeFalse();

        expect($method->withPublic(static fn(): string => 'public callback'))->toBe('public callback');

        expect($method->withPrivate(function (): string {
            expect((new \ReflectionMethod(ReflectionMethodCoverageTarget::class, 'publicMethod'))->isPrivate())
                ->toBeTrue();

            return 'private callback';
        }))->toBe('private callback');
        expect((new \ReflectionMethod(ReflectionMethodCoverageTarget::class, 'publicMethod'))->isPublic())->toBeTrue();

        expect($method->withProtected(static fn(): string => 'protected callback'))->toBe('protected callback');
        expect((new \ReflectionMethod(ReflectionMethodCoverageTarget::class, 'publicMethod'))->isPublic())->toBeTrue();
    });

    it('changes method flags and restores visibility after an exception', function (): void {
        $method = new ReflectionMethod(ReflectionMethodCoverageTarget::class, 'publicMethod');

        $method->setPrivate();
        expect((new \ReflectionMethod(ReflectionMethodCoverageTarget::class, 'publicMethod'))->isPrivate())->toBeTrue();

        $method->setProtected();
        expect((new \ReflectionMethod(ReflectionMethodCoverageTarget::class, 'publicMethod'))->isProtected())->toBeTrue();

        $method->setPublic(false);
        expect((new \ReflectionMethod(ReflectionMethodCoverageTarget::class, 'publicMethod'))->isPublic())->toBeFalse();

        $method->setPublic();
        expect(fn(): mixed => $method->withPublic(static function (): never {
            throw new RuntimeException('callback failed');
        }))->toThrow(RuntimeException::class);
        expect((new \ReflectionMethod(ReflectionMethodCoverageTarget::class, 'publicMethod'))->isPublic())->toBeTrue();
    });

    it('reports static methods and toggles the static flag', function (): void {
        $method = new ReflectionMethod(ReflectionMethodCoverageTarget::class, 'staticMethod');

        expect($method->isStatic())->toBeTrue();

        $method->setStatic(false);
        expect((new \ReflectionMethod(ReflectionMethodCoverageTarget::class, 'staticMethod'))->isStatic())->toBeFalse();

        $method->setStatic();
        expect((new \ReflectionMethod(ReflectionMethodCoverageTarget::class, 'staticMethod'))->isStatic())->toBeTrue();
    });
});
