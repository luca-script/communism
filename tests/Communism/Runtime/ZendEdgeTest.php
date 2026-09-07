<?php

declare(strict_types=1);

use Communism\Internals\Zend;
use Communism\Mixin\Accessor;
use Communism\Mixin\At;
use Communism\Mixin\Group;
use Communism\Mixin\Inject;
use Communism\Mixin\Invoker;
use Communism\Mixin\DebugOptions;
use Communism\Mixin\MixinConfiguration;
use Communism\Mixin\Coerce;
use Communism\Mixin\Shadow;
use Communism\Mixin\Intrinsic;
use Communism\Mixin\Mixin;
use Communism\Mixin\SoftOverride;
use Communism\Mixin\Unique;
use Communism\Mixin\Interface_;
use Communism\Mixin\Implements_;
use Communism\Mixin\Overwrite;
use Communism\Mixin\ModifyVariable;
use Communism\Mixin\Surrogate;
use Communism\Mixin\Final_;

describe('Zend', function (): void {
    /** @return class-string */
    function zendDuplicateFixture(string $name): string
    {
        if (!class_exists($name)) {
            throw new LogicException('Fixture was not evaluated.');
        }
        return $name;
    }

    eval(<<<'PHP'
        #[\Communism\Mixin\Mixin(ZendIntrinsicStaticTarget::class)]
        final class ZendDuplicateIntrinsicMixin
        {
            private function __construct() {}

            #[\Communism\Mixin\Intrinsic]
            #[\Communism\Mixin\Intrinsic]
            public function duplicate(): string
            {
                return 'duplicate';
            }
        }

        #[\Communism\Mixin\Mixin(ZendSoftMissingTarget::class)]
        final class ZendDuplicateSoftOverrideMixin
        {
            private function __construct() {}

            #[\Communism\Mixin\SoftOverride]
            #[\Communism\Mixin\SoftOverride]
            public function duplicate(): string
            {
                return 'duplicate';
            }
        }

        #[\Communism\Mixin\Mixin(ZendDuplicateSurrogateTarget::class)]
        final class ZendDuplicateSurrogateMixin
        {
            private function __construct() {}

            #[\Communism\Mixin\Surrogate]
            #[\Communism\Mixin\Surrogate]
            public function handlerSurrogate(): void {}
        }

        #[\Communism\Mixin\Mixin(ZendEdgeTarget::class)]
        final class ZendDuplicateAccessorMixin
        {
            private function __construct() {}

            #[\Communism\Mixin\Accessor]
            #[\Communism\Mixin\Accessor]
            public function duplicate(): string
            {
                return 'duplicate';
            }
        }

        #[\Communism\Mixin\Mixin(ZendEdgeTarget::class)]
        final class ZendDuplicateFinalMixin
        {
            private function __construct() {}

            #[\Communism\Mixin\Final_]
            #[\Communism\Mixin\Final_]
            public function duplicate(): string
            {
                return 'duplicate';
            }
        }

        #[\Communism\Mixin\Mixin(ZendEdgeTarget::class)]
        final class ZendDuplicateOverwriteMixin
        {
            private function __construct() {}

            #[\Communism\Mixin\Overwrite]
            #[\Communism\Mixin\Overwrite]
            public function duplicate(): string
            {
                return 'duplicate';
            }
        }

        #[\Communism\Mixin\Mixin(ZendEdgeTarget::class)]
        final class ZendDuplicateShadowMixin
        {
            private function __construct() {}

            #[\Communism\Mixin\Shadow]
            #[\Communism\Mixin\Shadow]
            public function duplicate(): string
            {
                return 'duplicate';
            }
        }

        final class ZendDuplicateGroupTarget
        {
            #[\Communism\Mixin\Group('one')]
            #[\Communism\Mixin\Group('two')]
            public function grouped(): void {}
        }

        #[\Communism\Mixin\Mixin(ZendDuplicatePropertyTarget::class)]
        final class ZendDuplicatePropertyMixin
        {
            private function __construct() {}

            #[\Communism\Mixin\Shadow]
            #[\Communism\Mixin\Final_]
            #[\Communism\Mixin\Final_]
            public string $value = 'value';
        }
    PHP);
    covers([Group::class, Zend::class, ...COMMUNISM_INJECTOR_COVERAGE_CLASSES]);

    class ZendEdgeTarget
    {
        public string $value;
        public static string $staticValue;
        public readonly string $readonlyValue;

        public function __construct()
        {
            $this->readonlyValue = 'readonly';
        }

        public function getValue(): string
        {
            return $this->value;
        }
        public function get(): string
        {
            return $this->value;
        }
        public function setValue(string $value): void
        {
            $this->value = $value;
        }
        public static function getStaticValue(): string
        {
            return self::$staticValue;
        }
        public function setReadonlyValue(string $value): void {}
        public function run(): string
        {
            return 'run';
        }
        public function callRun(): string
        {
            return $this->run();
        }
        public function unrelated(): string
        {
            return 'unrelated';
        }

        #[Group('edge')]
        public function grouped(): void {}
    }

    class ZendInvalidPublicMixin
    {
        public function __construct() {}
    }

    final class ZendInvalidParameterMixin
    {
        private function __construct(string $value) { if ($value === 'never') { throw new LogicException(); } }
    }

    final class ZendEdgeTypedHandlers
    {
        public function nullable(?string $value): ?string
        {
            return $value;
        }

        #[Coerce]
        public function coercedString(string $value): string
        {
            return $value;
        }

        #[Coerce]
        public function coercedBool(bool $value): bool
        {
            return $value;
        }

        /**
         * @param array<int, mixed> $value
         * @return array<int, mixed>
         */
        #[Coerce]
        public function coercedArray(array $value): array
        {
            return $value;
        }
    }

    final class ZendIntrinsicStaticTarget {}

    #[Mixin(ZendIntrinsicStaticTarget::class)]
    final class ZendIntrinsicStaticMixin
    {
        private function __construct() {}

        #[Intrinsic]
        public static function intrinsic(): string
        {
            return 'intrinsic';
        }
    }


    final class ZendCombinedAnnotationTarget {}

    #[Mixin(ZendCombinedAnnotationTarget::class)]
    final class ZendCombinedAnnotationMixin
    {
        private function __construct() {}

        #[Intrinsic]
        #[Overwrite]
        public function combined(): string
        {
            return 'combined';
        }
    }

    final class ZendSoftMissingTarget {}

    #[Mixin(ZendSoftMissingTarget::class)]
    final class ZendSoftMissingMixin
    {
        private function __construct() {}

        #[SoftOverride]
        public function missing(): string
        {
            return 'missing';
        }
    }


    class ZendSoftInheritedBase
    {
        public function inherited(): string
        {
            return 'base';
        }
    }

    final class ZendSoftInheritedTarget extends ZendSoftInheritedBase {}

    #[Mixin(ZendSoftInheritedTarget::class)]
    final class ZendSoftPrivateMixin
    {
        private function __construct() {}

        #[SoftOverride]
        /** @phpstan-used */
        private function inherited(): string
        {
            return 'private';
        }

        public static function inheritedForAnalysis(): string
        {
            return (new self())->inherited();
        }
    }

    final class ZendSoftCombinedTarget {}

    #[Mixin(ZendSoftCombinedTarget::class)]
    final class ZendSoftCombinedMixin
    {
        private function __construct() {}

        #[SoftOverride]
        #[Unique]
        public function combined(): string
        {
            return 'combined';
        }
    }

    final class ZendPrintTarget {}

    #[Mixin(ZendPrintTarget::class)]
    final class ZendPrintMissingMixin
    {
        private function __construct() {}

        #[ModifyVariable('missing', new At('STORE'), print: true)]
        public function printMissing(): void {}
    }

    final class ZendInvokerMismatchTarget
    {
        public function run(): string
        {
            return 'run';
        }
    }

    #[Mixin(ZendInvokerMismatchTarget::class)]
    final class ZendInvokerMismatchMixin
    {
        private function __construct() {}

        #[Invoker('run')]
        public static function invoke(): string
        {
            return 'invoke';
        }
    }

    interface ZendDuplicateInterfaceA
    {
        public function value(): string;
    }

    interface ZendDuplicateInterfaceB
    {
        public function value(): string;
    }

    #[Mixin(ZendEdgeTarget::class)]
    #[Implements_(new Interface_(ZendDuplicateInterfaceA::class, 'prefix'), new Interface_(ZendDuplicateInterfaceB::class, 'prefix'))]
    final class ZendDuplicateInterfaceMixin
    {
        private function __construct() {}

        public function prefixvalue(): string
        {
            return 'value';
        }
    }

    final class ZendParameterTarget
    {
        public function convert(int $value): string
        {
            return (string) $value;
        }
    }

    final class ZendParameterSource
    {
        public function convert(string $value): string
        {
            return $value;
        }
    }

    final class ZendDuplicateSurrogateTarget {}







    final class ZendDuplicatePropertyTarget
    {
        public string $value = 'value';
    }


    it('validates generated accessor and invoker targets', function (): void {
        $invoke = static function (string $name, mixed ...$arguments): mixed {
            $method = new ReflectionMethod(Zend::class, $name);

            return $method->invoke(null, ...$arguments);
        };
        $class = ZendEdgeTarget::class;

        expect($invoke('accessorProperty', $class, new ReflectionMethod($class, 'getValue'), null))
            ->toBe('value')
            ->and($invoke('accessorProperty', $class, new ReflectionMethod($class, 'getStaticValue'), null))
            ->toBe('staticValue')
            ->and($invoke('accessorIsSetter', new ReflectionMethod($class, 'setValue')))->toBeTrue()
            ->and($invoke('accessorIsSetter', new ReflectionMethod($class, 'getValue')))->toBeFalse()
            ->and($invoke('invokerMethod', new ReflectionClass($class), new ReflectionMethod($class, 'callRun'), null))
            ->toBe('run');

        expect(static fn(): mixed => $invoke('accessorProperty', $class, new ReflectionMethod($class, 'run'), null))
            ->toThrow(InvalidArgumentException::class)
            ->and(static fn(): mixed => $invoke('accessorProperty', $class, new ReflectionMethod($class, 'getValue'), 'missing'))
            ->toThrow(InvalidArgumentException::class)
            ->and(static fn(): mixed => $invoke('accessorProperty', $class, new ReflectionMethod($class, 'getValue'), 'staticValue'))
            ->toThrow(InvalidArgumentException::class)
            ->and(static fn(): mixed => $invoke('accessorProperty', $class, new ReflectionMethod($class, 'get'), null))
            ->toThrow(InvalidArgumentException::class)
            ->and(static fn(): mixed => $invoke('accessorProperty', $class, new ReflectionMethod($class, 'setReadonlyValue'), null))
            ->toThrow(InvalidArgumentException::class)
            ->and(static fn(): mixed => $invoke('invokerMethod', new ReflectionClass($class), new ReflectionMethod($class, 'unrelated'), null))
            ->toThrow(InvalidArgumentException::class)
            ->and(static fn(): mixed => $invoke('invokerMethod', new ReflectionClass($class), new ReflectionMethod($class, 'callRun'), 'callRun'))
            ->toThrow(InvalidArgumentException::class)
            ->and(static fn(): mixed => $invoke('invokerMethod', new ReflectionClass($class), new ReflectionMethod($class, 'run'), 'missing'))
            ->toThrow(InvalidArgumentException::class)
            ->and(static fn(): mixed => $invoke('invokerMethod', new ReflectionClass($class), new ReflectionMethod($class, 'run'), null))
            ->toThrow(InvalidArgumentException::class);
    });

    it('applies mutability and attaches groups through Zend internals', function (): void {
        $invoke = static function (string $name, mixed ...$arguments): mixed {
            $method = new ReflectionMethod(Zend::class, $name);

            return $method->invoke(null, ...$arguments);
        };
        $class = ZendEdgeTarget::class;

        /** @var ?Group $noGroup */
        $noGroup = $invoke('groupForMethod', new ReflectionMethod($class, 'run'));
        /** @var Group $grouped */
        $grouped = $invoke('groupForMethod', new ReflectionMethod($class, 'grouped'));

        expect($noGroup)->toBeNull()
            ->and($grouped->name)->toBe('edge')
            ->and(static fn(): mixed => $invoke('setPropertyMutability', $class, 'missing', false, true))
            ->toThrow(InvalidArgumentException::class)
            ->and(static fn(): mixed => $invoke('setMethodMutability', $class, '', false, true))
            ->toThrow(InvalidArgumentException::class)
            ->and(static fn(): mixed => $invoke('setMethodMutability', $class, 'missing', false, true))
            ->toThrow(InvalidArgumentException::class);

        $invoke('setPropertyMutability', $class, 'value', false, true);
        $invoke('setPropertyMutability', $class, 'value', true, false);
        $invoke('setMethodMutability', $class, 'run', false, true);
        $invoke('setMethodMutability', $class, 'run', true, false);

        $group = new Group('attached');
        /** @var Inject $attached */
        $attached = $invoke('attachGroup', new Inject('run', new At('HEAD')), $group);
        /** @var Inject $preserved */
        $preserved = $invoke('attachGroup', new Inject('run', new At('HEAD'), group: $group), new Group('ignored'));

        expect($attached->group)->toBe($group)
            ->and($preserved->group)->toBe($group);

        Zend::disableJitForFunction('strlen');
        Zend::disableJitForMethod($class, 'run');
        Zend::disableJitForClass($class);
    });

    it('manages Zend configuration state and strict diagnostics', function (): void {
        $invoke = static function (string $name, mixed ...$arguments): mixed {
            return (new ReflectionMethod(Zend::class, $name))->invoke(null, ...$arguments);
        };

        Zend::setDebugOptions(new DebugOptions(export: false, verbose: true, strict: true, verify: false));
        expect(Zend::debugOptions()->export)->toBeFalse()
            ->and(Zend::debugOptions()->verbose)->toBeTrue()
            ->and(Zend::debugOptions()->verify)->toBeFalse();
        Zend::clearTransformationSnapshots();
        expect(Zend::transformationSnapshots())->toBe([]);

        Zend::applyMixinConfigurations(ZendEdgeTarget::class, [
            new MixinConfiguration('MissingZendEdgeMixin', [ZendEdgeTarget::class], required: false),
        ]);
        expect(static fn() => Zend::applyMixinConfigurations(ZendEdgeTarget::class, [
            new MixinConfiguration('MissingZendEdgeMixin', [ZendEdgeTarget::class], required: true),
        ]))->toThrow(InvalidArgumentException::class, 'not declared');

        expect(static fn() => Zend::applyMixinConfigurations(ZendEdgeTarget::class, [
            new MixinConfiguration(ZendEdgeTarget::class, ['OtherZendEdgeTarget'], required: true),
        ]))->toThrow(InvalidArgumentException::class, 'incompatible');
        Zend::applyMixinConfigurations(ZendEdgeTarget::class, [
            new MixinConfiguration('MissingZendEdgeMixin', ['OtherZendEdgeTarget'], required: false),
        ]);

        expect(static fn() => Zend::registerPreloadConfigurations([
            new MixinConfiguration(ZendEdgeTarget::class, []),
        ]))->toThrow(InvalidArgumentException::class, 'at least one');
        Zend::registerPreloadConfigurations([
            new MixinConfiguration(ZendEdgeTarget::class, ['*'], required: false),
        ]);
        Zend::clearPreloadConfigurations();

        $invoke('autoloadPreloadTarget', 'MissingZendEdgeAutoloadTarget');
        $invoke('snapshotMethods', 'DateTime');
        $anonymousSnapshotTarget = new class {
            public function run(): string
            {
                return 'run';
            }
        };
        expect($invoke('snapshotMethods', $anonymousSnapshotTarget::class))->toBe([]);
        $invoke('debug', 'failed', ZendEdgeTarget::class, ZendInvalidPublicMixin::class, 'diagnostic');

        $active = new ReflectionProperty(Zend::class, 'activeTransformations');
        $active->setValue(null, [strtolower(ZendEdgeTarget::class) . '|' . strtolower(ZendInvalidPublicMixin::class) => true]);
        expect(static fn() => Zend::injectMixinMethods(ZendEdgeTarget::class, ZendInvalidPublicMixin::class))
            ->toThrow(InvalidArgumentException::class, 'already active');
        $active->setValue(null, []);
        Zend::setDebugOptions(new DebugOptions());

        expect($invoke('reflectionTypeString', null))->toBe('')
            ->and($invoke('reflectionTypeString', (new ReflectionMethod(ZendEdgeTarget::class, 'setValue'))->getParameters()[0]->getType()))
            ->toBe('string');
    });

    it('validates mixin constructors, overwrite signatures, shadows, and handler types', function (): void {
        $invoke = static function (string $name, mixed ...$arguments): mixed {
            return (new ReflectionMethod(Zend::class, $name))->invoke(null, ...$arguments);
        };

        expect(static fn() => $invoke('applyMixinMethods', ZendEdgeTarget::class, ZendInvalidPublicMixin::class))
            ->toThrow(InvalidArgumentException::class, 'final mixin')
            ->and(static fn() => $invoke('applyMixinMethods', ZendEdgeTarget::class, ZendInvalidParameterMixin::class))
            ->toThrow(InvalidArgumentException::class, 'private zero-argument')
            ->and(static fn() => $invoke('validateOverwriteSignature', 'MissingZendEdgeTarget', 'run', new ReflectionMethod(ZendEdgeTarget::class, 'run'), ZendEdgeTarget::class))
            ->toThrow(InvalidArgumentException::class, 'not declared')
            ->and(static fn() => $invoke('validateOverwriteSignature', ZendEdgeTarget::class, 'getValue', new ReflectionMethod(ZendEdgeTarget::class, 'setValue'), ZendEdgeTarget::class))
            ->toThrow(InvalidArgumentException::class, 'incompatible signature')
            ->and($invoke('shadowMethodTarget', ZendEdgeTarget::class, 'getValue', new Shadow()))
            ->toBe('getValue')
            ->and(static fn() => $invoke('shadowMethodTarget', ZendEdgeTarget::class, 'other', new Shadow(prefix: 'pre')))
            ->toThrow(InvalidArgumentException::class, 'does not start with prefix')
            ->and(static fn() => $invoke('shadowMethodTarget', ZendEdgeTarget::class, '', new Shadow()))
            ->toThrow(InvalidArgumentException::class, 'no target after prefix');

        expect($invoke('handlerVariableType', new ReflectionMethod(ZendEdgeTarget::class, 'run')))->toBeNull()
            ->and($invoke('handlerVariableType', new ReflectionMethod(ZendEdgeTypedHandlers::class, 'nullable')))->toBeNull()
            ->and($invoke('handlerVariableType', new ReflectionMethod(ZendEdgeTypedHandlers::class, 'coercedString')))->toContain('bool', 'int', 'float')
            ->and($invoke('handlerVariableType', new ReflectionMethod(ZendEdgeTypedHandlers::class, 'coercedBool')))->toContain('string', 'int', 'float')
            ->and($invoke('handlerVariableType', new ReflectionMethod(ZendEdgeTypedHandlers::class, 'coercedArray')))->toContain('array', 'iterable');

        expect(static fn() => Zend::injectMixinMethods(ZendIntrinsicStaticTarget::class, ZendIntrinsicStaticMixin::class))
            ->toThrow(InvalidArgumentException::class, 'cannot be static')
            ->and(static fn() => Zend::injectMixinMethods(ZendIntrinsicStaticTarget::class, zendDuplicateFixture('ZendDuplicateIntrinsicMixin')))
            ->toThrow(InvalidArgumentException::class, 'may have only one #[Intrinsic]')
            ->and(static fn() => Zend::injectMixinMethods(ZendCombinedAnnotationTarget::class, ZendCombinedAnnotationMixin::class))
            ->toThrow(InvalidArgumentException::class, 'Intrinsic')
            ->and(static fn() => Zend::injectMixinMethods(ZendSoftMissingTarget::class, ZendSoftMissingMixin::class))
            ->toThrow(InvalidArgumentException::class, 'no target method')
            ->and(static fn() => Zend::injectMixinMethods(ZendSoftMissingTarget::class, zendDuplicateFixture('ZendDuplicateSoftOverrideMixin')))
            ->toThrow(InvalidArgumentException::class, 'may have only one #[SoftOverride]')
            ->and(static fn() => Zend::injectMixinMethods(ZendSoftInheritedTarget::class, ZendSoftPrivateMixin::class))
            ->toThrow(InvalidArgumentException::class, 'cannot be private')
            ->and(static fn() => Zend::injectMixinMethods(ZendSoftCombinedTarget::class, ZendSoftCombinedMixin::class))
            ->toThrow(InvalidArgumentException::class, 'another composition')
            ->and(static fn() => Zend::injectMixinMethods(ZendPrintTarget::class, ZendPrintMissingMixin::class))
            ->toThrow(InvalidArgumentException::class, 'print mode')
            ->and(static fn() => Zend::injectMixinMethods(ZendInvokerMismatchTarget::class, ZendInvokerMismatchMixin::class))
            ->toThrow(InvalidArgumentException::class, 'both be static or instance')
            ->and(static fn() => $invoke('interfaceMethodMappings', new ReflectionClass(ZendDuplicateInterfaceMixin::class)))
            ->toThrow(InvalidArgumentException::class, 'more than one')
            ->and(static fn() => Zend::injectMixinMethods(ZendDuplicateSurrogateTarget::class, zendDuplicateFixture('ZendDuplicateSurrogateMixin')))
            ->toThrow(InvalidArgumentException::class, 'may have only one #[Surrogate]')
            ->and(static fn() => Zend::injectMixinMethods(ZendEdgeTarget::class, zendDuplicateFixture('ZendDuplicateAccessorMixin')))
            ->toThrow(InvalidArgumentException::class, 'may have only one #[Accessor]')
            ->and(static fn() => Zend::injectMixinMethods(ZendEdgeTarget::class, zendDuplicateFixture('ZendDuplicateFinalMixin')))
            ->toThrow(InvalidArgumentException::class, 'may have only one #[Final]')
            ->and(static fn() => Zend::injectMixinMethods(ZendEdgeTarget::class, zendDuplicateFixture('ZendDuplicateOverwriteMixin')))
            ->toThrow(InvalidArgumentException::class, 'may have only one #[Overwrite]')
            ->and(static fn() => Zend::injectMixinMethods(ZendEdgeTarget::class, zendDuplicateFixture('ZendDuplicateShadowMixin')))
            ->toThrow(InvalidArgumentException::class, 'may have only one #[Shadow]')
            ->and(static fn() => $invoke('groupForMethod', new ReflectionMethod(zendDuplicateFixture('ZendDuplicateGroupTarget'), 'grouped')))
            ->toThrow(InvalidArgumentException::class, 'may have only one #[Group]')
            ->and(static fn() => Zend::injectMixinMethods(ZendDuplicatePropertyTarget::class, zendDuplicateFixture('ZendDuplicatePropertyMixin')))
            ->toThrow(InvalidArgumentException::class, 'may have only one #[Final]')
            ->and(static fn() => $invoke('validateOverwriteSignature', ZendParameterTarget::class, 'convert', new ReflectionMethod(ZendParameterSource::class, 'convert'), ZendParameterSource::class))
            ->toThrow(InvalidArgumentException::class, 'incompatible signature');
    });
});
