<?php

declare(strict_types=1);

use Communism\Mixin\At;
use Communism\Mixin\CallbackInfo;
use Communism\Mixin\Group;
use Communism\Mixin\Inject;
use Communism\Mixin\Mixin;
use Communism\Mixin\Redirect;
use Communism\Reflect\ReflectionClass;
use Communism\Mixin\Slice;

describe('Inject', function (): void {
    covers([Inject::class, ...COMMUNISM_INJECTOR_COVERAGE_CLASSES]);

    final class MultiTargetInjectionTarget
    {
        public function first(): string
        {
            return 'first';
        }

        public function second(): string
        {
            return 'second';
        }
    }

    function markMultiTargetInjection(string $method): void
    {
        $GLOBALS['multi_target_' . $method] = true;
    }

    function multiTargetInjectionMarked(string $method): bool
    {
        return isset($GLOBALS['multi_target_' . $method]);
    }

    #[Mixin(MultiTargetInjectionTarget::class)]
    final class MultiTargetInjectionMixin
    {
        private function __construct() {}

        #[Inject(['first', 'second'], new At('HEAD'))]
        public function record(CallbackInfo $info): void
        {
            markMultiTargetInjection($info->getMethodName());
        }
    }

    it('applies one Inject callback to every selected target method', function (): void {
        unset($GLOBALS['multi_target_first'], $GLOBALS['multi_target_second']);
        (new ReflectionClass(MultiTargetInjectionTarget::class))->inject(MultiTargetInjectionMixin::class);

        $target = new MultiTargetInjectionTarget();
        expect($target->first())->toBe('first')
            ->and($target->second())->toBe('second')
            ->and(multiTargetInjectionMarked('first'))->toBeTrue()
            ->and(multiTargetInjectionMarked('second'))->toBeTrue();
    });

    #[Mixin(ExecutableInjectionTarget::class)]
    final class ExecutableInjectionTrait
    {
        private function __construct() {}
        #[Inject('value', new At('RETURN', '', -1, 'BEFORE', 0, 'replace'))]
        public function replaceValue(): int
        {
            return 7;
        }

        #[Inject('beforeValue', new At('RETURN', '', -1, 'BEFORE', 0, 'before'))]
        public function beforeReturn(): void
        {
            $GLOBALS['executed_return_before'] = true;
        }

        #[Inject('modifyValue', new At('RETURN', '', -1, 'BEFORE', 0, 'modify'))]
        public function modifyReturn(): int
        {
            return 8;
        }

        #[Inject('beginValue', new At('HEAD'))]
        public function beginMethod(): void
        {
            $GLOBALS['executed_begin'] = true;
        }
    }


    final class ExecutableInjectionTarget
    {
        public function value(): int
        {
            return 1;
        }

        public function beforeValue(): int
        {
            return 2;
        }

        public function modifyValue(): int
        {
            return 3;
        }

        public function beginValue(): int
        {
            return 4;
        }
    }

    function executableInjectionCall(): int
    {
        return 3;
    }

    function executableInjectionGlobalWasSet(string $name): bool
    {
        return isset($GLOBALS[$name]);
    }

    it('executes return, before, modify, and begin injections', function (): void {
        unset($GLOBALS['executed_return_before'], $GLOBALS['executed_begin']);
        (new ReflectionClass(ExecutableInjectionTarget::class))->inject(ExecutableInjectionTrait::class);

        $target = new ExecutableInjectionTarget();
        expect($target->value())->toBe(7);
        expect($target->beforeValue())->toBe(2);
        expect(executableInjectionGlobalWasSet('executed_return_before'))->toBeTrue();
        expect($target->modifyValue())->toBe(8);
        expect($target->beginValue())->toBe(4);
        expect(executableInjectionGlobalWasSet('executed_begin'))->toBeTrue();
    });
    #[Mixin(MultiSpotExecutableTarget::class)]
    final class MultiSpotExecutableTrait
    {
        private function __construct() {}
        #[Inject('run', new At('INVOKE', 'executableInjectionCall'))]
        public function markBefore(): void
        {
            $GLOBALS['multi_spot_before'] = true;
        }

        #[Inject('run', new At('INVOKE', 'executableInjectionCall', -1, 'AFTER'))]
        public function markAfter(): void
        {
            $GLOBALS['multi_spot_after'] = true;
        }
    }


    final class MultiSpotExecutableTarget
    {
        public function run(): int
        {
            return executableInjectionCall();
        }
    }

    it('executes multiple before and after injections around an invocation', function (): void {
        unset($GLOBALS['multi_spot_before'], $GLOBALS['multi_spot_after']);
        (new ReflectionClass(MultiSpotExecutableTarget::class))->inject(MultiSpotExecutableTrait::class);

        $target = new MultiSpotExecutableTarget();

        expect($target->run())->toBe(3);
        expect(executableInjectionGlobalWasSet('multi_spot_before'))->toBeTrue();
        expect(executableInjectionGlobalWasSet('multi_spot_after'))->toBeTrue();
    });

    #[Mixin(WildcardInvocationExecutableTarget::class)]
    final class WildcardInvocationExecutableTrait
    {
        private function __construct() {}
        #[Inject('run', new At('INVOKE', '*'))]
        public function markGlobalInvocation(): void
        {
            $GLOBALS['wildcard_invocation'] = true;
        }
    }


    final class WildcardInvocationExecutableTarget
    {
        public function run(): int
        {
            return executableInjectionCall();
        }
    }

    it('executes a callback selected by a wildcard invocation target', function (): void {
        unset($GLOBALS['wildcard_invocation']);
        (new ReflectionClass(WildcardInvocationExecutableTarget::class))->inject(WildcardInvocationExecutableTrait::class);

        expect((new WildcardInvocationExecutableTarget())->run())->toBe(3);
        expect(executableInjectionGlobalWasSet('wildcard_invocation'))->toBeTrue();
    });

    #[Mixin(SlicedExecutableTarget::class)]
    final class SlicedExecutableTrait
    {
        private function __construct() {}
        #[Inject(
            'run',
            new At('INVOKE', 'executableInjectionCall'),
            slice: new Slice(
                new At('INVOKE', 'executableInjectionCall', 0),
                new At('INVOKE', 'executableInjectionCall', 1),
            ),
        )]
        public function markFirstCall(): void
        {
            $GLOBALS['sliced_call'] = true;
        }
    }


    final class SlicedExecutableTarget
    {
        public function run(): int
        {
            return executableInjectionCall() + executableInjectionCall();
        }
    }

    it('executes an injection only inside its Mixin-style slice', function (): void {
        unset($GLOBALS['sliced_call']);
        (new ReflectionClass(SlicedExecutableTarget::class))->inject(SlicedExecutableTrait::class);

        expect((new SlicedExecutableTarget())->run())->toBe(6);
        expect(executableInjectionGlobalWasSet('sliced_call'))->toBeTrue();
    });

    #[Mixin(GroupedExecutableTarget::class)]
    final class GroupedExecutableTrait
    {
        private function __construct() {}
        #[Group('calls', 2, 2)]
        #[Inject('run', new At('INVOKE', 'executableInjectionCall', 0))]
        public function markFirstGroupedCall(): void
        {
            $GLOBALS['grouped_first'] = true;
        }

        #[Group('calls', 2, 2)]
        #[Inject('run', new At('INVOKE', 'executableInjectionCall', 1))]
        public function markSecondGroupedCall(): void
        {
            $GLOBALS['grouped_second'] = true;
        }
    }


    final class GroupedExecutableTarget
    {
        public function run(): int
        {
            return executableInjectionCall() + executableInjectionCall();
        }
    }

    it('executes a grouped set of injection points after aggregate validation', function (): void {
        unset($GLOBALS['grouped_first'], $GLOBALS['grouped_second']);
        (new ReflectionClass(GroupedExecutableTarget::class))->inject(GroupedExecutableTrait::class);

        expect((new GroupedExecutableTarget())->run())->toBe(6);
        expect(executableInjectionGlobalWasSet('grouped_first'))->toBeTrue();
        expect(executableInjectionGlobalWasSet('grouped_second'))->toBeTrue();
    });

    #[Mixin(ByShiftExecutableTarget::class)]
    final class ByShiftExecutableTrait
    {
        private function __construct() {}
        #[Inject('run', new At('INVOKE', 'executableInjectionCall', -1, 'BY', 1))]
        public function markShiftedPoint(): void
        {
            $GLOBALS['shifted_call'] = true;
        }
    }


    final class ByShiftExecutableTarget
    {
        public function run(): int
        {
            return executableInjectionCall();
        }
    }

    it('executes a callback at a safely shifted injection point', function (): void {
        unset($GLOBALS['shifted_call']);
        (new ReflectionClass(ByShiftExecutableTarget::class))->inject(ByShiftExecutableTrait::class);

        expect((new ByShiftExecutableTarget())->run())->toBe(3);
        expect(executableInjectionGlobalWasSet('shifted_call'))->toBeTrue();
    });

    #[Mixin(AllMatcherExecutableTarget::class)]
    final class AllMatcherExecutableTrait
    {
        private function __construct() {}
        #[Inject('assignMember', new At('FIELD', '::member'))]
        public function handleAssignment(): void
        {
            $GLOBALS['executed_assign'] = true;
        }

        #[Redirect('invokeReplace', new At('INVOKE', 'executableInjectionCall'))]
        public function replaceInvocation(): int
        {
            return 9;
        }

        #[Inject('thrower', new At('THROW', RuntimeException::class))]
        public function handleThrow(): void
        {
            $GLOBALS['executed_throw'] = true;
        }
    }


    final class AllMatcherExecutableTarget
    {
        public int $member = 0;

        public function assignMember(): void
        {
            $this->member = 1;
        }

        public function invokeReplace(): int
        {
            return executableInjectionCall();
        }

        public function thrower(): void
        {
            throw new RuntimeException('injected');
        }
    }

    it('executes field, invocation, and throw injections', function (): void {
        unset($GLOBALS['executed_assign'], $GLOBALS['executed_throw']);
        (new ReflectionClass(AllMatcherExecutableTarget::class))->inject(AllMatcherExecutableTrait::class);

        $target = new AllMatcherExecutableTarget();
        $target->assignMember();

        expect($target->member)->toBe(0);
        expect(executableInjectionGlobalWasSet('executed_assign'))->toBeTrue();
        expect($target->invokeReplace())->toBe(9);
        $threw = false;
        try {
            $target->thrower();
        } catch (Throwable) {
            $threw = true;
        }

        expect($threw)->toBeFalse();
        expect(executableInjectionGlobalWasSet('executed_throw'))->toBeTrue();
    });
});
