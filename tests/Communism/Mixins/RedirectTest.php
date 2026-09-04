<?php

declare(strict_types=1);

use Communism\Mixin\At;
use Communism\Mixin\Mixin;
use Communism\Mixin\Redirect;
use Communism\Reflect\ReflectionClass;

describe('Redirect', function (): void {
    covers([Redirect::class, ...COMMUNISM_INJECTOR_COVERAGE_CLASSES]);

    function mixinArgumentTarget(int $value): int
    {
        return $value * 2;
    }

    #[Mixin(RedirectTarget::class)]
    final class RedirectMixin
    {
        private function __construct() {}
        #[Redirect('value', new At('INVOKE', 'mixinArgumentTarget'))]
        public function redirectArgumentTarget(): int
        {
            return 12;
        }
    }

    final class RedirectTarget
    {
        public function value(int $value): int
        {
            return mixinArgumentTarget($value);
        }
    }


    it('executes a Mixin-shaped Redirect handler at an invocation', function (): void {
        (new ReflectionClass(RedirectTarget::class))->inject(RedirectMixin::class);

        expect((new RedirectTarget())->value(4))->toBe(12);
    });

    #[Mixin(ComputedRedirectTarget::class)]
    final class ComputedRedirectMixin
    {
        private function __construct() {}
        #[Redirect('value', new At('INVOKE', 'mixinArgumentTarget'))]
        public function redirectWithArgument(int $value): int
        {
            return $value + 1;
        }
    }


    final class ComputedRedirectTarget
    {
        public function value(int $value): int
        {
            return mixinArgumentTarget($value);
        }
    }

    it('passes matched invocation arguments to a computed Redirect handler', function (): void {
        (new ReflectionClass(ComputedRedirectTarget::class))->inject(ComputedRedirectMixin::class);

        expect((new ComputedRedirectTarget())->value(4))->toBe(5);
    });

    final class RedirectStaticTarget
    {
        public static function compute(int $value): int
        {
            return $value * 3;
        }
    }

    #[Mixin(RedirectStaticInvocationTarget::class)]
    final class RedirectStaticInvocationMixin
    {
        private function __construct() {}
        #[Redirect('value', new At('INVOKE', ['RedirectStaticTarget::compute', ['numargs' => 1]]))]
        public function redirectStatic(int $value): int
        {
            return $value + 4;
        }
    }


    final class RedirectStaticInvocationTarget
    {
        public function value(int $value): int
        {
            return RedirectStaticTarget::compute($value);
        }
    }

    it('redirects a static invocation and honors the numargs extension', function (): void {
        (new ReflectionClass(RedirectStaticInvocationTarget::class))->inject(RedirectStaticInvocationMixin::class);

        expect((new RedirectStaticInvocationTarget())->value(4))->toBe(8);
    });

    #[Mixin(RedirectMissingTarget::class)]
    final class RedirectMissingMixin
    {
        private function __construct() {}
        #[Redirect('value', new At('INVOKE', 'missingRedirectFunction'))]
        public function redirectMissing(): int
        {
            return 1;
        }
    }


    final class RedirectMissingTarget
    {
        public function value(int $value): int
        {
            return mixinArgumentTarget($value);
        }
    }

    it('rejects a Redirect point that has no matching invocation', function (): void {
        expect(function (): void {
            (new ReflectionClass(RedirectMissingTarget::class))->inject(RedirectMissingMixin::class);
        })->toThrow(InvalidArgumentException::class, 'did not match');

        expect((new RedirectMissingTarget())->value(4))->toBe(8);
    });

    final class RedirectReceiver
    {
        public function compute(int $value): int
        {
            return $value * 2;
        }
    }

    #[Mixin(MemberRedirectTarget::class)]
    final class MemberRedirectMixin
    {
        private function __construct() {}
        #[Redirect('value', new At('INVOKE', '->compute'))]
        public function redirectMember(RedirectReceiver $receiver, int $value): int
        {
            return $receiver->compute($value) + 1;
        }
    }


    final class MemberRedirectTarget
    {
        public function value(int $value): int
        {
            $receiver = new RedirectReceiver();

            return $receiver->compute($value);
        }
    }

    it('passes the receiver and arguments to a member Redirect handler', function (): void {
        (new ReflectionClass(MemberRedirectTarget::class))->inject(MemberRedirectMixin::class);

        expect((new MemberRedirectTarget())->value(4))->toBe(9);
    });

    class RedirectWidgetBase
    {
        public function value(): string
        {
            return 'base';
        }
    }

    final class RedirectConstructedWidget extends RedirectWidgetBase
    {
        public function value(): string
        {
            return 'original';
        }
    }

    final class RedirectReplacementWidget extends RedirectWidgetBase
    {
        public function value(): string
        {
            return 'replacement';
        }
    }

    final class RedirectConstructedWidgetWithValue
    {
        public function __construct(public readonly int $value) {}
    }

    #[Mixin(ConstructorRedirectTarget::class)]
    final class ConstructorRedirectMixin
    {
        private function __construct() {}
        #[Redirect('make', new At('NEW', RedirectConstructedWidget::class))]
        public function replaceWidget(): object
        {
            return new RedirectReplacementWidget();
        }
    }

    final class ConstructorRedirectTarget
    {
        public function make(): RedirectWidgetBase
        {
            return new RedirectConstructedWidget();
        }

    }


    it('redirects a NEW point together with its constructor call', function (): void {
        (new ReflectionClass(ConstructorRedirectTarget::class))->inject(ConstructorRedirectMixin::class);

        expect((new ConstructorRedirectTarget())->make())->toBeInstanceOf(RedirectReplacementWidget::class);
        expect((new ConstructorRedirectTarget())->make()->value())->toBe('replacement');
    });

    it('redirects NEW sequences that contain constructor arguments', function (): void {
        (new ReflectionClass(ConstructorArgumentRedirectTarget::class))->inject(ConstructorArgumentRedirectMixin::class);

        $replacement = (new ConstructorArgumentRedirectTarget())->make(42);
        if (!$replacement instanceof RedirectConstructedWidgetWithValue) {
            throw new RuntimeException('Constructor Redirect did not return the replacement value object');
        }

        expect($replacement)->toBeInstanceOf(RedirectConstructedWidgetWithValue::class)
            ->and($replacement->value)->toBe(42);
    });

    #[Mixin(ConstructorArgumentMismatchTarget::class)]
    final class ConstructorArgumentMismatchMixin
    {
        private function __construct() {}

        #[Redirect('make', new At('NEW', RedirectConstructedWidgetWithValue::class))]
        public function replaceWidget(string $value): object
        {
            return new RedirectReplacementWidget();
        }
    }

    final class ConstructorArgumentMismatchTarget
    {
        public function make(int $value): object
        {
            return new RedirectConstructedWidgetWithValue($value);
        }
    }

    it('rejects an incompatible NEW handler argument before mutation', function (): void {
        expect(static fn() => (new ReflectionClass(ConstructorArgumentMismatchTarget::class))->inject(ConstructorArgumentMismatchMixin::class))
            ->toThrow(InvalidArgumentException::class, 'expects string');

        expect((new ConstructorArgumentMismatchTarget())->make(42))->toBeInstanceOf(RedirectConstructedWidgetWithValue::class);
    });

    #[Mixin(ConstructorArgumentRedirectTarget::class)]
    final class ConstructorArgumentRedirectMixin
    {
        private function __construct() {}
        #[Redirect('make', new At('NEW', RedirectConstructedWidgetWithValue::class))]
        public function replaceWidget(int $value): object
        {
            return new RedirectConstructedWidgetWithValue($value);
        }
    }

    final class ConstructorArgumentRedirectTarget
    {
        public function make(int $value): object
        {
            return new RedirectConstructedWidgetWithValue($value);
        }
    }


    #[Mixin(InvalidRedirectTarget::class)]
    final class InvalidRedirectMixin
    {
        private function __construct() {}
        #[Redirect('value', new At('INVOKE', 'mixinArgumentTarget'))]
        public function expectsTooManyArguments(int $value, int $missing): int
        {
            return $value + $missing;
        }
    }

    final class InvalidRedirectTarget
    {
        public function value(int $value): int
        {
            return mixinArgumentTarget($value);
        }
    }


    it('rejects Redirect handlers whose signatures cannot map to the invocation', function (): void {
        expect(function (): void {
            (new ReflectionClass(InvalidRedirectTarget::class))->inject(InvalidRedirectMixin::class);
        })->toThrow(InvalidArgumentException::class, 'cannot be captured');

        expect((new InvalidRedirectTarget())->value(4))->toBe(8);
    });

    #[Mixin(FieldReadRedirectTarget::class)]
    final class FieldReadRedirectMixin
    {
        private function __construct() {}
        #[Redirect('read', new At('FIELD', '::value'))]
        public function redirectFieldRead(): int
        {
            return 9;
        }
    }


    final class FieldReadRedirectTarget
    {
        public int $value = 2;

        public function read(): int
        {
            return $this->value;
        }
    }

    it('redirects a Mixin-shaped field read', function (): void {
        (new ReflectionClass(FieldReadRedirectTarget::class))->inject(FieldReadRedirectMixin::class);

        expect((new FieldReadRedirectTarget())->read())->toBe(9);
    });

    #[Mixin(InvalidFieldReadRedirectTarget::class)]
    final class InvalidFieldReadRedirectMixin
    {
        private function __construct() {}
        #[Redirect('read', new At('FIELD', '::value'))]
        public function redirectFieldReadWithoutValue(): void {}
    }

    final class InvalidFieldReadRedirectTarget
    {
        public int $value = 2;

        public function read(): int
        {
            return $this->value;
        }
    }


    it('rejects a field-read Redirect without a replacement value', function (): void {
        expect(function (): void {
            (new ReflectionClass(InvalidFieldReadRedirectTarget::class))->inject(InvalidFieldReadRedirectMixin::class);
        })->toThrow(InvalidArgumentException::class, 'must return a value');

        expect((new InvalidFieldReadRedirectTarget())->read())->toBe(2);
    });

    function captureFieldWriteValue(int $value): void
    {
        $GLOBALS['redirectedFieldValue'] = $value;
    }

    function capturedFieldWriteValue(): int
    {
        if (!isset($GLOBALS['redirectedFieldValue'])) {
            return -1;
        }

        $value = $GLOBALS['redirectedFieldValue'];
        return is_int($value) ? $value : -1;
    }

    #[Mixin(FieldWriteRedirectTarget::class)]
    final class FieldWriteRedirectMixin
    {
        private function __construct() {}
        #[Redirect('write', new At('FIELD', '::value'))]
        public function redirectFieldWrite(int $value): void
        {
            captureFieldWriteValue($value);
        }
    }


    final class FieldWriteRedirectTarget
    {
        public int $value = 2;

        public function write(int $value): void
        {
            $this->value = $value;
        }
    }

    it('redirects a Mixin-shaped field write and captures its assigned value', function (): void {
        (new ReflectionClass(FieldWriteRedirectTarget::class))->inject(FieldWriteRedirectMixin::class);

        $target = new FieldWriteRedirectTarget();
        $target->write(7);

        expect($target->value)->toBe(2)
            ->and(capturedFieldWriteValue())->toBe(7);
    });
});
