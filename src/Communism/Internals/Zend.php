<?php

/*============================================================================*
 * SPDX-License-Identifier: 0BSD                                              *
 * SPDX-FileCopyrightText: 2026 Luca Mollema                                  *
 * Copyright (C) 2026 Luca Mollema                                            *
 *                                                                            *
 * Permission to use, copy, modify, and/or distribute this software for any   *
 * purpose with or without fee is hereby granted.                             *
 *                                                                            *
 * THE SOFTWARE IS PROVIDED “AS IS” AND THE AUTHOR DISCLAIMS ALL WARRANTIES   *
 * WITH REGARD TO THIS SOFTWARE INCLUDING ALL IMPLIED WARRANTIES OF           *
 * MERCHANTABILITY AND FITNESS. IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR    *
 * ANY SPECIAL, DIRECT, INDIRECT, OR CONSEQUENTIAL DAMAGES OR ANY DAMAGES     *
 * WHATSOEVER RESULTING FROM LOSS OF USE, DATA OR PROFITS, WHETHER IN AN      *
 * ACTION OF CONTRACT, NEGLIGENCE OR OTHER TORTIOUS ACTION, ARISING OUT OF OR *
 * IN CONNECTION WITH THE USE OR PERFORMANCE OF THIS SOFTWARE.                *
 *============================================================================*
 * :: Communism :: "In comrade PHP, all are public" ::                        *
 *----------------------------------------------------------------------------*
 * File: Zend.php                                                             *
 * Consumer: Internal                                                         *
 * Purpose: Mixin composition orchestration and compatibility constants.      *
 *============================================================================*/

declare(strict_types=1);

namespace Communism\Internals;

use Communism\Mixin\Accessor;
use Communism\Mixin\Applies;
use Communism\Mixin\At;
use Communism\Mixin\Coerce;
use Communism\Mixin\Dynamic;
use Communism\Mixin\DebugOptions;
use Communism\Mixin\Final_;
use Communism\Mixin\Group;
use Communism\Mixin\Inject;
use Communism\Mixin\Implements_;
use Communism\Mixin\Interface_;
use Communism\Mixin\Intrinsic;
use Communism\Mixin\Invoker;
use Communism\Mixin\Mixin;
use Communism\Mixin\MixinConfiguration;
use Communism\Mixin\ModifyArg;
use Communism\Mixin\ModifyArgs;
use Communism\Mixin\ModifyConstant;
use Communism\Mixin\ModifyVariable;
use Communism\Mixin\Mutable;
use Communism\Mixin\Overwrite;
use Communism\Mixin\Pseudo;
use Communism\Mixin\Redirect;
use Communism\Mixin\Shadow;
use Communism\Mixin\SoftOverride;
use Communism\Mixin\Surrogate;
use Communism\Mixin\Unique;
use InvalidArgumentException;
use Zendful\Zendful;

use function sprintf;

/**
 * Mixin composition orchestration and compatibility constants.
 *
 * @internal Runtime mutation is delegated to Zendful handles.
 */
final class Zend
{
    /** @var array<string, true> */
    private static array $activeTransformations = [];

    /** @var list<TransformationSnapshot> */
    private static array $snapshots = [];

    /** @var list<MixinConfiguration> */
    private static array $preloadConfigurations = [];

    private static bool $preloadRegistered = false;

    private static DebugOptions $debugOptions;

    public static function debugOptions(): DebugOptions
    {
        return self::$debugOptions ??= new DebugOptions();
    }

    public static function setDebugOptions(DebugOptions $options): void
    {
        self::$debugOptions = $options;
    }

    /** @return list<TransformationSnapshot> */
    public static function transformationSnapshots(): array
    {
        return self::$snapshots;
    }

    public static function clearTransformationSnapshots(): void
    {
        self::$snapshots = [];
    }

    /**
     * Apply declarative mixin configurations in stable ascending priority.
     *
     * @param list<MixinConfiguration> $configurations
     */
    public static function applyMixinConfigurations(string $className, array $configurations, ?string $environment = null): void
    {
        /** @var list<MixinConfiguration> $ordered */
        $ordered = MixinConfiguration::ordered($configurations);
        foreach ($ordered as $configuration) {
            if (!$configuration->appliesTo($className, $environment)) {
                if (self::debugOptions()->strict && $configuration->required
                    && ($configuration->environment === null || $configuration->environment === $environment)) {
                    throw new InvalidArgumentException(sprintf(
                        'Required mixin %s is incompatible with target %s or PHP %s',
                        $configuration->mixin,
                        $className,
                        PHP_VERSION,
                    ));
                }
                continue;
            }
            if (!class_exists($configuration->mixin)) {
                if (self::debugOptions()->strict && $configuration->required) {
                    throw new InvalidArgumentException(sprintf('Required mixin %s is not declared', $configuration->mixin));
                }
                continue;
            }

            self::injectMixinMethods($className, $configuration->mixin);
        }
    }

    /**
     * Register configurations to be applied immediately after autoloading a
     * matching target class. Concrete targets must not already be declared.
     *
     * @param list<MixinConfiguration> $configurations
     */
    public static function registerPreloadConfigurations(array $configurations, ?string $environment = null): void
    {
        $ordered = MixinConfiguration::ordered($configurations);
        foreach ($ordered as $configuration) {
            if ($configuration->targets === []) {
                throw new InvalidArgumentException('Preload configurations require at least one concrete target or "*"');
            }
            foreach ($configuration->targets as $target) {
                if ($target !== '*' && class_exists($target, false)) {
                    throw new InvalidArgumentException(sprintf(
                        'Cannot register preload mixin %s: target %s is already declared',
                        $configuration->mixin,
                        $target,
                    ));
                }
            }
        }

        self::$preloadConfigurations = [...self::$preloadConfigurations, ...$ordered];
        if (!self::$preloadRegistered) {
            spl_autoload_register([self::class, 'autoloadPreloadTarget'], true, true);
            self::$preloadRegistered = true;
        }
    }

    public static function clearPreloadConfigurations(): void
    {
        self::$preloadConfigurations = [];
        if (self::$preloadRegistered) {
            spl_autoload_unregister([self::class, 'autoloadPreloadTarget']);
            self::$preloadRegistered = false;
        }
    }

    private static function autoloadPreloadTarget(string $className): void
    {
        foreach (spl_autoload_functions() as $autoloader) {
            if ($autoloader === [self::class, 'autoloadPreloadTarget']) {
                continue;
            }
            call_user_func($autoloader, $className);
            if (class_exists($className, false)) {
                break;
            }
        }
        if (!class_exists($className, false)) {
            return;
        }

        $configurations = array_values(array_filter(
            self::$preloadConfigurations,
            static fn(MixinConfiguration $configuration): bool => $configuration->appliesTo($className),
        ));
        if ($configurations !== []) {
            self::applyMixinConfigurations($className, $configurations);
        }
    }

    /**
     * Inject selected methods from a non-instantiable mixin class into a class.
     *
     * @param string $className
     * @param class-string $traitName
     * @param list<non-empty-string>|null $methods
     */
    public static function injectMixinMethods(string $className, string $traitName, ?array $methods = null): void
    {
        $key = strtolower($className) . '|' . strtolower($traitName);
        if (isset(self::$activeTransformations[$key])) {
            throw new InvalidArgumentException(sprintf(
                'Mixin transformation is already active for %s with %s',
                $className,
                $traitName,
            ));
        }
        self::$activeTransformations[$key] = true;
        self::debug('started', $className, $traitName);
        $before = self::snapshotMethods($className);

        try {
            self::applyMixinMethods($className, $traitName, $methods);
            if (self::debugOptions()->export) {
                self::$snapshots[] = new TransformationSnapshot($className, $traitName, $before, self::snapshotMethods($className));
            }
            self::debug('completed', $className, $traitName);
        } catch (\Throwable $exception) {
            self::debug('failed', $className, $traitName, $exception->getMessage());
            throw $exception;
        } finally {
            unset(self::$activeTransformations[$key]);
        }
    }

    private static function debug(string $phase, string $className, string $mixin, ?string $error = null): void
    {
        if (!self::debugOptions()->verbose) {
            return;
        }

        $message = sprintf('[Communism\\Mixin] %s %s <- %s', $phase, $className, $mixin);
        if ($error !== null) {
            $message .= ': ' . $error;
        }
        fwrite(STDERR, $message . PHP_EOL);
    }

    /** @return array<string, string> */
    private static function snapshotMethods(string $className): array
    {
        if (!class_exists($className)) {
            return [];
        }

        $snapshot = [];
        foreach ((new \ReflectionClass($className))->getMethods() as $method) {
            if ($method->isInternal()) {
                continue;
            }
            try {
                $snapshot[$method->getName()] = \Communism\Bytecode\Bytecode::disassemble(
                    $method->getDeclaringClass()->getName() . '::' . $method->getName(),
                );
            } catch (\Throwable) {
                // A snapshot must never prevent an otherwise valid transform.
            }
        }

        ksort($snapshot);

        return $snapshot;
    }

    /**
     * @param class-string $traitName
     * @param list<non-empty-string>|null $methods
     */
    private static function applyMixinMethods(string $className, string $traitName, ?array $methods = null): void
    {
        $mixin = new \ReflectionClass($traitName);

        if ($mixin->isTrait() || !$mixin->isFinal()) {
            throw new InvalidArgumentException(sprintf('%s must be a final mixin class', $traitName));
        }
        $constructor = $mixin->getConstructor();
        if ($constructor === null || !$constructor->isPrivate() || $constructor->getNumberOfParameters() !== 0) {
            throw new InvalidArgumentException(sprintf(
                'Mixin %s must declare a private zero-argument constructor',
                $traitName,
            ));
        }
        if (!class_exists($className)) {
            if ($mixin->getAttributes(Pseudo::class) !== []) {
                return;
            }

            throw new InvalidArgumentException(sprintf('Mixin target class %s is not declared', $className));
        }

        $class = new \ReflectionClass($className);

        $mixins = $mixin->getAttributes(Mixin::class);
        if ($mixins === []) {
            throw new InvalidArgumentException(sprintf('Mixin %s must have at least one #[Mixin] declaration', $traitName));
        }

        $applies = $mixin->getAttributes(Applies::class);
        $allowed = false;
        foreach ($mixins as $targetDeclaration) {
            if ($targetDeclaration->newInstance()->allows($className)) {
                $allowed = true;
                break;
            }
        }
        foreach ($applies as $selector) {
            if ($selector->newInstance()->matches($className)) {
                $allowed = true;
                break;
            }
        }
        if (!$allowed) {
            throw new InvalidArgumentException(sprintf('Mixin %s is not allowed to be injected into %s', $traitName, $className));
        }

        $interfaceMappings = self::interfaceMethodMappings($mixin);
        $mixinUnique = $mixin->getAttributes(Unique::class) !== [];
        $propertyMappings = [];

        $availableMethods = [];
        /** @var array<string, string> $surrogates */
        $surrogates = [];
        foreach ($mixin->getMethods() as $method) {
            if ($method->getName() === '__construct') {
                continue;
            }
            $shadow = $method->getAttributes(Shadow::class);
            $overwrite = $method->getAttributes(Overwrite::class);
            $intrinsic = $method->getAttributes(Intrinsic::class);
            $softOverride = $method->getAttributes(SoftOverride::class);
            $injection = array_merge(
                $method->getAttributes(Inject::class),
                $method->getAttributes(ModifyArg::class),
                $method->getAttributes(ModifyArgs::class),
                $method->getAttributes(ModifyConstant::class),
                $method->getAttributes(ModifyVariable::class),
                $method->getAttributes(Redirect::class),
            );
            $accessor = $method->getAttributes(Accessor::class);
            $invoker = $method->getAttributes(Invoker::class);
            $final = $method->getAttributes(Final_::class);
            $mutable = $method->getAttributes(Mutable::class);
            $surrogate = $method->getAttributes(Surrogate::class);
            if (count($surrogate) > 1) {
                throw new InvalidArgumentException(sprintf('Trait method %s::%s may have only one #[Surrogate]', $traitName, $method->getName()));
            }
            if ($surrogate !== []) {
                $handlerName = \str_ends_with($method->getName(), 'Surrogate')
                    ? substr($method->getName(), 0, -strlen('Surrogate'))
                    : '';
                if ($handlerName === '') {
                    throw new InvalidArgumentException(sprintf(
                        'Surrogate method %s::%s must be named <handler>Surrogate',
                        $traitName,
                        $method->getName(),
                    ));
                }
                if ($shadow !== [] || $overwrite !== [] || $injection !== [] || $accessor !== [] || $invoker !== [] || $final !== [] || $mutable !== []) {
                    throw new InvalidArgumentException(sprintf(
                        'Surrogate method %s::%s cannot combine #[Surrogate] with another method annotation',
                        $traitName,
                        $method->getName(),
                    ));
                }
                if (isset($surrogates[$handlerName])) {
                    // PHP method names are case-insensitive and cannot be declared twice.
                    // @codeCoverageIgnoreStart
                    throw new InvalidArgumentException(sprintf(
                        'Trait %s declares more than one surrogate for handler %s',
                        $traitName,
                        $handlerName,
                    ));
                    // @codeCoverageIgnoreEnd
                }
                $surrogates[$handlerName] = $method->getName();
            }
            if (count($accessor) > 1 || count($invoker) > 1) {
                throw new InvalidArgumentException(sprintf('Trait method %s::%s may have only one #[Accessor] or #[Invoker]', $traitName, $method->getName()));
            }
            if ($accessor !== [] && $invoker !== []) {
                throw new InvalidArgumentException(sprintf('Trait method %s::%s cannot combine #[Accessor] and #[Invoker]', $traitName, $method->getName()));
            }
            $generated = array_merge($accessor, $invoker);
            if ($generated !== [] && ($shadow !== [] || $overwrite !== [] || $injection !== [] || $method->getAttributes(Unique::class) !== [])) {
                throw new InvalidArgumentException(sprintf('Trait method %s::%s cannot combine a generated member annotation with another method annotation', $traitName, $method->getName()));
            }
            if (count($final) > 1 || count($mutable) > 1) {
                throw new InvalidArgumentException(sprintf('Trait method %s::%s may have only one #[Final] and #[Mutable]', $traitName, $method->getName()));
            }
            if ($final !== [] && $injection !== []) {
                throw new InvalidArgumentException(sprintf('Trait method %s::%s cannot mark an injection handler #[Final]', $traitName, $method->getName()));
            }
            if ($mutable !== [] && $shadow === []) {
                throw new InvalidArgumentException(sprintf('#[Mutable] on mixin method %s::%s requires #[Shadow]', $traitName, $method->getName()));
            }
            if ($final !== [] && $mutable !== []) {
                throw new InvalidArgumentException(sprintf('Trait method %s::%s cannot combine #[Final] and #[Mutable]', $traitName, $method->getName()));
            }
            if (count($overwrite) > 1) {
                throw new InvalidArgumentException(sprintf('Trait method %s::%s may have only one #[Overwrite]', $traitName, $method->getName()));
            }
            if (count($intrinsic) > 1) {
                throw new InvalidArgumentException(sprintf('Trait method %s::%s may have only one #[Intrinsic]', $traitName, $method->getName()));
            }
            if ($intrinsic !== [] && $overwrite !== []) {
                throw new InvalidArgumentException(sprintf('Trait method %s::%s cannot combine #[Intrinsic] and #[Overwrite]', $traitName, $method->getName()));
            }
            if ($intrinsic !== [] && $method->isStatic()) {
                throw new InvalidArgumentException(sprintf('Intrinsic method %s::%s cannot be static', $traitName, $method->getName()));
            }
            if (count($softOverride) > 1) {
                throw new InvalidArgumentException(sprintf('Trait method %s::%s may have only one #[SoftOverride]', $traitName, $method->getName()));
            }
            if ($softOverride !== [] && ($overwrite !== [] || $intrinsic !== [] || $method->getAttributes(Unique::class) !== [])) {
                throw new InvalidArgumentException(sprintf('Trait method %s::%s cannot combine #[SoftOverride] with another composition annotation', $traitName, $method->getName()));
            }
            if (count($shadow) > 1) {
                throw new InvalidArgumentException(sprintf('Trait method %s::%s may have only one #[Shadow]', $traitName, $method->getName()));
            }
            if ($shadow !== [] && ($overwrite !== [] || $injection !== [] || $method->getAttributes(Unique::class) !== [])) {
                throw new InvalidArgumentException(sprintf('Trait method %s::%s cannot combine #[Shadow] with another method annotation', $traitName, $method->getName()));
            }
            if ($method->getAttributes(Group::class) !== [] && $injection === []) {
                throw new InvalidArgumentException(sprintf('Trait method %s::%s uses #[Group] without an injection annotation', $traitName, $method->getName()));
            }

            $availableMethods[$method->getName()] = $method;
        }

        foreach ($surrogates as $handlerName => $surrogateName) {
            if (!isset($availableMethods[$handlerName])) {
                throw new InvalidArgumentException(sprintf(
                    'Surrogate method %s::%s has no handler method %s',
                    $traitName,
                    $surrogateName,
                    $handlerName,
                ));
            }
            if ($availableMethods[$handlerName]->getAttributes(Inject::class) === []) {
                throw new InvalidArgumentException(sprintf(
                    'Surrogate method %s::%s must target an #[Inject] handler',
                    $traitName,
                    $surrogateName,
                ));
            }
        }

        $methodNames = $methods ?? array_keys($availableMethods);
        foreach ($methodNames as $methodName) {
            if (!isset($availableMethods[$methodName])) {
                throw new InvalidArgumentException(sprintf('Trait %s does not declare method %s', $traitName, $methodName));
            }
        }
        $methodsToCompose = array_values(array_filter(
            $methodNames,
            static function (string $methodName) use ($availableMethods): bool {
                $method = $availableMethods[$methodName];

                return $method->getAttributes(Shadow::class) === []
                    && $method->getAttributes(Accessor::class) === []
                    && $method->getAttributes(Invoker::class) === []
                    && $method->getAttributes(Inject::class) === []
                    && $method->getAttributes(ModifyArg::class) === []
                    && $method->getAttributes(ModifyArgs::class) === []
                    && $method->getAttributes(ModifyConstant::class) === []
                     && $method->getAttributes(ModifyVariable::class) === []
                     && $method->getAttributes(Redirect::class) === []
                     && $method->getAttributes(Surrogate::class) === [];
            },
        ));
        foreach ($methodNames as $methodName) {
            $method = $availableMethods[$methodName];
            $shadow = $method->getAttributes(Shadow::class);
            if ($shadow === []) {
                continue;
            }

            $shadowTarget = self::shadowMethodTarget($className, $methodName, $shadow[0]->newInstance());
            if (!$class->hasMethod($shadowTarget)) {
                throw new InvalidArgumentException(sprintf(
                    'Class %s has no method %s shadowed by %s::%s',
                    $className,
                    $shadowTarget,
                    $traitName,
                    $methodName,
                ));
            }
            if ($method->getAttributes(Mutable::class) !== [] && $method->getAttributes(Final_::class) !== []) {
                // The earlier declaration validation rejects this combination first.
                // @codeCoverageIgnoreStart
                throw new InvalidArgumentException(sprintf('Trait method %s::%s cannot combine #[Final] and #[Mutable]', $traitName, $methodName));
                // @codeCoverageIgnoreEnd
            }
        }
        foreach ($methodsToCompose as $methodName) {
            $overwrite = $availableMethods[$methodName]->getAttributes(Overwrite::class);
            $intrinsic = $availableMethods[$methodName]->getAttributes(Intrinsic::class);
            $softOverride = $availableMethods[$methodName]->getAttributes(SoftOverride::class);
            $targetMethod = $interfaceMappings[$methodName]['target']
                ?? ($overwrite === [] ? $methodName : self::overwriteTargetName($className, $methodName, $overwrite[0]->newInstance()));
            if ($targetMethod === '') {
                throw new InvalidArgumentException('Injected method names must not be empty');
            }
            $hasTargetMethod = $class->hasMethod($targetMethod);

            if ($softOverride !== []) {
                if (!$hasTargetMethod) {
                    throw new InvalidArgumentException(sprintf('SoftOverride method %s::%s has no target method to override', $traitName, $methodName));
                }
                $targetReflection = new \ReflectionMethod($className, $targetMethod);
                if ($targetReflection->getDeclaringClass()->getName() === $className) {
                    throw new InvalidArgumentException(sprintf('SoftOverride method %s::%s must target an inherited method', $traitName, $methodName));
                }
                if ($availableMethods[$methodName]->isPrivate()) {
                    throw new InvalidArgumentException(sprintf('SoftOverride method %s::%s cannot be private', $traitName, $methodName));
                }
                self::validateOverwriteSignature($className, $targetMethod, $availableMethods[$methodName], $traitName);
            }


            $unique = $mixinUnique
                || $availableMethods[$methodName]->getAttributes(Unique::class) !== []
                || ($interfaceMappings[$methodName]['unique'] ?? false);
            if ($overwrite === [] && $hasTargetMethod && !$unique && $intrinsic === [] && $softOverride === []) {
                throw new InvalidArgumentException(sprintf('Class %s already has method %s', $className, $targetMethod));
            }

            if ($overwrite !== [] && !$hasTargetMethod) {
                throw new InvalidArgumentException(sprintf('Class %s has no method %s to override', $className, $targetMethod));
            }
            if ($overwrite !== []) {
                self::validateOverwriteSignature(
                    $className,
                    $targetMethod,
                    $availableMethods[$methodName],
                    $traitName,
                );
            }
            if ($intrinsic !== [] && $intrinsic[0]->newInstance()->displace && $hasTargetMethod
                && (new \ReflectionMethod($className, $targetMethod))->getDeclaringClass()->getName() !== $className
            ) {
                throw new InvalidArgumentException(sprintf(
                    'Intrinsic method %s::%s cannot displace an inherited method',
                    $traitName,
                    $methodName,
                ));
            }

        }
        foreach ($methodNames as $methodName) {
            $method = $availableMethods[$methodName];
            $generated = array_merge($method->getAttributes(Accessor::class), $method->getAttributes(Invoker::class));
            if ($generated === []) {
                continue;
            }
            if ($class->hasMethod($methodName)) {
                throw new InvalidArgumentException(sprintf('Class %s already has generated member method %s', $className, $methodName));
            }

            self::validateGeneratedMethod($className, $method, $generated[0]->newInstance());
        }

        foreach ($mixin->getProperties() as $traitProperty) {
            $shadowAttributes = $traitProperty->getAttributes(Shadow::class);
            if ($shadowAttributes === []) {
                throw new InvalidArgumentException(sprintf('Trait property %s::$%s must be marked with #[Shadow]', $traitName, $traitProperty->getName()));
            }
            $shadow = $shadowAttributes[0]->newInstance();
            $targetProperty = self::shadowPropertyTarget($className, $traitProperty->getName(), $shadow);
            $propertyMappings[$traitProperty->getName()] = $targetProperty;

            try {
                $classProperty = $class->getProperty($targetProperty);
            } catch (\ReflectionException) {
                throw new InvalidArgumentException(sprintf('Class %s is missing mixin property %s', $className, $targetProperty));
            }

            if (\strval($traitProperty->getType()) !== \strval($classProperty->getType())
                || $traitProperty->isStatic() !== $classProperty->isStatic()
                || $traitProperty->isReadOnly() !== $classProperty->isReadOnly()
            ) {
                throw new InvalidArgumentException(sprintf('Trait property %s does not match class %s::$%s', $traitName, $className, $targetProperty));
            }
            $propertyFinal = $traitProperty->getAttributes(Final_::class);
            $propertyMutable = $traitProperty->getAttributes(Mutable::class);
            if (count($propertyFinal) > 1 || count($propertyMutable) > 1) {
                throw new InvalidArgumentException(sprintf('Trait property %s::$%s may have only one #[Final] and #[Mutable]', $traitName, $traitProperty->getName()));
            }
            if ($propertyFinal !== [] && $propertyMutable !== []) {
                throw new InvalidArgumentException(sprintf('Trait property %s::$%s cannot combine #[Final] and #[Mutable]', $traitName, $traitProperty->getName()));
            }
            if ($propertyMutable !== [] && !$classProperty->isReadOnly()) {
                throw new InvalidArgumentException(sprintf('#[Mutable] property %s::$%s must shadow a readonly target property', $className, $traitProperty->getName()));
            }
        }

        // Resolve every declarative injection anchor before mutating the
        // target's function table. This gives #[Inject] the same fail-fast
        // behavior as Mixin's injection points and leaves the class untouched
        // on a miss.
        /** @var array<string, list<array{inject: Inject, handler: string, surrogate: string|null}>> $injectionsByMethod */
        $injectionsByMethod = [];
        foreach ($methodNames as $methodName) {
            $method = $availableMethods[$methodName];
            $group = self::groupForMethod($method);
            foreach ($method->getAttributes(Inject::class) as $attribute) {
                $inject = self::attachGroup($attribute->newInstance(), $group);
                foreach ($inject->targets as $targetMethod) {
                    if (!$class->hasMethod($targetMethod)) {
                        throw new InvalidArgumentException(sprintf(
                            'Class %s has no method %s for injection from %s::%s%s',
                            $className,
                            $targetMethod,
                            $traitName,
                            $method->getName(),
                            self::dynamicDiagnostic($method),
                        ));
                    }

                    $injectionsByMethod[$targetMethod][] = [
                        'inject' => $inject,
                        'handler' => $method->getName(),
                        'surrogate' => $surrogates[$method->getName()] ?? null,
                    ];
                }
            }
            foreach ($method->getAttributes(ModifyConstant::class) as $attribute) {
                $modify = $attribute->newInstance();
                if (strtolower($modify->at->value) !== 'constant') {
                    throw new InvalidArgumentException(sprintf(
                        '#[ModifyConstant] on %s::%s must use #[At("CONSTANT")]',
                        $traitName,
                        $method->getName(),
                    ));
                }
                $constantTarget = $modify->constant === null && $modify->type !== 'null' && !$modify->nullValue ? '' : $modify->constant;
                foreach ($modify->targets as $targetMethod) {
                    $at = new At($modify->at->value, $constantTarget, $modify->at->ordinal, $modify->at->shift, $modify->at->by, opcode: $modify->at->opcode);
                    $inject = self::attachGroup(new Inject($targetMethod, $at, true, null, null, null, $modify->slice, null, null, $modify->type, nullValue: $modify->nullValue), $group);
                    if (!$class->hasMethod($targetMethod)) {
                        throw new InvalidArgumentException(sprintf('Class %s has no method %s for injection from %s::%s', $className, $targetMethod, $traitName, $method->getName()));
                    }

                    $injectionsByMethod[$targetMethod][] = [
                        'inject' => $inject,
                        'handler' => $method->getName(),
                        'surrogate' => null,
                    ];
                }
            }
            foreach ($method->getAttributes(ModifyVariable::class) as $attribute) {
                $modify = $attribute->newInstance();
                if (!in_array(strtolower($modify->at->value), ['store', 'load'], true)) {
                    throw new InvalidArgumentException(sprintf(
                        '#[ModifyVariable] on %s::%s must use #[At("STORE")] or #[At("LOAD")]',
                        $traitName,
                        $method->getName(),
                    ));
                }
                if ($modify->print) {
                    foreach ($modify->targets as $targetMethod) {
                        if (!$class->hasMethod($targetMethod)) {
                            throw new InvalidArgumentException(sprintf('Class %s has no method %s for ModifyVariable print mode from %s::%s', $className, $targetMethod, $traitName, $method->getName()));
                        }
                        self::printModifyVariableLocals(
                            \Communism\Internals\Needle\Decompiler::decompile($className . '::' . $targetMethod),
                            $className,
                            $targetMethod,
                        );
                    }
                    continue;
                }
                $ordinal = $modify->ordinal >= 0 ? $modify->ordinal : $modify->at->ordinal;
                foreach ($modify->targets as $targetMethod) {
                    $at = new At($modify->at->value, $modify->name ?? '', $ordinal, $modify->at->shift, $modify->at->by, opcode: $modify->at->opcode);
                    $inject = self::attachGroup(new Inject(
                        $targetMethod,
                        $at,
                        require: $modify->require,
                        expect: $modify->expect,
                        allow: $modify->allow,
                        variableIndex: $modify->index,
                        variableType: self::handlerVariableType($method),
                        variableArgsOnly: $modify->argsOnly,
                        slice: $modify->slice,
                    ), $group);
                    if (!$class->hasMethod($targetMethod)) {
                        throw new InvalidArgumentException(sprintf('Class %s has no method %s for injection from %s::%s', $className, $targetMethod, $traitName, $method->getName()));
                    }

                    $injectionsByMethod[$targetMethod][] = [
                        'inject' => $inject,
                        'handler' => $method->getName(),
                        'surrogate' => null,
                    ];
                }
            }
            foreach ($method->getAttributes(ModifyArg::class) as $attribute) {
                $modify = $attribute->newInstance();
                if (strtolower($modify->at->value) !== 'invoke') {
                    throw new InvalidArgumentException(sprintf(
                        '#[ModifyArg] on %s::%s must use an invocation injection point',
                        $traitName,
                        $method->getName(),
                    ));
                }
                foreach ($modify->targets as $targetMethod) {
                    $at = new At($modify->at->value, $modify->at->target, $modify->at->ordinal, $modify->at->shift, $modify->at->by, opcode: $modify->at->opcode);
                    $inject = self::attachGroup(new Inject($targetMethod, $at, argumentIndex: $modify->index, slice: $modify->slice), $group);
                    if (!$class->hasMethod($targetMethod)) {
                        throw new InvalidArgumentException(sprintf('Class %s has no method %s for injection from %s::%s', $className, $targetMethod, $traitName, $method->getName()));
                    }
                    $injectionsByMethod[$targetMethod][] = ['inject' => $inject, 'handler' => $method->getName(), 'surrogate' => null];
                }
            }
            foreach ($method->getAttributes(ModifyArgs::class) as $attribute) {
                $modify = $attribute->newInstance();
                if (strtolower($modify->at->value) !== 'invoke') {
                    throw new InvalidArgumentException(sprintf(
                        '#[ModifyArgs] on %s::%s must use an invocation injection point',
                        $traitName,
                        $method->getName(),
                    ));
                }
                foreach ($modify->targets as $targetMethod) {
                    $at = new At($modify->at->value, $modify->at->target, $modify->at->ordinal, $modify->at->shift, $modify->at->by, 'replace', $modify->at->opcode);
                    $inject = self::attachGroup(new Inject($targetMethod, $at, true, slice: $modify->slice, mode: 'args'), $group);
                    if (!$class->hasMethod($targetMethod)) {
                        throw new InvalidArgumentException(sprintf('Class %s has no method %s for injection from %s::%s', $className, $targetMethod, $traitName, $method->getName()));
                    }
                    $injectionsByMethod[$targetMethod][] = ['inject' => $inject, 'handler' => $method->getName(), 'surrogate' => null];
                }
            }
            foreach ($method->getAttributes(Redirect::class) as $attribute) {
                $redirect = $attribute->newInstance();
                if (!in_array(strtolower($redirect->at->value), ['invoke', 'field', 'new'], true)) {
                    throw new InvalidArgumentException(sprintf(
                        '#[Redirect] on %s::%s must use an INVOKE, FIELD, or NEW injection point',
                        $traitName,
                        $method->getName(),
                    ));
                }
                foreach ($redirect->targets as $targetMethod) {
                    $inject = self::attachGroup(new Inject($targetMethod, new At($redirect->at->value, $redirect->at->target, $redirect->at->ordinal, $redirect->at->shift, $redirect->at->by, 'replace', $redirect->at->opcode), slice: $redirect->slice), $group);
                    if (!$class->hasMethod($targetMethod)) {
                        throw new InvalidArgumentException(sprintf('Class %s has no method %s for injection from %s::%s', $className, $targetMethod, $traitName, $method->getName()));
                    }
                    $injectionsByMethod[$targetMethod][] = ['inject' => $inject, 'handler' => $method->getName(), 'surrogate' => null];
                }
            }
        }

        /** @var array<string, \Communism\Internals\Needle\MethodBody> $rewrittenBodies */
        $rewrittenBodies = [];
        foreach ($injectionsByMethod as $targetMethod => $injections) {
            $body = \Communism\Internals\Needle\Decompiler::decompile($className . '::' . $targetMethod);
            $definitions = [];
            $handlers = [];
            $handlerNames = [];
            $surrogateHandlers = [];
            foreach ($injections as $injection) {
                $definitions[] = $injection['inject'];
                $handlers[spl_object_id($injection['inject'])] = \Communism\Internals\Needle\Decompiler::decompile($traitName . '::' . $injection['handler']);
                $handlerNames[spl_object_id($injection['inject'])] = $injection['handler'];
                if ($injection['surrogate'] !== null) {
                    $surrogateHandlers[spl_object_id($injection['inject'])] = \Communism\Internals\Needle\Decompiler::decompile($traitName . '::' . $injection['surrogate']);
                }
            }

            $rewrittenBodies[$targetMethod] = \Communism\Internals\Needle\Injector::inject(
                $body,
                $definitions,
                static fn(Inject $inject): \Communism\Internals\Needle\MethodBody => $handlers[spl_object_id($inject)],
                static function (Inject $inject, \Communism\Internals\Needle\MethodBody $handler, \Communism\Internals\Needle\CaptureException $exception) use ($surrogateHandlers): ?\Communism\Internals\Needle\MethodBody {
                    return $surrogateHandlers[spl_object_id($inject)] ?? null;
                },
                $traitName,
                static fn(Inject $inject): string => $handlerNames[spl_object_id($inject)],
            );
        }

        foreach ($mixin->getProperties() as $traitProperty) {
            $propertyFinal = $traitProperty->getAttributes(Final_::class) !== [];
            $propertyMutable = $traitProperty->getAttributes(Mutable::class) !== [];
            if (!$propertyFinal && !$propertyMutable) {
                continue;
            }

            self::setPropertyMutability($className, self::shadowPropertyTarget($className, $traitProperty->getName(), $traitProperty->getAttributes(Shadow::class)[0]->newInstance()), $propertyMutable, $propertyFinal);
        }

        foreach ($methodNames as $methodName) {
            $method = $availableMethods[$methodName];
            $accessor = $method->getAttributes(Accessor::class);
            $invoker = $method->getAttributes(Invoker::class);
            if ($accessor === [] && $invoker === []) {
                continue;
            }

            $generated = $accessor !== [] ? $accessor[0]->newInstance() : $invoker[0]->newInstance();
            $sourceMethod = Zendful::method($traitName, $methodName);
            class_exists(AccessorInvokerTemplates::class);
            if ($accessor !== []) {
                $templateName = self::accessorIsSetter($method)
                    ? ($method->isStatic() ? 'accessorStaticSet' : 'accessorSet')
                    : ($method->isStatic() ? 'accessorStaticGet' : 'accessorGet');
                $templateMethod = Zendful::method(AccessorInvokerTemplates::class, $templateName);
                $property = self::accessorProperty($className, $method, $generated->target);
                self::installGeneratedAccessor($sourceMethod, $templateMethod, $className, $methodName, $property);
            } else {
                $targetMethod = self::invokerMethod($class, $method, $generated->target);
                if ($method->isStatic() !== $class->getMethod($targetMethod)->isStatic()) {
                    throw new InvalidArgumentException(sprintf(
                        'Invoker %s::%s and target %s::%s must both be static or instance methods',
                        $traitName,
                        $methodName,
                        $className,
                        $targetMethod,
                    ));
                }
                Zendful::method($className, $targetMethod)->installInto(Zendful::class($className), $methodName);
                Zendful::method($className, $methodName)->setVisibility(self::methodVisibility($method));
            }
        }

        foreach ($methodsToCompose as $methodName) {
            $sourceMethod = Zendful::method($traitName, $methodName);

            $overwrite = $availableMethods[$methodName]->getAttributes(Overwrite::class);
            $intrinsic = $availableMethods[$methodName]->getAttributes(Intrinsic::class);
            $softOverride = $availableMethods[$methodName]->getAttributes(SoftOverride::class);
            $targetMethod = $interfaceMappings[$methodName]['target']
                ?? ($overwrite === [] ? $methodName : self::overwriteTargetName($className, $methodName, $overwrite[0]->newInstance()));
            $methodLower = mb_strtolower($targetMethod);
            if ($propertyMappings !== []) {
                $sourceBody = \Communism\Internals\Needle\Decompiler::decompile($traitName . '::' . $methodName);
                $rewritten = self::rewriteShadowProperties($sourceBody, $propertyMappings);
                \Communism\Internals\Needle\Assembler::write($rewritten, $sourceMethod->opArray());
            }

            if ($intrinsic !== [] && method_exists($className, $targetMethod)) {
                if (!$intrinsic[0]->newInstance()->displace) {
                    continue;
                }
            }

            $displacedName = null;
            if ($overwrite !== []) {
                $deletedName = '';
                for ($deleted = 0; $deletedName === ''; $deleted++) {
                    $candidate = '__deleted' . $deleted;
                    if (!method_exists($className, $candidate)) {
                        $deletedName = $candidate;
                    }
                }
                Zendful::method($className, $targetMethod)->renameTo($deletedName);
                $displacedName = $deletedName;
            } elseif ($intrinsic !== [] && method_exists($className, $targetMethod)) {
                $deletedName = '';
                for ($deleted = 0; $deletedName === ''; $deleted++) {
                    $candidate = '__intrinsic' . $deleted;
                    if (!method_exists($className, $candidate)) {
                        $deletedName = $candidate;
                    }
                }
                Zendful::method($className, $targetMethod)->renameTo($deletedName);
                $displacedName = $deletedName;
            }

            if ($overwrite === [] && method_exists($className, $targetMethod) && $softOverride === []) {
                $uniqueName = '__unique_' . mb_strtolower($traitName) . '_' . $targetMethod;
                $uniqueName = preg_replace('/[^a-z0-9_]+/i', '_', $uniqueName) ?? ('__unique_' . $targetMethod);
                for ($suffix = 0; method_exists($className, $uniqueName); $suffix++) {
                    $uniqueName = '__unique_' . $targetMethod . '_' . $suffix;
                }
                $targetMethod = $uniqueName;
                $methodLower = mb_strtolower($targetMethod);
            }

            if ($displacedName !== null) {
                $sourceBody = \Communism\Internals\Needle\Decompiler::decompile($traitName . '::' . $methodName);
                $rewritten = self::rewriteIntrinsicCalls($sourceBody, $targetMethod, $displacedName);
                \Communism\Internals\Needle\Assembler::write($rewritten, $sourceMethod->opArray());
            }

            $sourceMethod->installInto(
                Zendful::class($className),
                $targetMethod,
                $availableMethods[$methodName]->getAttributes(Final_::class) !== [],
            );
        }

        foreach ($rewrittenBodies as $targetMethod => $rewrittenBody) {
            \Communism\Internals\Needle\Assembler::write(
                $rewrittenBody,
                Zendful::method($className, $targetMethod)->opArray(),
            );
        }

        foreach ($methodNames as $methodName) {
            $method = $availableMethods[$methodName];
            $shadow = $method->getAttributes(Shadow::class);
            $mutable = $method->getAttributes(Mutable::class) !== [];
            $final = $method->getAttributes(Final_::class) !== [];
            if ($shadow === [] || (!$mutable && !$final)) {
                continue;
            }

            $shadowTarget = self::shadowMethodTarget($className, $methodName, $shadow[0]->newInstance());
            self::setMethodMutability($className, $shadowTarget, $mutable, $final);
        }

        foreach ($mixin->getAttributes(Implements_::class) as $declaration) {
            foreach ($declaration->newInstance()->interfaces as $interface) {
                Zendful::class($className)->implementInterface(Zendful::class($interface->interface));
            }
        }

        self::disableJitForClass($className);
    }

    private static function printModifyVariableLocals(\Communism\Internals\Needle\MethodBody $body, string $className, string $methodName): void
    {
        $locals = [];
        foreach ($body->instructions() as $instruction) {
            foreach ([$instruction->result, $instruction->operand1, $instruction->operand2] as $operand) {
                if ($operand->kind !== \Communism\Internals\Needle\Operand::CV || !is_int($operand->value)) {
                    continue;
                }
                $name = $body->variableName($operand);
                if ($name === null || isset($locals[$operand->value])) {
                    continue;
                }
                $locals[$operand->value] = sprintf(
                    '%d $%s (%s)',
                    $body->variableIndex($operand) ?? $operand->value,
                    $name,
                    $body->variableType($operand) ?? 'unknown',
                );
            }
        }
        ksort($locals);
        fwrite(STDERR, sprintf("ModifyVariable locals for %s::%s:\n", $className, $methodName));
        foreach ($locals as $local) {
            fwrite(STDERR, "  " . $local . "\n");
        }
    }

    private static function rewriteIntrinsicCalls(\Communism\Internals\Needle\MethodBody $body, string $method, string $displaced): \Communism\Internals\Needle\MethodBody
    {
        $instructions = [];
        foreach ($body->instructions() as $instruction) {
            if (in_array($instruction->name, ['INIT_METHOD_CALL', 'INIT_STATIC_METHOD_CALL'], true)
                && $instruction->operand2->kind === \Communism\Internals\Needle\Operand::CONSTANT
                && $instruction->operand2->value === $method
            ) {
                $instruction = $instruction->withOperands(
                    $instruction->operand1,
                    $instruction->operand2->withValue($displaced),
                );
            }
            $instructions[] = $instruction;
        }

        return $body->withInstructions($instructions);
    }

    /**
     * @param \ReflectionClass<object> $mixin
     * @return array<string, array{target: string, unique: bool}>
     */
    private static function interfaceMethodMappings(\ReflectionClass $mixin): array
    {
        $declarations = $mixin->getAttributes(Implements_::class);
        if ($declarations === []) {
            return [];
        }

        $mappings = [];
        foreach ($declarations[0]->newInstance()->interfaces as $declaration) {
            foreach ((new \ReflectionClass($declaration->interface))->getMethods() as $interfaceMethod) {
                $sourceName = $declaration->prefix . $interfaceMethod->getName();
                if (!$mixin->hasMethod($sourceName)
                    && in_array($declaration->remap, [\Communism\Mixin\InterfaceRemap::ALL, \Communism\Mixin\InterfaceRemap::FORCE], true)
                    && $mixin->hasMethod($interfaceMethod->getName())
                ) {
                    $sourceName = $interfaceMethod->getName();
                }
                if (!$mixin->hasMethod($sourceName)) {
                    throw new InvalidArgumentException(sprintf(
                        'Mixin %s is missing %s for interface %s::%s',
                        $mixin->getName(),
                        $sourceName,
                        $declaration->interface,
                        $interfaceMethod->getName(),
                    ));
                }
                if (isset($mappings[$sourceName])) {
                    throw new InvalidArgumentException(sprintf(
                        'Mixin %s maps more than one interface method to %s',
                        $mixin->getName(),
                        $sourceName,
                    ));
                }
                $mappings[$sourceName] = [
                    'target' => $interfaceMethod->getName(),
                    'unique' => $declaration->unique,
                ];
            }
        }

        return $mappings;
    }

    private static function overwriteTargetName(string $className, string $sourceName, Overwrite $overwrite): string
    {
        $primary = $overwrite->method ?? $sourceName;
        foreach ([$primary, ...$overwrite->aliases] as $candidate) {
            if (method_exists($className, $candidate)) {
                return $candidate;
            }
        }

        return $primary;
    }

    private static function validateOverwriteSignature(
        string $className,
        string $targetMethod,
        \ReflectionMethod $sourceMethod,
        string $traitName,
    ): void {
        if (!class_exists($className)) {
            throw new InvalidArgumentException(sprintf('Class %s is not declared', $className));
        }
        $target = (new \ReflectionClass($className))->getMethod($targetMethod);
        $sameShape = $sourceMethod->isStatic() === $target->isStatic()
            && $sourceMethod->isVariadic() === $target->isVariadic()
            && $sourceMethod->getNumberOfParameters() === $target->getNumberOfParameters()
            && self::reflectionTypeString($sourceMethod->getReturnType()) === self::reflectionTypeString($target->getReturnType());

        if ($sameShape) {
            foreach ($sourceMethod->getParameters() as $index => $sourceParameter) {
                $targetParameter = $target->getParameters()[$index];
                if ($sourceParameter->isVariadic() !== $targetParameter->isVariadic()
                    || $sourceParameter->isPassedByReference() !== $targetParameter->isPassedByReference()
                    || self::reflectionTypeString($sourceParameter->getType()) !== self::reflectionTypeString($targetParameter->getType())
                ) {
                    $sameShape = false;
                    break;
                }
            }
        }

        $sameVisibility = $sourceMethod->isPublic() === $target->isPublic()
            && $sourceMethod->isProtected() === $target->isProtected()
            && $sourceMethod->isPrivate() === $target->isPrivate();
        if (!$sameShape || !$sameVisibility) {
            throw new InvalidArgumentException(sprintf(
                'Overwrite %s::%s has an incompatible signature for %s::%s',
                $traitName,
                $sourceMethod->getName(),
                $className,
                $targetMethod,
            ));
        }
    }

    private static function dynamicDiagnostic(\ReflectionMethod $method): string
    {
        $dynamic = $method->getAttributes(Dynamic::class);
        if ($dynamic === []) {
            return '';
        }

        return sprintf(' (dynamic: %s)', $dynamic[0]->newInstance()->description());
    }

    private static function reflectionTypeString(?\ReflectionType $type): string
    {
        return $type === null ? '' : (string) $type;
    }

    private static function shadowMethodTarget(string $className, string $methodName, Shadow $shadow): string
    {
        $primary = $shadow->target;
        if ($primary === null && $shadow->prefix === '') {
            $primary = $methodName;
        }
        if ($primary === null && !\str_starts_with($methodName, $shadow->prefix)) {
            throw new InvalidArgumentException(sprintf(
                'Shadow method %s does not start with prefix %s',
                $methodName,
                $shadow->prefix,
            ));
        }

        $target = $primary ?? substr($methodName, strlen($shadow->prefix));
        if ($target === '') {
            throw new InvalidArgumentException(sprintf(
                'Shadow method %s has no target after prefix %s',
                $methodName,
                $shadow->prefix,
            ));
        }

        foreach ([$target, ...$shadow->aliases] as $candidate) {
            if (method_exists($className, $candidate)) {
                return $candidate;
            }
        }

        return $target;
    }

    private static function shadowPropertyTarget(string $className, string $propertyName, Shadow $shadow): string
    {
        $primary = $shadow->target ?? $propertyName;
        foreach ([$primary, ...$shadow->aliases] as $candidate) {
            if (property_exists($className, $candidate)) {
                return $candidate;
            }
        }

        return $primary;
    }

    /** @param array<string, string> $mappings */
    private static function rewriteShadowProperties(\Communism\Internals\Needle\MethodBody $body, array $mappings): \Communism\Internals\Needle\MethodBody
    {
        $instructions = [];
        foreach ($body->instructions() as $instruction) {
            if (in_array($instruction->name, ['FETCH_OBJ_R', 'FETCH_OBJ_W', 'FETCH_OBJ_IS', 'ASSIGN_OBJ'], true)
                && $instruction->operand2->kind === \Communism\Internals\Needle\Operand::CONSTANT
                && is_string($instruction->operand2->value)
                && isset($mappings[$instruction->operand2->value])
            ) {
                $instruction = $instruction->withOperands(
                    $instruction->operand1,
                    $instruction->operand2->withValue($mappings[$instruction->operand2->value]),
                );
            }
            $instructions[] = $instruction;
        }

        return $body->withInstructions($instructions);
    }

    /** @param class-string $className */
    private static function validateGeneratedMethod(string $className, \ReflectionMethod $method, Accessor|Invoker $generated): void
    {
        if ($generated instanceof Accessor) {
            self::accessorProperty($className, $method, $generated->target);

            return;
        }

        self::invokerMethod(new \ReflectionClass($className), $method, $generated->target);
    }

    /**
     * @param class-string $className
     * @return non-empty-string
     */
    private static function accessorProperty(string $className, \ReflectionMethod $method, ?string $target): string
    {
        $parameters = $method->getParameters();
        $name = $method->getName();
        $isSetter = count($parameters) === 1 && str_starts_with($name, 'set');
        $isGetter = count($parameters) === 0 && (str_starts_with($name, 'get') || str_starts_with($name, 'is') || $target !== null);
        if (!$isSetter && !$isGetter) {
            throw new InvalidArgumentException(sprintf('Accessor %s::%s must be a get/is method with no arguments or a set method with one argument', $className, $name));
        }

        $property = $target;
        if ($property === null) {
            $prefixLength = $isSetter || str_starts_with($name, 'get') ? 3 : 2;
            $property = lcfirst(substr($name, $prefixLength));
        }
        if ($property === '') {
            throw new InvalidArgumentException(sprintf('Accessor %s::%s resolved to an empty property name', $className, $name));
        }

        try {
            $reflection = new \ReflectionProperty($className, $property);
        } catch (\ReflectionException $exception) {
            throw new InvalidArgumentException(sprintf('Accessor %s::%s targets missing property $%s', $className, $name, $property), 0, $exception);
        }

        if ($reflection->isStatic() !== $method->isStatic()) {
            throw new InvalidArgumentException(sprintf('Accessor %s::%s and property %s::$%s must both be static or instance members', $className, $name, $className, $property));
        }
        if ($isSetter && $reflection->isReadOnly()) {
            throw new InvalidArgumentException(sprintf('Accessor %s::%s cannot write readonly property %s::$%s', $className, $name, $className, $property));
        }

        return $property;
    }

    private static function accessorIsSetter(\ReflectionMethod $method): bool
    {
        return count($method->getParameters()) === 1 && str_starts_with($method->getName(), 'set');
    }

    private static function installGeneratedAccessor(
        \Zendful\MethodHandle $source,
        \Zendful\MethodHandle $template,
        string $className,
        string $methodName,
        string $property,
    ): void {
        $templateName = $template->methodName();
        $original = \Communism\Internals\Needle\Decompiler::decompile(AccessorInvokerTemplates::class . '::' . $templateName);
        $rewritten = $original->withInstructions(array_map(
            static fn(\Communism\Internals\Needle\Instruction $instruction): \Communism\Internals\Needle\Instruction => $instruction->withOperands(
                self::replaceAccessorProperty($instruction->operand1, $property),
                self::replaceAccessorProperty($instruction->operand2, $property),
                self::replaceAccessorProperty($instruction->result, $property),
            ),
            $original->instructions(),
        ));
        // The source method is unique to this accessor and carries the
        // accessor's public signature. Assemble its direct member bytecode
        // there, then clone that body into the target. This avoids sharing a
        // mutable placeholder op-array between separately generated methods.
        \Communism\Internals\Needle\Assembler::write($rewritten, $source->opArray());
        $source->installInto(Zendful::class($className), $methodName);
    }

    private static function replaceAccessorProperty(\Communism\Internals\Needle\Operand $operand, string $property): \Communism\Internals\Needle\Operand
    {
        return $operand->kind === \Communism\Internals\Needle\Operand::CONSTANT
            && in_array($operand->value, ['accessorPlaceholder', 'accessorStaticPlaceholder'], true)
            ? $operand->withValue($property)
            : $operand;
    }

    private static function methodVisibility(\ReflectionMethod $method): string
    {
        return $method->isPrivate() ? 'private' : ($method->isProtected() ? 'protected' : 'public');
    }

    /** @return non-empty-string */
    /** @param \ReflectionClass<object> $class */
    private static function invokerMethod(\ReflectionClass $class, \ReflectionMethod $method, ?string $target): string
    {
        $name = $target;
        if ($name === null) {
            $candidates = [$method->getName()];
            foreach (['call', 'invoke'] as $prefix) {
                if (str_starts_with($method->getName(), $prefix)) {
                    $candidates[] = lcfirst(substr($method->getName(), strlen($prefix)));
                }
            }
            foreach ($candidates as $candidate) {
                if ($candidate !== '' && strcasecmp($candidate, $method->getName()) !== 0 && $class->hasMethod($candidate)) {
                    $name = $candidate;
                    break;
                }
            }
        }
        if ($name === null || $name === '' || !$class->hasMethod($name)) {
            throw new InvalidArgumentException(sprintf('Invoker %s::%s targets a missing method', $class->getName(), $method->getName()));
        }
        if (strcasecmp($name, $method->getName()) === 0) {
            throw new InvalidArgumentException(sprintf('Invoker %s::%s cannot invoke itself', $class->getName(), $method->getName()));
        }

        return $name;
    }

    /** @param class-string $className */
    private static function setPropertyMutability(string $className, string $property, bool $mutable, bool $final): void
    {
        try {
            $propertyInfo = Zendful::property($className, $property);
        } catch (\InvalidArgumentException) {
            throw new InvalidArgumentException(sprintf('Could not find shadowed property %s::$%s', $className, $property));
        }

        if ($mutable) {
            $propertyInfo->setReadonly(false);
        }
        if ($final) {
            $propertyInfo->setReadonly(true);
        }
    }

    /** @param class-string $className */
    private static function setMethodMutability(string $className, string $method, bool $mutable, bool $final): void
    {
        if ($method === '') {
            throw new InvalidArgumentException(sprintf('Could not find shadowed method %s::%s', $className, $method));
        }
        try {
            $function = Zendful::method($className, $method);
        } catch (\InvalidArgumentException) {
            throw new InvalidArgumentException(sprintf('Could not find shadowed method %s::%s', $className, $method));
        }

        if ($mutable) {
            $function->setFinal(false);
        }
        if ($final) {
            $function->setFinal(true);
        }
    }

    private static function groupForMethod(\ReflectionMethod $method): ?Group
    {
        $groups = $method->getAttributes(Group::class);
        if (count($groups) > 1) {
            throw new InvalidArgumentException(sprintf('Trait method %s may have only one #[Group]', $method->getName()));
        }

        return $groups === [] ? null : $groups[0]->newInstance();
    }

    private static function attachGroup(Inject $inject, ?Group $group): Inject
    {
        if ($group === null || $inject->group !== null) {
            return $inject;
        }

        return new Inject(
            $inject->method,
            $inject->at,
            $inject->cancellable,
            $inject->require,
            $inject->expect,
            $inject->allow,
            $inject->slice,
            $inject->argumentIndex,
            $group,
            $inject->constantType,
            $inject->mode,
            $inject->locals,
            $inject->variableIndex,
            $inject->variableType,
            $inject->nullValue,
            $inject->variableArgsOnly,
            $inject->id,
        );
    }

    private static function handlerVariableType(\ReflectionMethod $method): ?string
    {
        $parameters = $method->getParameters();
        if ($parameters === []) {
            return null;
        }
        $type = $parameters[0]->getType() ?? null;

        if (!$type instanceof \ReflectionType || $type->allowsNull()) {
            return null;
        }
        $types = [(string) $type];
        $coerce = $method->getAttributes(Coerce::class) !== []
            || $parameters[0]->getAttributes(Coerce::class) !== [];
        if ($coerce) {
            foreach (explode('|', (string) $type) as $candidate) {
                $candidate = trim($candidate);
                if (in_array($candidate, ['int', 'float'], true)) {
                    $types[] = 'int';
                    $types[] = 'float';
                }
                if ($candidate === 'string') {
                    $types[] = 'bool';
                    $types[] = 'int';
                    $types[] = 'float';
                }
                if ($candidate === 'bool') {
                    $types[] = 'string';
                    $types[] = 'int';
                    $types[] = 'float';
                }
                if (in_array($candidate, ['array', 'iterable'], true)) {
                    $types[] = 'array';
                    $types[] = 'iterable';
                }
            }
        }

        return implode('|', array_values(array_unique($types)));
    }

    /**
     * Mutation of runtime metadata can leave JIT assumptions stale.
     *
     * @param string $function
     */
    public static function disableJitForFunction(string $function): void
    {
        Zendful::function($function)->disableJit();
    }

    /**
     * Disables JIT for a specific method to stop opcache from breaking things
     *
     * @param class-string $className
     * @param non-empty-string $method
     */
    public static function disableJitForMethod(string $className, string $method): void
    {
        Zendful::method($className, $method)->disableJit();
    }

    /**
     * Disable JIT for a specific class to prevent opcache from breaking things
     *
     * @param class-string $className
     */
    public static function disableJitForClass(string $className): void
    {
        Zendful::class($className)->disableJit();
    }

}
