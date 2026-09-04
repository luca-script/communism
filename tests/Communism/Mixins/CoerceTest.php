<?php

declare(strict_types=1);

use Communism\Mixin\At;
use Communism\Mixin\CallbackInfo;
use Communism\Mixin\Coerce;
use Communism\Mixin\Inject;
use Communism\Mixin\HandlerValidationException;
use Communism\Mixin\Mixin;
use Communism\Mixin\Redirect;
use Communism\Internals\Needle\Decompiler;
use Communism\Reflect\ReflectionClass;

describe('Coerce', function (): void {
    covers([Coerce::class, Redirect::class, ...COMMUNISM_INJECTOR_COVERAGE_CLASSES]);

    interface CoerceReceiverContract
    {
        public function hidden(string $value): string;
    }

    final class CoerceReceiverService implements CoerceReceiverContract
    {
        public function hidden(string $value): string
        {
            return $value . '!';
        }
    }

    #[Mixin(CoerceReceiverTarget::class)]
    final class CoerceReceiverMixin
    {
        private function __construct() {}

        #[Redirect('run', new At('INVOKE', '->hidden'))]
        public function redirect(CoerceReceiverContract $receiver, string $value): string
        {
            return $receiver->hidden(strtoupper($value));
        }
    }

    final class CoerceReceiverTarget
    {
        public function run(CoerceReceiverService $service, string $value): string
        {
            return $service->hidden($value);
        }
    }

    it('validates a Redirect member receiver against its declared interface', function (): void {
        (new ReflectionClass(CoerceReceiverTarget::class))->inject(CoerceReceiverMixin::class);

        expect((new CoerceReceiverTarget())->run(new CoerceReceiverService(), 'ok'))->toBe('OK!');
    });

    #[Mixin(CoerceReceiverUnionTarget::class)]
    final class CoerceReceiverUnionMixin
    {
        private function __construct() {}

        #[Redirect('run', new At('INVOKE', '->hidden'))]
        public function redirect(CoerceReceiverContract|stdClass $receiver, string $value): string
        {
            return strtoupper($value);
        }
    }

    final class CoerceReceiverUnionTarget
    {
        public function run(CoerceReceiverService $service, string $value): string
        {
            return $service->hidden($value);
        }
    }

    it('validates union-typed Redirect receivers', function (): void {
        (new ReflectionClass(CoerceReceiverUnionTarget::class))->inject(CoerceReceiverUnionMixin::class);

        expect((new CoerceReceiverUnionTarget())->run(new CoerceReceiverService(), 'ok'))->toBe('OK');
    });

    #[Mixin(CoerceReceiverRejectTarget::class)]
    final class CoerceReceiverRejectMixin
    {
        private function __construct() {}

        #[Redirect('run', new At('INVOKE', '->hidden'))]
        public function redirect(string $receiver, string $value): string
        {
            return $value;
        }
    }

    final class CoerceReceiverRejectTarget
    {
        public function run(CoerceReceiverService $service, string $value): string
        {
            return $service->hidden($value);
        }
    }

    it('rejects an incompatible Redirect member receiver before mutation', function (): void {
        expect(static fn() => (new ReflectionClass(CoerceReceiverRejectTarget::class))->inject(CoerceReceiverRejectMixin::class))
            ->toThrow(InvalidArgumentException::class, 'Redirect receiver');

        expect((new CoerceReceiverRejectTarget())->run(new CoerceReceiverService(), 'ok'))->toBe('ok!');
    });

    #[Mixin(CoerceDynamicReceiverTarget::class)]
    final class CoerceDynamicReceiverMixin
    {
        private function __construct() {}

        #[Redirect('run', new At('INVOKE', '->hidden'))]
        public function redirect(#[Coerce] CoerceReceiverContract $receiver, string $value): string
        {
            return $receiver->hidden(strtoupper($value));
        }
    }

    final class CoerceDynamicReceiverTarget
    {
        public function run($service, string $value): string
        {
            return $service->hidden($value);
        }
    }

    it('allows an explicitly coerced Redirect receiver with an unknown target type', function (): void {
        (new ReflectionClass(CoerceDynamicReceiverTarget::class))->inject(CoerceDynamicReceiverMixin::class);

        expect((new CoerceDynamicReceiverTarget())->run(new CoerceReceiverService(), 'ok'))->toBe('OK!');
    });

    #[Mixin(CoerceDynamicReceiverRejectTarget::class)]
    final class CoerceDynamicReceiverRejectMixin
    {
        private function __construct() {}

        #[Redirect('run', new At('INVOKE', '->hidden'))]
        public function redirect(CoerceReceiverContract $receiver, string $value): string
        {
            return $receiver->hidden(strtoupper($value));
        }
    }

    final class CoerceDynamicReceiverRejectTarget
    {
        public function run($service, string $value): string
        {
            return $service->hidden($value);
        }
    }

    it('allows an untyped Redirect receiver when the runtime object satisfies the handler', function (): void {
        (new ReflectionClass(CoerceDynamicReceiverRejectTarget::class))->inject(CoerceDynamicReceiverRejectMixin::class);

        expect((new CoerceDynamicReceiverRejectTarget())->run(new CoerceReceiverService(), 'ok'))->toBe('OK!');
    });

    #[Mixin(CoerceTarget::class)]
    final class CoerceMixin
    {
        private function __construct() {}
        #[Inject('value', new At('HEAD'))]
        public function inspectValue(CallbackInfo $info, #[Coerce] float $value): void
        {
            if ($value > 2.5) {
                $info->cancel('coerced value was too large');
            }
        }
    }


    final class CoerceTarget
    {
        public function value(int $value): int
        {
            return $value + 1;
        }
    }

    #[Mixin(CoerceUnionTarget::class)]
    final class CoerceUnionMixin
    {
        private function __construct() {}

        #[Inject('value', new At('HEAD'))]
        public function inspectValue(CallbackInfo $info, int|float $value): void
        {
            $GLOBALS['coerce_union_value'] = $value;
        }
    }

    final class CoerceUnionTarget
    {
        public function value(int|float $value): int|float
        {
            return $value;
        }
    }

    #[Mixin(CoerceUnionRejectTarget::class)]
    final class CoerceUnionRejectMixin
    {
        private function __construct() {}

        #[Inject('value', new At('HEAD'))]
        public function inspectValue(CallbackInfo $info, string|bool $value): void {}
    }

    final class CoerceUnionRejectTarget
    {
        public function value(int|float $value): int|float
        {
            return $value;
        }
    }

    it('validates union callback parameters and rejects incompatible unions', function (): void {
        (new ReflectionClass(CoerceUnionTarget::class))->inject(CoerceUnionMixin::class);

        expect((new CoerceUnionTarget())->value(4))->toBe(4)
            ->and($GLOBALS['coerce_union_value'])->toBe(4);
        expect(static fn() => (new ReflectionClass(CoerceUnionRejectTarget::class))->inject(CoerceUnionRejectMixin::class))
            ->toThrow(InvalidArgumentException::class, 'expects string|bool');
    });

    #[Mixin(CoerceUnionCastTarget::class)]
    final class CoerceUnionCastMixin
    {
        private function __construct() {}

        #[Coerce]
        #[Inject('value', new At('HEAD'))]
        public function inspectValue(CallbackInfo $info, float|string $value): void
        {
            $GLOBALS['coerce_union_cast_type'] = is_float($value);
        }
    }

    final class CoerceUnionCastTarget
    {
        public function value(int $value): int
        {
            return $value;
        }
    }

    it('casts a coerced callback value to a compatible union arm', function (): void {
        (new ReflectionClass(CoerceUnionCastTarget::class))->inject(CoerceUnionCastMixin::class);

        expect((new CoerceUnionCastTarget())->value(4))->toBe(4)
            ->and($GLOBALS['coerce_union_cast_type'])->toBeTrue();
    });

    interface CoerceIntersectionLeft
    {
        public function marker(): bool;
    }

    interface CoerceIntersectionRight {}

    final class CoerceIntersectionValue implements CoerceIntersectionLeft, CoerceIntersectionRight
    {
        public function marker(): bool
        {
            return true;
        }
    }

    #[Mixin(CoerceIntersectionTarget::class)]
    final class CoerceIntersectionMixin
    {
        private function __construct() {}

        #[Inject('value', new At('HEAD'))]
        public function inspectValue(CallbackInfo $info, CoerceIntersectionLeft $value): void
        {
            $GLOBALS['coerce_intersection_seen'] = $value->marker();
        }
    }

    final class CoerceIntersectionTarget
    {
        public function value(CoerceIntersectionLeft&CoerceIntersectionRight $value): string
        {
            return 'ok';
        }
    }

    it('validates a callback against an intersection-typed target parameter', function (): void {
        (new ReflectionClass(CoerceIntersectionTarget::class))->inject(CoerceIntersectionMixin::class);

        expect((new CoerceIntersectionTarget())->value(new CoerceIntersectionValue()))->toBe('ok')
            ->and($GLOBALS['coerce_intersection_seen'])->toBeTrue();
    });

    #[Mixin(CoerceIntersectionRejectTarget::class)]
    final class CoerceIntersectionRejectMixin
    {
        private function __construct() {}

        #[Inject('value', new At('HEAD'))]
        public function inspectValue(CallbackInfo $info, stdClass $value): void {}
    }

    final class CoerceIntersectionRejectTarget
    {
        public function value(CoerceIntersectionLeft&CoerceIntersectionRight $value): string
        {
            return 'ok';
        }
    }

    it('rejects a callback type incompatible with every intersection component', function (): void {
        expect(static fn() => (new ReflectionClass(CoerceIntersectionRejectTarget::class))->inject(CoerceIntersectionRejectMixin::class))
            ->toThrow(InvalidArgumentException::class, 'expects stdClass');
    });

    it('allows an explicitly coerced compatible callback parameter', function (): void {
        (new ReflectionClass(CoerceTarget::class))->inject(CoerceMixin::class);

        expect((new CoerceTarget())->value(2))->toBe(3)
            ->and((new CoerceTarget())->value(3))->toBe(0);
    });

    #[Mixin(CoerceParameterTarget::class)]
    final class CoerceParameterMixin
    {
        private function __construct() {}

        #[Inject('value', new At('HEAD'))]
        public function inspectValue(CallbackInfo $info, #[Coerce] float $value): void
        {
            if ($value !== 2.0) {
                $info->cancel('parameter coercion was not applied');
            }
        }
    }

    final class CoerceParameterTarget
    {
        public function value(int $value): int
        {
            return $value + 1;
        }
    }

    it('accepts and emits a cast for parameter-level callback Coerce', function (): void {
        (new ReflectionClass(CoerceParameterTarget::class))->inject(CoerceParameterMixin::class);

        expect((new CoerceParameterTarget())->value(2))->toBe(3)
            ->and((new CoerceParameterTarget())->value(3))->toBe(0);

        expect(array_filter(
            Decompiler::decompile(CoerceParameterTarget::class . '::value')->instructions(),
            static fn($instruction): bool => $instruction->name === 'CAST' && $instruction->extendedValue === 5,
        ))->not->toBeEmpty();
    });

    #[Mixin(CoerceRejectTarget::class)]
    final class CoerceRejectMixin
    {
        private function __construct() {}
        #[Inject('value', new At('HEAD'))]
        public function inspectValue(CallbackInfo $info, string $value): void {}
    }


    final class CoerceRejectTarget
    {
        public function value(int $value): int
        {
            return $value + 1;
        }
    }

    it('rejects an incompatible callback parameter without Coerce before mutation', function (): void {
        expect(static function (): void {
            (new ReflectionClass(CoerceRejectTarget::class))->inject(CoerceRejectMixin::class);
        })->toThrow(InvalidArgumentException::class, 'add #[Coerce]');

        expect((new CoerceRejectTarget())->value(2))->toBe(3);
    });

    function coerceRedirectFunction(int $value): int
    {
        return $value * 2;
    }

    #[Mixin(CoerceRedirectTarget::class)]
    final class CoerceRedirectMixin
    {
        private function __construct() {}
        #[Redirect('value', new At('INVOKE', 'coerceRedirectFunction'))]
        #[Coerce]
        public function redirectValue(#[Coerce] float $value): float
        {
            return $value + 4;
        }
    }


    final class CoerceRedirectTarget
    {
        public function value(int $value): int
        {
            return coerceRedirectFunction($value);
        }
    }

    it('allows Coerce on a Redirect argument mapped to an invocation', function (): void {
        (new ReflectionClass(CoerceRedirectTarget::class))->inject(CoerceRedirectMixin::class);

        expect((new CoerceRedirectTarget())->value(2))->toBe(6);
    });

    #[Mixin(CoerceRedirectReturnTarget::class)]
    final class CoerceRedirectReturnMixin
    {
        private function __construct() {}

        #[Redirect('value', new At('INVOKE', 'coerceRedirectReturnSource'))]
        #[Coerce]
        public function redirectReturn(): float
        {
            return 3.5;
        }
    }

    function coerceRedirectReturnSource(): int
    {
        return 1;
    }

    final class CoerceRedirectReturnTarget
    {
        public function value(): int
        {
            return coerceRedirectReturnSource();
        }
    }

    it('casts a coerced Redirect replacement result before a strict return', function (): void {
        (new ReflectionClass(CoerceRedirectReturnTarget::class))->inject(CoerceRedirectReturnMixin::class);

        expect((new CoerceRedirectReturnTarget())->value())->toBe(3);
    });

    #[Mixin(CoerceFieldTarget::class)]
    final class CoerceFieldMixin
    {
        private function __construct() {}

        #[Redirect('write', new At('FIELD', '::value'))]
        public function redirectField(#[Coerce] float $value): void
        {
            $GLOBALS['coerce_field_value'] = $value + 0.5;
        }
    }

    final class CoerceFieldTarget
    {
        public int $value = 1;

        public function write(int $value): void
        {
            $this->value = $value;
        }
    }

    it('allows Coerce on a Redirect field value with a compatible numeric type', function (): void {
        (new ReflectionClass(CoerceFieldTarget::class))->inject(CoerceFieldMixin::class);

        $target = new CoerceFieldTarget();
        $target->write(7);

        expect($target->value)->toBe(1)
            ->and($GLOBALS['coerce_field_value'])->toBe(7.5)
            ->and(array_filter(
                Decompiler::decompile(CoerceFieldTarget::class . '::write')->instructions(),
                static fn($instruction): bool => $instruction->name === 'CAST' && $instruction->extendedValue === 5,
            ))->not->toBeEmpty();
    });

    #[Mixin(CoerceFieldReadTarget::class)]
    final class CoerceFieldReadMixin
    {
        private function __construct() {}

        #[Redirect('read', new At('FIELD', '::value'))]
        #[Coerce]
        public function redirectFieldRead(): int
        {
            return 3;
        }
    }

    final class CoerceFieldReadTarget
    {
        public float $value = 1.5;

        public function read(): float
        {
            return $this->value;
        }
    }

    it('casts a coerced Redirect field-read result to the property type', function (): void {
        (new ReflectionClass(CoerceFieldReadTarget::class))->inject(CoerceFieldReadMixin::class);

        expect((new CoerceFieldReadTarget())->read())->toBe(3.0);
    });

    function coerceUnionFieldSource(bool $asFloat): int|float
    {
        return $asFloat ? 3.5 : 3;
    }

    #[Mixin(CoerceUnionFieldTarget::class)]
    final class CoerceUnionFieldMixin
    {
        private function __construct() {}

        #[Redirect('read', new At('FIELD', '::value'))]
        public function redirectField(): int|float
        {
            return coerceUnionFieldSource(false);
        }
    }

    final class CoerceUnionFieldTarget
    {
        public int|float $value = 1;

        public function read(): int|float
        {
            return $this->value;
        }
    }

    it('validates union Redirect field-read results', function (): void {
        (new ReflectionClass(CoerceUnionFieldTarget::class))->inject(CoerceUnionFieldMixin::class);

        expect((new CoerceUnionFieldTarget())->read())->toBe(3);
    });

    #[Mixin(CoerceUnionFieldRejectTarget::class)]
    final class CoerceUnionFieldRejectMixin
    {
        private function __construct() {}

        #[Redirect('read', new At('FIELD', '::value'))]
        public function redirectField(): string|bool
        {
            return coerceUnionFieldSource(false) > 0 ? true : 'invalid';
        }
    }

    final class CoerceUnionFieldRejectTarget
    {
        public int|float $value = 1;

        public function read(): int|float
        {
            return $this->value;
        }
    }

    it('rejects incompatible union Redirect field-read results before mutation', function (): void {
        expect(static fn() => (new ReflectionClass(CoerceUnionFieldRejectTarget::class))->inject(CoerceUnionFieldRejectMixin::class))
            ->toThrow(InvalidArgumentException::class, 'uses string|bool');
    });

    #[Mixin(CoerceFieldRejectTarget::class)]
    final class CoerceFieldRejectMixin
    {
        private function __construct() {}

        #[Redirect('write', new At('FIELD', '::value'))]
        public function redirectField(string $value): void {}
    }

    final class CoerceFieldRejectTarget
    {
        public int $value = 1;

        public function write(int $value): void
        {
            $this->value = $value;
        }
    }

    it('rejects an incompatible Redirect field parameter before mutation', function (): void {
        try {
            (new ReflectionClass(CoerceFieldRejectTarget::class))->inject(CoerceFieldRejectMixin::class);
            throw new RuntimeException('Expected handler validation to fail');
        } catch (HandlerValidationException $exception) {
            expect($exception->handlerMethod)->toBe(CoerceFieldRejectMixin::class . '::redirectField')
                ->and($exception->targetMethod)->toBe(CoerceFieldRejectTarget::class . '::write')
                ->and($exception->point)->toBe('FIELD')
                ->and($exception->start)->toBeGreaterThanOrEqual(0)
                ->and($exception->end)->toBeGreaterThan($exception->start)
                ->and($exception->selector)->toContain('FIELD target ::value')
                ->and($exception->getMessage())->toContain('add #[Coerce]')
                ->and($exception->getPrevious())->toBeInstanceOf(InvalidArgumentException::class);
        }

        expect((new CoerceFieldRejectTarget())->value)->toBe(1);
    });

    class CoerceConstructorTarget {}
    final class CoerceConstructorReplacement {}

    #[Mixin(CoerceConstructorRedirectTarget::class)]
    final class CoerceConstructorMixin
    {
        private function __construct() {}

        #[Redirect('make', new At('NEW', CoerceConstructorTarget::class))]
        #[Coerce]
        public function redirectConstructor(): CoerceConstructorReplacement
        {
            return new CoerceConstructorReplacement();
        }
    }

    final class CoerceConstructorRedirectTarget
    {
        public function make(): object
        {
            return new CoerceConstructorTarget();
        }
    }

    it('allows Coerce on a compatible Redirect constructor replacement', function (): void {
        (new ReflectionClass(CoerceConstructorRedirectTarget::class))->inject(CoerceConstructorMixin::class);

        expect((new CoerceConstructorRedirectTarget())->make())->toBeInstanceOf(CoerceConstructorReplacement::class);
    });
});
