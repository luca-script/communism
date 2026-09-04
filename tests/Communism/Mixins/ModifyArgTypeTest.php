<?php

declare(strict_types=1);

use Communism\Mixin\At;
use Communism\Mixin\Coerce;
use Communism\Mixin\Mixin;
use Communism\Mixin\ModifyArg;
use Communism\Reflect\ReflectionClass;

describe('ModifyArg', function (): void {
    covers([ModifyArg::class, ...COMMUNISM_INJECTOR_COVERAGE_CLASSES]);

    function modifyArgTypedFloat(float $value): float
    {
        return $value * 2;
    }

    #[Mixin(ModifyArgCoerceTarget::class)]
    final class ModifyArgCoerceMixin
    {
        private function __construct() {}
        #[Coerce]
        #[ModifyArg('value', new At('INVOKE', 'modifyArgTypedFloat'), 0)]
        public function coerceArgument(int $value): int
        {
            return $value + 1;
        }
    }


    final class ModifyArgCoerceTarget
    {
        public function value(float $value): float
        {
            return modifyArgTypedFloat($value);
        }
    }

    it('allows compatible numeric ModifyArg coercion with #[Coerce]', function (): void {
        (new ReflectionClass(ModifyArgCoerceTarget::class))->inject(ModifyArgCoerceMixin::class);

        expect((new ModifyArgCoerceTarget())->value(2.5))->toBe(7.0);
    });

    function modifyArgStrictInt(int $value): int
    {
        return $value * 2;
    }

    #[Mixin(ModifyArgNumericCastTarget::class)]
    final class ModifyArgNumericCastMixin
    {
        private function __construct() {}

        #[Coerce]
        #[ModifyArg('value', new At('INVOKE', 'modifyArgStrictInt'), 0)]
        public function coerceFloat(float $value): float
        {
            return $value + 0.5;
        }
    }

    final class ModifyArgNumericCastTarget
    {
        public function value(int $value): int
        {
            return modifyArgStrictInt($value);
        }
    }

    it('emits an explicit cast for a coerced numeric ModifyArg result', function (): void {
        (new ReflectionClass(ModifyArgNumericCastTarget::class))->inject(ModifyArgNumericCastMixin::class);

        expect((new ModifyArgNumericCastTarget())->value(2))->toBe(4);
    });

    /** @param array<int, mixed> $values */
    function modifyArgStrictArray(array $values): int
    {
        return count($values);
    }

    #[Mixin(ModifyArgArrayCastTarget::class)]
    final class ModifyArgArrayCastMixin
    {
        private function __construct() {}

        /** @param iterable<int, mixed> $values
         * @return iterable<int, mixed>
         */
        #[Coerce]
        #[ModifyArg('value', new At('INVOKE', 'modifyArgStrictArray'), 0)]
        public function coerceIterable(iterable $values): iterable
        {
            return $values;
        }
    }

    final class ModifyArgArrayCastTarget
    {
        /** @param array<int, mixed> $values */
        public function value(array $values): int
        {
            return modifyArgStrictArray($values);
        }
    }

    it('emits an explicit array cast for a coerced iterable ModifyArg result', function (): void {
        (new ReflectionClass(ModifyArgArrayCastTarget::class))->inject(ModifyArgArrayCastMixin::class);

        expect((new ModifyArgArrayCastTarget())->value([1, 2, 3]))->toBe(3);
    });

    function modifyArgUnionResult(int|float $value): int|float
    {
        return $value;
    }

    #[Mixin(ModifyArgUnionTarget::class)]
    final class ModifyArgUnionMixin
    {
        private function __construct() {}

        #[ModifyArg('value', new At('INVOKE', 'modifyArgUnionResult'), 0)]
        public function preserveUnion(int|float $value): int|float
        {
            return $value + 1;
        }
    }

    final class ModifyArgUnionTarget
    {
        public function value(int|float $value): int|float
        {
            return modifyArgUnionResult($value);
        }
    }

    it('validates union ModifyArg results', function (): void {
        (new ReflectionClass(ModifyArgUnionTarget::class))->inject(ModifyArgUnionMixin::class);

        expect((new ModifyArgUnionTarget())->value(2))->toBe(3);
    });

    #[Mixin(ModifyArgUnionRejectTarget::class)]
    final class ModifyArgUnionRejectMixin
    {
        private function __construct() {}

        #[ModifyArg('value', new At('INVOKE', 'modifyArgUnionResult'), 0)]
        public function rejectUnion(int|float $value): string|bool
        {
            return $value > 0 ? true : 'invalid';
        }
    }

    final class ModifyArgUnionRejectTarget
    {
        public function value(int|float $value): int|float
        {
            return modifyArgUnionResult($value);
        }
    }

    it('rejects incompatible union ModifyArg results before mutation', function (): void {
        expect(static fn() => (new ReflectionClass(ModifyArgUnionRejectTarget::class))->inject(ModifyArgUnionRejectMixin::class))
            ->toThrow(InvalidArgumentException::class, 'returns string|bool');
    });

    function modifyArgUnionCastSource(float $value): float
    {
        return $value;
    }

    #[Mixin(ModifyArgUnionCastTarget::class)]
    final class ModifyArgUnionCastMixin
    {
        private function __construct() {}

        #[Coerce]
        #[ModifyArg('value', new At('INVOKE', 'modifyArgUnionCastSource'), 0)]
        public function coerceUnion(float $value): int|float
        {
            return $value > 0 ? 1 : $value;
        }
    }

    final class ModifyArgUnionCastTarget
    {
        public function value(float $value): float
        {
            return modifyArgUnionCastSource($value);
        }
    }

    it('emits a cast for a coerced union ModifyArg result', function (): void {
        (new ReflectionClass(ModifyArgUnionCastTarget::class))->inject(ModifyArgUnionCastMixin::class);

        expect((new ModifyArgUnionCastTarget())->value(2.5))->toBe(1.0);
    });

    function modifyArgTypedInt(int $value): int
    {
        return $value * 2;
    }

    #[Mixin(ModifyArgParameterMismatchTarget::class)]
    final class ModifyArgParameterMismatchMixin
    {
        private function __construct() {}
        #[ModifyArg('value', new At('INVOKE', 'modifyArgTypedInt'), 0)]
        public function wrongParameter(string $value): int
        {
            return 1;
        }
    }


    final class ModifyArgParameterMismatchTarget
    {
        public function value(int $value): int
        {
            return modifyArgTypedInt($value);
        }
    }

    it('rejects an incompatible ModifyArg parameter before mutation', function (): void {
        expect(function (): void {
            (new ReflectionClass(ModifyArgParameterMismatchTarget::class))->inject(ModifyArgParameterMismatchMixin::class);
        })->toThrow(InvalidArgumentException::class, 'expects string');

        expect((new ModifyArgParameterMismatchTarget())->value(4))->toBe(8);
    });

    #[Mixin(ModifyArgReturnMismatchTarget::class)]
    final class ModifyArgReturnMismatchMixin
    {
        private function __construct() {}
        #[ModifyArg('value', new At('INVOKE', 'modifyArgTypedInt'), 0)]
        public function wrongReturn(int $value): string
        {
            return (string) $value;
        }
    }


    final class ModifyArgReturnMismatchTarget
    {
        public function value(int $value): int
        {
            return modifyArgTypedInt($value);
        }
    }

    it('rejects an incompatible ModifyArg return before mutation', function (): void {
        expect(function (): void {
            (new ReflectionClass(ModifyArgReturnMismatchTarget::class))->inject(ModifyArgReturnMismatchMixin::class);
        })->toThrow(InvalidArgumentException::class, 'returns string');

        expect((new ModifyArgReturnMismatchTarget())->value(4))->toBe(8);
    });
});
