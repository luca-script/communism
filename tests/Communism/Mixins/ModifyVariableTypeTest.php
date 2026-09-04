<?php

declare(strict_types=1);

use Communism\Mixin\At;
use Communism\Mixin\Mixin;
use Communism\Mixin\ModifyVariable;
use Communism\Reflect\ReflectionClass;

describe('ModifyVariable', function (): void {
    covers([ModifyVariable::class, ...COMMUNISM_INJECTOR_COVERAGE_CLASSES]);

    #[Mixin(ModifyVariableTypedTarget::class)]
    final class ModifyVariableTypedMixin
    {
        private function __construct() {}

        #[ModifyVariable('value', new At('STORE'))]
        public function changeIntegers(int $value): int
        {
            return $value + 10;
        }
    }

    final class ModifyVariableTypedTarget
    {
        public function value(int $value): int
        {
            $first = $value;
            $text = 'not an integer';

            return $first + (int) $text;
        }
    }

    it('uses the ModifyVariable handler type to select compatible locals', function (): void {
        (new ReflectionClass(ModifyVariableTypedTarget::class))->inject(ModifyVariableTypedMixin::class);

        expect((new ModifyVariableTypedTarget())->value(2))->toBe(12);
    });

    #[Mixin(ModifyVariableTypedMismatchTarget::class)]
    final class ModifyVariableTypedMismatchMixin
    {
        private function __construct() {}

        #[ModifyVariable('value', new At('STORE'), require: 0)]
        public function changeStrings(string $value): string
        {
            return $value . '!';
        }
    }

    final class ModifyVariableTypedMismatchTarget
    {
        public function value(int $value): int
        {
            $number = $value;

            return $number;
        }
    }

    it('does not match a typed ModifyVariable handler against an incompatible local', function (): void {
        (new ReflectionClass(ModifyVariableTypedMismatchTarget::class))->inject(ModifyVariableTypedMismatchMixin::class);

        expect((new ModifyVariableTypedMismatchTarget())->value(2))->toBe(2);
    });

    #[Mixin(ModifyVariableTypedRequiredTarget::class)]
    final class ModifyVariableTypedRequiredMixin
    {
        private function __construct() {}

        #[ModifyVariable('value', new At('STORE'), require: 1)]
        public function requiresStrings(string $value): string
        {
            return $value . '!';
        }
    }

    final class ModifyVariableTypedRequiredTarget
    {
        public function value(int $value): int
        {
            $number = $value;

            return $number;
        }
    }

    it('rejects a typed ModifyVariable requirement before mutating the target', function (): void {
        expect(static function (): void {
            (new ReflectionClass(ModifyVariableTypedRequiredTarget::class))->inject(ModifyVariableTypedRequiredMixin::class);
        })->toThrow(InvalidArgumentException::class, 'requires at least 1');

        expect((new ModifyVariableTypedRequiredTarget())->value(2))->toBe(2);
    });

    #[Mixin(ModifyVariableTypedLoadTarget::class)]
    final class ModifyVariableTypedLoadMixin
    {
        private function __construct() {}

        #[ModifyVariable('value', new At('LOAD'))]
        public function changeIntegerLoad(int $value): int
        {
            return $value + 3;
        }
    }

    final class ModifyVariableTypedLoadTarget
    {
        public function value(): int
        {
            $number = 4;
            $text = 'must remain untouched';

            return $number + strlen($text);
        }
    }

    it('infers simple local-load types from preceding literal assignments', function (): void {
        (new ReflectionClass(ModifyVariableTypedLoadTarget::class))->inject(ModifyVariableTypedLoadMixin::class);

        expect((new ModifyVariableTypedLoadTarget())->value())->toBe(28);
    });

    #[Mixin(ModifyVariableTypedLoadRejectTarget::class)]
    final class ModifyVariableTypedLoadRejectMixin
    {
        private function __construct() {}

        #[ModifyVariable('value', new At('LOAD'), require: 0)]
        public function changeOnlyIntegers(int $value): int
        {
            return $value + 100;
        }
    }

    final class ModifyVariableTypedLoadRejectTarget
    {
        public function value(): string
        {
            $text = 'safe';

            return $text;
        }
    }

    it('does not match a typed local load with a known incompatible assignment', function (): void {
        (new ReflectionClass(ModifyVariableTypedLoadRejectTarget::class))->inject(ModifyVariableTypedLoadRejectMixin::class);

        expect((new ModifyVariableTypedLoadRejectTarget())->value())->toBe('safe');
    });

    #[Mixin(ModifyVariableExpressionTarget::class)]
    final class ModifyVariableExpressionMixin
    {
        private function __construct() {}

        #[ModifyVariable('value', new At('LOAD'), 'number', require: 1, expect: 1, allow: 1)]
        public function changeInferredInteger(int $value): int
        {
            return $value + 2;
        }
    }

    final class ModifyVariableExpressionTarget
    {
        public function value(int $value): int
        {
            $number = $value + 1;
            $text = $value . '!';

            return $number + 0;
        }
    }

    it('infers arithmetic local-load types without matching concatenated locals', function (): void {
        (new ReflectionClass(ModifyVariableExpressionTarget::class))->inject(ModifyVariableExpressionMixin::class);

        expect((new ModifyVariableExpressionTarget())->value(3))->toBe(6);
    });

    #[Mixin(ModifyVariableArgsOnlyTarget::class)]
    final class ModifyVariableArgsOnlyMixin
    {
        private function __construct() {}

        #[ModifyVariable('value', new At('STORE'), argsOnly: true)]
        public function changeArgument(int $value): int
        {
            return $value + 10;
        }
    }

    final class ModifyVariableArgsOnlyTarget
    {
        public function value(int $value): int
        {
            $value = $value + 1;
            $local = 4;

            return $value + $local;
        }
    }

    it('restricts ModifyVariable matching to method arguments with argsOnly', function (): void {
        (new ReflectionClass(ModifyVariableArgsOnlyTarget::class))->inject(ModifyVariableArgsOnlyMixin::class);

        expect((new ModifyVariableArgsOnlyTarget())->value(2))->toBe(17);
    });

    #[Mixin(ModifyVariableWholeBodyInferenceTarget::class)]
    final class ModifyVariableWholeBodyInferenceMixin
    {
        private function __construct() {}

        #[ModifyVariable('value', new At('LOAD'), 'bits', require: 1)]
        public function changeShiftResult(int $value): int
        {
            return $value + 10;
        }
    }

    final class ModifyVariableWholeBodyInferenceTarget
    {
        public function value(int $input): float
        {
            $quotient = $input / 2;
            $bits = $input << 1;

            return $quotient + $bits;
        }
    }

    it('infers integer locals produced by shifts while rejecting division results', function (): void {
        (new ReflectionClass(ModifyVariableWholeBodyInferenceTarget::class))->inject(ModifyVariableWholeBodyInferenceMixin::class);

        expect((new ModifyVariableWholeBodyInferenceTarget())->value(3))->toBe(17.5);
    });

    #[Mixin(ModifyVariablePathTarget::class)]
    final class ModifyVariablePathMixin
    {
        private function __construct() {}

        #[ModifyVariable('value', new At('LOAD'), 'shared', require: 1)]
        public function changeSharedInteger(int $shared): int
        {
            return $shared + 1;
        }
    }

    final class ModifyVariablePathTarget
    {
        public function value(bool $condition): int
        {
            if ($condition) {
                $shared = 4;
            } else {
                $shared = 5;
            }

            return $shared;
        }
    }

    it('infers one consistent local type across alternate control-flow paths', function (): void {
        (new ReflectionClass(ModifyVariablePathTarget::class))->inject(ModifyVariablePathMixin::class);

        expect((new ModifyVariablePathTarget())->value(true))->toBe(5)
            ->and((new ModifyVariablePathTarget())->value(false))->toBe(6);
    });

    #[Mixin(ModifyVariableConflictingPathTarget::class)]
    final class ModifyVariableConflictingPathMixin
    {
        private function __construct() {}

        #[ModifyVariable('value', new At('LOAD'), 'shared', require: 0)]
        public function changeOnlyIntegers(int $shared): int
        {
            return $shared + 100;
        }
    }

    final class ModifyVariableConflictingPathTarget
    {
        public function value(bool $condition): int|string
        {
            if ($condition) {
                $shared = 4;
            } else {
                $shared = 'safe';
            }

            return $shared;
        }
    }

    it('leaves a multiply-typed local unknown across conflicting paths', function (): void {
        (new ReflectionClass(ModifyVariableConflictingPathTarget::class))->inject(ModifyVariableConflictingPathMixin::class);

        expect((new ModifyVariableConflictingPathTarget())->value(true))->toBe(4)
            ->and((new ModifyVariableConflictingPathTarget())->value(false))->toBe('safe');
    });

    #[Mixin(ModifyVariablePrintTarget::class)]
    final class ModifyVariablePrintMixin
    {
        private function __construct() {}

        #[ModifyVariable('value', new At('STORE'), print: true)]
        public function diagnosticOnly(int $value): int
        {
            return $value + 100;
        }
    }

    final class ModifyVariablePrintTarget
    {
        public function value(int $value): int
        {
            $local = $value;

            return $local;
        }
    }

    it('prints ModifyVariable locals without injecting the handler', function (): void {
        (new ReflectionClass(ModifyVariablePrintTarget::class))->inject(ModifyVariablePrintMixin::class);

        expect((new ModifyVariablePrintTarget())->value(2))->toBe(2);
    });
});
