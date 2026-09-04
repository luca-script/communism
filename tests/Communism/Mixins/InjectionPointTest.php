<?php

declare(strict_types=1);

use Communism\Mixin\At;
use Communism\Mixin\CallbackInfo;
use Communism\Mixin\Inject;
use Communism\Mixin\Mixin;
use Communism\Reflect\ReflectionClass;

describe('At', function (): void {
    covers([At::class, Inject::class, ...COMMUNISM_INJECTOR_COVERAGE_CLASSES]);

    function incrementNewCounter(string $name): void
    {
        $value = $GLOBALS[$name] ?? 0;
        $GLOBALS[$name] = is_int($value) ? $value + 1 : 1;
    }

    function newCounter(string $name): int
    {
        $value = $GLOBALS[$name] ?? 0;

        return is_int($value) ? $value : 0;
    }

    #[Mixin(NewInjectionTarget::class)]
    final class NewInjectionMixin
    {
        private function __construct() {}
        #[Inject('make', new At('NEW', stdClass::class))]
        public function beforeObjectCreation(CallbackInfo $info): void
        {
            incrementNewCounter('new_exact');
        }
    }


    final class NewInjectionTarget
    {
        public function make(): object
        {
            return new stdClass();
        }
    }

    it('executes a callback before an exact NEW class target', function (): void {
        unset($GLOBALS['new_exact']);
        (new ReflectionClass(NewInjectionTarget::class))->inject(NewInjectionMixin::class);

        expect((new NewInjectionTarget())->make())->toBeInstanceOf(stdClass::class)
            ->and(newCounter('new_exact'))->toBe(1);
    });

    #[Mixin(WildcardNewInjectionTarget::class)]
    final class WildcardNewInjectionMixin
    {
        private function __construct() {}
        #[Inject('make', new At('NEW'))]
        public function beforeAnyObjectCreation(): void
        {
            incrementNewCounter('new_wildcard');
        }
    }


    final class WildcardNewInjectionTarget
    {
        /** @return list<object> */
        public function make(): array
        {
            return [new stdClass(), new RuntimeException('created')];
        }
    }

    it('executes a wildcard NEW callback at every construction', function (): void {
        unset($GLOBALS['new_wildcard']);
        (new ReflectionClass(WildcardNewInjectionTarget::class))->inject(WildcardNewInjectionMixin::class);

        expect((new WildcardNewInjectionTarget())->make())->toHaveCount(2)
            ->and(newCounter('new_wildcard'))->toBe(2);
    });

    #[Mixin(MissingNewInjectionTarget::class)]
    final class MissingNewInjectionMixin
    {
        private function __construct() {}
        #[Inject('make', new At('NEW', DateTimeImmutable::class))]
        public function beforeMissingConstruction(): void {}
    }


    final class MissingNewInjectionTarget
    {
        public function make(): object
        {
            return new stdClass();
        }
    }

    it('rejects a NEW target that does not occur before mutation', function (): void {
        expect(function (): void {
            (new ReflectionClass(MissingNewInjectionTarget::class))->inject(MissingNewInjectionMixin::class);
        })->toThrow(InvalidArgumentException::class, 'Injection point did not match');

        expect((new MissingNewInjectionTarget())->make())->toBeInstanceOf(stdClass::class);
    });

    #[Mixin(JumpInjectionTarget::class)]
    final class JumpInjectionMixin
    {
        private function __construct() {}
        #[Inject('branch', new At('JUMP', 'JMPZ'))]
        public function beforeConditionalJump(CallbackInfo $info): void
        {
            $info->cancel('jump callback reached');
        }
    }


    final class JumpInjectionTarget
    {
        public function branch(bool $flag): int
        {
            if ($flag) {
                return 1;
            }

            return 2;
        }
    }

    it('matches an exact conditional jump opcode', function (): void {
        unset($GLOBALS['jump_exact']);
        (new ReflectionClass(JumpInjectionTarget::class))->inject(JumpInjectionMixin::class);

        $target = new JumpInjectionTarget();
        expect($target->branch(true))->toBe(0)
            ->and($target->branch(false))->toBe(0);
    });

    #[Mixin(WildcardJumpInjectionTarget::class)]
    final class WildcardJumpInjectionMixin
    {
        private function __construct() {}
        #[Inject('countDown', new At('JUMP', 'CONDITIONAL'))]
        public function beforeConditionalJumps(CallbackInfo $info): void
        {
            $info->cancel('conditional jump callback reached');
        }
    }


    final class WildcardJumpInjectionTarget
    {
        public function countDown(int $value): int
        {
            while ($value > 0) {
                $value--;
            }

            return $value + 10;
        }
    }

    it('matches conditional jumps without matching the unconditional entry jump', function (): void {
        unset($GLOBALS['jump_conditional']);
        (new ReflectionClass(WildcardJumpInjectionTarget::class))->inject(WildcardJumpInjectionMixin::class);

        expect((new WildcardJumpInjectionTarget())->countDown(2))->toBe(0);
    });

    #[Mixin(MissingJumpInjectionTarget::class)]
    final class MissingJumpInjectionMixin
    {
        private function __construct() {}
        #[Inject('value', new At('JUMP', 'JMPZ'))]
        public function beforeMissingJump(): void {}
    }


    final class MissingJumpInjectionTarget
    {
        public function value(): int
        {
            return 1;
        }
    }

    it('rejects a jump selector that does not occur before mutation', function (): void {
        expect(function (): void {
            (new ReflectionClass(MissingJumpInjectionTarget::class))->inject(MissingJumpInjectionMixin::class);
        })->toThrow(InvalidArgumentException::class, 'Injection point did not match');

        expect((new MissingJumpInjectionTarget())->value())->toBe(1);
    });
});
