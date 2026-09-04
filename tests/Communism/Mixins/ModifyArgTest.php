<?php

declare(strict_types=1);

use Communism\Mixin\At;
use Communism\Mixin\Mixin;
use Communism\Mixin\ModifyArg;
use Communism\Mixin\Slice;
use Communism\Reflect\ReflectionClass;

describe('ModifyArg', function (): void {
    covers([ModifyArg::class, ...COMMUNISM_INJECTOR_COVERAGE_CLASSES]);

    function modifyArgTarget(int $value): int
    {
        return $value * 2;
    }

    #[Mixin(ModifyArgTarget::class)]
    final class ModifyArgMixin
    {
        private function __construct() {}
        #[ModifyArg('value', new At('INVOKE', 'modifyArgTarget'), 0)]
        public function changeArgument(int $value): int
        {
            return $value + 3;
        }
    }


    final class ModifyArgTarget
    {
        public function value(int $value): int
        {
            return modifyArgTarget($value);
        }
    }

    it('executes a Mixin-shaped ModifyArg handler at an invocation', function (): void {
        (new ReflectionClass(ModifyArgTarget::class))->inject(ModifyArgMixin::class);

        expect((new ModifyArgTarget())->value(4))->toBe(14);
    });

    function modifyArgPair(int $first, int $second): int
    {
        return $first * 10 + $second;
    }

    #[Mixin(ModifyArgPairTarget::class)]
    final class ModifyArgPairMixin
    {
        private function __construct() {}
        #[ModifyArg('value', new At('INVOKE', 'modifyArgPair'), 1)]
        public function changePairSecond(int $value): int
        {
            return $value + 10;
        }
    }


    final class ModifyArgPairTarget
    {
        public function value(int $first, int $second): int
        {
            return modifyArgPair($first, $second);
        }
    }

    it('changes only the selected ModifyArg ordinal', function (): void {
        (new ReflectionClass(ModifyArgPairTarget::class))->inject(ModifyArgPairMixin::class);

        expect((new ModifyArgPairTarget())->value(2, 3))->toBe(33);
    });

    #[Mixin(InvalidModifyArgTarget::class)]
    final class InvalidModifyArgMixin
    {
        private function __construct() {}
        #[ModifyArg('value', new At('INVOKE', 'modifyArgTarget'), 1)]
        public function missingArgument(int $value): int
        {
            return $value;
        }
    }

    final class InvalidModifyArgTarget
    {
        public function value(int $value): int
        {
            return modifyArgTarget($value);
        }
    }


    it('rejects ModifyArg indexes that the invocation does not provide', function (): void {
        expect(function (): void {
            (new ReflectionClass(InvalidModifyArgTarget::class))->inject(InvalidModifyArgMixin::class);
        })->toThrow(InvalidArgumentException::class);

        expect((new InvalidModifyArgTarget())->value(4))->toBe(8);
    });

    function modifyArgSliceValue(int $value): int
    {
        return $value * 2;
    }

    #[Mixin(ModifyArgSliceTarget::class)]
    final class ModifyArgSliceMixin
    {
        private function __construct() {}

        #[ModifyArg(
            'value',
            new At('INVOKE', 'modifyArgSliceValue'),
            slice: new Slice(
                new At('INVOKE', 'modifyArgSliceValue', 0),
                new At('INVOKE', 'modifyArgSliceValue', 1),
            ),
        )]
        public function changeOnlyFirst(int $value): int
        {
            return $value + 1;
        }
    }

    final class ModifyArgSliceTarget
    {
        public function value(int $value): int
        {
            return modifyArgSliceValue($value) + modifyArgSliceValue($value);
        }
    }

    it('applies ModifyArg only inside its declared slice', function (): void {
        (new ReflectionClass(ModifyArgSliceTarget::class))->inject(ModifyArgSliceMixin::class);

        expect((new ModifyArgSliceTarget())->value(4))->toBe(18);
    });

    function multiModifyArgCall(int $value): int
    {
        return $value * 2;
    }

    #[Mixin(MultiModifyArgTarget::class)]
    final class MultiModifyArgMixin
    {
        private function __construct() {}

        #[ModifyArg(['first', 'second'], new At('INVOKE', 'multiModifyArgCall'))]
        public function changeBoth(int $value): int
        {
            return $value + 1;
        }
    }

    final class MultiModifyArgTarget
    {
        public function first(int $value): int
        {
            return multiModifyArgCall($value);
        }

        public function second(int $value): int
        {
            return multiModifyArgCall($value);
        }
    }

    it('applies ModifyArg to multiple target methods', function (): void {
        (new ReflectionClass(MultiModifyArgTarget::class))->inject(MultiModifyArgMixin::class);

        $target = new MultiModifyArgTarget();
        expect($target->first(4))->toBe(10)
            ->and($target->second(4))->toBe(10);
    });
});
