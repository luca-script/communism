<?php

declare(strict_types=1);

use Communism\Mixin\At;
use Communism\Mixin\Inject;
use Communism\Mixin\Mixin;
use Communism\Internals\Needle\MatchResult;
use Communism\Internals\Needle\Matcher;
use Communism\Internals\Needle\MethodBody;
use Communism\Mixin\Redirect;
use Communism\Reflect\ReflectionClass;

describe('Matcher', function (): void {
    covers([Matcher::class, ...COMMUNISM_INJECTOR_COVERAGE_CLASSES]);

    #[Mixin(FieldOpcodeReadTarget::class)]
    final class FieldOpcodeReadMixin
    {
        private function __construct() {}

        #[Redirect('read', new At('FIELD', '::value', opcode: 'READ'))]
        public function replaceRead(): int
        {
            return 41;
        }
    }

    final class FieldOpcodeReadTarget
    {
        public int $value = 7;

        public function read(): int
        {
            return $this->value;
        }
    }

    it('filters FIELD points by read opcode and applies the redirect', function (): void {
        (new ReflectionClass(FieldOpcodeReadTarget::class))->inject(FieldOpcodeReadMixin::class);

        expect((new FieldOpcodeReadTarget())->read())->toBe(41);
    });

    #[Mixin(FieldOpcodeWriteTarget::class)]
    final class FieldOpcodeWriteMismatchMixin
    {
        private function __construct() {}

        #[Redirect('write', new At('FIELD', '::value', opcode: 'READ'))]
        public function replaceReadOnly(): int
        {
            return 99;
        }
    }

    final class FieldOpcodeWriteTarget
    {
        public int $value = 7;

        public function write(int $value): void
        {
            $this->value = $value;
        }
    }

    it('rejects a FIELD opcode filter that cannot resolve without mutating the target', function (): void {
        expect(static function (): void {
            (new ReflectionClass(FieldOpcodeWriteTarget::class))->inject(FieldOpcodeWriteMismatchMixin::class);
        })->toThrow(InvalidArgumentException::class, 'did not match');

        $target = new FieldOpcodeWriteTarget();
        $target->write(12);

        expect($target->value)->toBe(12);
    });

    #[Mixin(CustomInjectionPointTarget::class)]
    final class CustomInjectionPointMixin
    {
        private function __construct() {}

        #[Inject('value', new At('_CUSTOM_HEAD', action: 'before'))]
        public function mark(): void
        {
            $GLOBALS['custom_injection_point_marker'] = 1;
        }
    }

    final class CustomInjectionPointTarget
    {
        public function value(int $value): int
        {
            return $value + 1;
        }
    }

    it('executes a registered custom injection point', function (): void {
        Matcher::registerInjectionPoint('_CUSTOM_HEAD', static function (MethodBody $body, At $at): array {
            $start = 0;
            while ($start < $body->count() && $body->instruction($start)->name === 'RECV') {
                $start++;
            }

            return [new MatchResult($start, $start, 'begin', 'before')];
        });

        try {
            (new ReflectionClass(CustomInjectionPointTarget::class))->inject(CustomInjectionPointMixin::class);
        } finally {
            Matcher::unregisterInjectionPoint('_CUSTOM_HEAD');
        }

        $GLOBALS['custom_injection_point_marker'] = 0;
        $result = (new CustomInjectionPointTarget())->value(3);
        expect($result)->toBe(4)
            ->and($GLOBALS['custom_injection_point_marker'])->toBe(1);
    });

    it('rejects malformed custom injection-point results and built-in replacement', function (): void {
        expect(static function (): void {
            Matcher::registerInjectionPoint('CUSTOM_POINT', static fn(MethodBody $body, At $at): array => []);
        })->toThrow(InvalidArgumentException::class, 'must start with _');

        expect(static function (): void {
            Matcher::registerInjectionPoint('RETURN', static fn(MethodBody $body, At $at): array => []);
        })->toThrow(InvalidArgumentException::class, 'must start with _');

        Matcher::registerInjectionPoint('_BROKEN_POINT', static fn(MethodBody $body, At $at): array => [
            new MatchResult(-1, 0, 'custom'),
        ]);

        try {
            expect(static fn(): array => Matcher::find(
                \Communism\Internals\Needle\Decompiler::decompile(CustomInjectionPointTarget::class . '::value'),
                new At('_BROKEN_POINT'),
            ))->toThrow(InvalidArgumentException::class, 'invalid match');
        } finally {
            Matcher::unregisterInjectionPoint('_BROKEN_POINT');
        }
    });
});
