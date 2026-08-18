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
use Communism\Mixin\At;
use Communism\Mixin\Final_;
use Communism\Mixin\Group;
use Communism\Mixin\Inject;
use Communism\Mixin\Invoker;
use Communism\Mixin\Mixin;
use Communism\Mixin\ModifyArg;
use Communism\Mixin\ModifyArgs;
use Communism\Mixin\ModifyConstant;
use Communism\Mixin\ModifyVariable;
use Communism\Mixin\Mutable;
use Communism\Mixin\Overwrite;
use Communism\Mixin\Pseudo;
use Communism\Mixin\Redirect;
use Communism\Mixin\Shadow;
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
    /**
     * Inject selected methods from a non-instantiable mixin class into a class.
     *
     * @param string $className
     * @param class-string $traitName
     * @param list<non-empty-string>|null $methods
     */
    public static function injectMixinMethods(string $className, string $traitName, ?array $methods = null): void
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
        if (count($mixins) !== 1) {
            throw new InvalidArgumentException(sprintf('Mixin %s must have exactly one #[Mixin] declaration', $traitName));
        }

        $targetDeclaration = $mixins[0];
        if (!$targetDeclaration->newInstance()->allows($className)) {
            throw new InvalidArgumentException(sprintf('Mixin %s is not allowed to be injected into %s', $traitName, $className));
        }

        $availableMethods = [];
        /** @var array<string, string> $surrogates */
        $surrogates = [];
        foreach ($mixin->getMethods() as $method) {
            if ($method->getName() === '__construct') {
                continue;
            }
            $shadow = $method->getAttributes(Shadow::class);
            $overwrite = $method->getAttributes(Overwrite::class);
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
                // @codeCoverageIgnoreStart
                throw new InvalidArgumentException(sprintf('Trait method %s::%s may have only one #[Surrogate]', $traitName, $method->getName()));
                // @codeCoverageIgnoreEnd
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
                // @codeCoverageIgnoreStart
                throw new InvalidArgumentException(sprintf('Trait method %s::%s may have only one #[Accessor] or #[Invoker]', $traitName, $method->getName()));
                // @codeCoverageIgnoreEnd
            }
            if ($accessor !== [] && $invoker !== []) {
                throw new InvalidArgumentException(sprintf('Trait method %s::%s cannot combine #[Accessor] and #[Invoker]', $traitName, $method->getName()));
            }
            $generated = array_merge($accessor, $invoker);
            if ($generated !== [] && ($shadow !== [] || $overwrite !== [] || $injection !== [] || $method->getAttributes(Unique::class) !== [])) {
                throw new InvalidArgumentException(sprintf('Trait method %s::%s cannot combine a generated member annotation with another method annotation', $traitName, $method->getName()));
            }
            if (count($final) > 1 || count($mutable) > 1) {
                // @codeCoverageIgnoreStart
                throw new InvalidArgumentException(sprintf('Trait method %s::%s may have only one #[Final] and #[Mutable]', $traitName, $method->getName()));
                // @codeCoverageIgnoreEnd
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
                // @codeCoverageIgnoreStart
                throw new InvalidArgumentException(sprintf('Trait method %s::%s may have only one #[Overwrite]', $traitName, $method->getName()));
                // @codeCoverageIgnoreEnd
            }
            if (count($shadow) > 1) {
                // @codeCoverageIgnoreStart
                throw new InvalidArgumentException(sprintf('Trait method %s::%s may have only one #[Shadow]', $traitName, $method->getName()));
                // @codeCoverageIgnoreEnd
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

            $shadowTarget = $shadow[0]->newInstance()->target ?? $methodName;
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
                // @codeCoverageIgnoreStart
                throw new InvalidArgumentException(sprintf('Trait method %s::%s cannot combine #[Final] and #[Mutable]', $traitName, $methodName));
                // @codeCoverageIgnoreEnd
            }
        }
        foreach ($methodsToCompose as $methodName) {
            $overwrite = $availableMethods[$methodName]->getAttributes(Overwrite::class);
            $targetMethod = $overwrite === [] ? $methodName : ($overwrite[0]->newInstance()->method ?? $methodName);
            if ($targetMethod === '') {
                throw new InvalidArgumentException('Injected method names must not be empty');
            }
            $hasTargetMethod = $class->hasMethod($targetMethod);

            $unique = $availableMethods[$methodName]->getAttributes(Unique::class) !== [];
            if ($overwrite === [] && $hasTargetMethod && !$unique) {
                throw new InvalidArgumentException(sprintf('Class %s already has method %s', $className, $targetMethod));
            }

            if ($overwrite !== [] && !$hasTargetMethod) {
                throw new InvalidArgumentException(sprintf('Class %s has no method %s to override', $className, $targetMethod));
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
            if ($traitProperty->getAttributes(Shadow::class) === []) {
                throw new InvalidArgumentException(sprintf('Trait property %s::$%s must be marked with #[Shadow]', $traitName, $traitProperty->getName()));
            }

            try {
                $classProperty = $class->getProperty($traitProperty->getName());
            } catch (\ReflectionException) {
                throw new InvalidArgumentException(sprintf('Class %s is missing mixin property %s', $className, $traitProperty->getName()));
            }

            if (\strval($traitProperty->getType()) !== \strval($classProperty->getType())
                || $traitProperty->isStatic() !== $classProperty->isStatic()
                || $traitProperty->isReadOnly() !== $classProperty->isReadOnly()
            ) {
                throw new InvalidArgumentException(sprintf('Trait property %s does not match class %s::$%s', $traitName, $className, $traitProperty->getName()));
            }
            $propertyFinal = $traitProperty->getAttributes(Final_::class);
            $propertyMutable = $traitProperty->getAttributes(Mutable::class);
            if (count($propertyFinal) > 1 || count($propertyMutable) > 1) {
                // @codeCoverageIgnoreStart
                throw new InvalidArgumentException(sprintf('Trait property %s::$%s may have only one #[Final] and #[Mutable]', $traitName, $traitProperty->getName()));
                // @codeCoverageIgnoreEnd
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
                if (!$class->hasMethod($inject->method)) {
                    throw new InvalidArgumentException(sprintf(
                        'Class %s has no method %s for injection from %s::%s',
                        $className,
                        $inject->method,
                        $traitName,
                        $method->getName(),
                    ));
                }

                $injectionsByMethod[$inject->method][] = [
                    'inject' => $inject,
                    'handler' => $method->getName(),
                    'surrogate' => $surrogates[$method->getName()] ?? null,
                ];
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
                $constantTarget = $modify->constant === null && $modify->type !== 'null' ? '' : $modify->constant;
                $at = new At($modify->at->value, $constantTarget, $modify->at->ordinal, $modify->at->shift, $modify->at->by, opcode: $modify->at->opcode);
                $inject = self::attachGroup(new Inject($modify->method, $at, true, null, null, null, null, null, null, $modify->type), $group);
                if (!$class->hasMethod($inject->method)) {
                    throw new InvalidArgumentException(sprintf(
                        'Class %s has no method %s for injection from %s::%s',
                        $className,
                        $inject->method,
                        $traitName,
                        $method->getName(),
                    ));
                }

                $injectionsByMethod[$inject->method][] = [
                    'inject' => $inject,
                    'handler' => $method->getName(),
                    'surrogate' => null,
                ];
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
                $ordinal = $modify->ordinal >= 0 ? $modify->ordinal : $modify->at->ordinal;
                $at = new At($modify->at->value, $modify->name ?? '', $ordinal, $modify->at->shift, $modify->at->by, opcode: $modify->at->opcode);
                $inject = self::attachGroup(new Inject(
                    $modify->method,
                    $at,
                    require: $modify->require,
                    expect: $modify->expect,
                    allow: $modify->allow,
                    variableIndex: $modify->index,
                ), $group);
                if (!$class->hasMethod($inject->method)) {
                    throw new InvalidArgumentException(sprintf(
                        'Class %s has no method %s for injection from %s::%s',
                        $className,
                        $inject->method,
                        $traitName,
                        $method->getName(),
                    ));
                }

                $injectionsByMethod[$inject->method][] = [
                    'inject' => $inject,
                    'handler' => $method->getName(),
                    'surrogate' => null,
                ];
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
                $at = new At($modify->at->value, $modify->at->target, $modify->at->ordinal, $modify->at->shift, $modify->at->by, opcode: $modify->at->opcode);
                $inject = self::attachGroup(new Inject($modify->method, $at, true, null, null, null, null, $modify->index), $group);
                if (!$class->hasMethod($inject->method)) {
                    throw new InvalidArgumentException(sprintf('Class %s has no method %s for injection from %s::%s', $className, $inject->method, $traitName, $method->getName()));
                }
                $injectionsByMethod[$inject->method][] = ['inject' => $inject, 'handler' => $method->getName(), 'surrogate' => null];
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
                $at = new At($modify->at->value, $modify->at->target, $modify->at->ordinal, $modify->at->shift, $modify->at->by, 'replace', $modify->at->opcode);
                $inject = self::attachGroup(new Inject($modify->method, $at, true, mode: 'args'), $group);
                if (!$class->hasMethod($inject->method)) {
                    throw new InvalidArgumentException(sprintf('Class %s has no method %s for injection from %s::%s', $className, $inject->method, $traitName, $method->getName()));
                }
                $injectionsByMethod[$inject->method][] = ['inject' => $inject, 'handler' => $method->getName(), 'surrogate' => null];
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
                $inject = self::attachGroup(new Inject($redirect->method, new At($redirect->at->value, $redirect->at->target, $redirect->at->ordinal, $redirect->at->shift, $redirect->at->by, 'replace', $redirect->at->opcode)), $group);
                if (!$class->hasMethod($inject->method)) {
                    throw new InvalidArgumentException(sprintf('Class %s has no method %s for injection from %s::%s', $className, $inject->method, $traitName, $method->getName()));
                }
                $injectionsByMethod[$inject->method][] = ['inject' => $inject, 'handler' => $method->getName(), 'surrogate' => null];
            }
        }

        /** @var array<string, \Communism\Internals\Needle\MethodBody> $rewrittenBodies */
        $rewrittenBodies = [];
        foreach ($injectionsByMethod as $targetMethod => $injections) {
            $body = \Communism\Internals\Needle\Decompiler::decompile($className . '::' . $targetMethod);
            $definitions = [];
            $handlers = [];
            $surrogateHandlers = [];
            foreach ($injections as $injection) {
                $definitions[] = $injection['inject'];
                $handlers[spl_object_id($injection['inject'])] = \Communism\Internals\Needle\Decompiler::decompile($traitName . '::' . $injection['handler']);
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
            );
        }

        foreach ($mixin->getProperties() as $traitProperty) {
            $propertyFinal = $traitProperty->getAttributes(Final_::class) !== [];
            $propertyMutable = $traitProperty->getAttributes(Mutable::class) !== [];
            if (!$propertyFinal && !$propertyMutable) {
                continue;
            }

            self::setPropertyMutability($className, $traitProperty->getName(), $propertyMutable, $propertyFinal);
        }

        foreach ($methodNames as $methodName) {
            $method = $availableMethods[$methodName];
            $accessor = $method->getAttributes(Accessor::class);
            $invoker = $method->getAttributes(Invoker::class);
            if ($accessor === [] && $invoker === []) {
                continue;
            }

            $generated = $accessor !== [] ? $accessor[0]->newInstance() : $invoker[0]->newInstance();
            $kind = $accessor !== [] ? 'accessor' : 'invoker';
            $templateName = $kind === 'accessor'
                ? ($method->isStatic() ? 'accessorStatic' : 'accessor')
                : ($method->isStatic() ? 'invokerStatic' : 'invoker');
            $sourceMethod = Zendful::method($traitName, $methodName);
            class_exists(AccessorInvokerTemplates::class);
            $templateMethod = Zendful::method(AccessorInvokerTemplates::class, $templateName);
            if (!$sourceMethod->exists() || !$templateMethod->exists()) {
                // @codeCoverageIgnoreStart
                throw new InvalidArgumentException('Generated accessor/invoker template was not found');
                // @codeCoverageIgnoreEnd
            }
            $sourceMethod->installGeneratedInto(Zendful::class($className), $templateMethod, $methodName);

            if ($accessor !== []) {
                $property = self::accessorProperty($className, $method, $generated->target);
                AccessorInvokerRuntime::registerAccessor($className, $methodName, $property, self::accessorIsSetter($method));
            } else {
                $targetMethod = self::invokerMethod($class, $method, $generated->target);
                AccessorInvokerRuntime::registerInvoker($className, $methodName, $targetMethod);
            }
        }

        foreach ($methodsToCompose as $methodName) {
            if ($availableMethods[$methodName]->getAttributes(Accessor::class) !== [] || $availableMethods[$methodName]->getAttributes(Invoker::class) !== []) {
                // @codeCoverageIgnoreStart
                continue;
                // @codeCoverageIgnoreEnd
            }
            $sourceMethod = Zendful::method($traitName, $methodName);
            if (!$sourceMethod->exists()) {
                // @codeCoverageIgnoreStart
                throw new InvalidArgumentException(sprintf('Could not find mixin method %s::%s', $traitName, $methodName));
                // @codeCoverageIgnoreEnd
            }

            $overwrite = $availableMethods[$methodName]->getAttributes(Overwrite::class);
            $targetMethod = $overwrite === [] ? $methodName : ($overwrite[0]->newInstance()->method ?? $methodName);
            $methodLower = mb_strtolower($targetMethod);
            if ($targetMethod === '') {
                // @codeCoverageIgnoreStart
                throw new InvalidArgumentException('Injected method names must not be empty');
                // @codeCoverageIgnoreEnd
            }

            if ($overwrite !== []) {
                $deletedName = '';
                for ($deleted = 0; $deletedName === ''; $deleted++) {
                    $candidate = '__deleted' . $deleted;
                    if (!method_exists($className, $candidate)) {
                        $deletedName = $candidate;
                    }
                }
                Zendful::method($className, $targetMethod)->renameTo($deletedName);
            }

            if ($overwrite === [] && method_exists($className, $targetMethod)) {
                $uniqueName = '__unique_' . mb_strtolower($traitName) . '_' . $targetMethod;
                $uniqueName = preg_replace('/[^a-z0-9_]+/i', '_', $uniqueName) ?? ('__unique_' . $targetMethod);
                for ($suffix = 0; method_exists($className, $uniqueName); $suffix++) {
                    $uniqueName = '__unique_' . $targetMethod . '_' . $suffix;
                }
                $targetMethod = $uniqueName;
                $methodLower = mb_strtolower($targetMethod);
            }

            $sourceMethod->installInto(
                Zendful::class($className),
                $targetMethod,
                $availableMethods[$methodName]->getAttributes(Final_::class) !== [],
            );
        }

        foreach ($rewrittenBodies as $targetMethod => $rewrittenBody) {
            if ($targetMethod === '') {
                // @codeCoverageIgnoreStart
                throw new InvalidArgumentException('Injected target method names must not be empty');
                // @codeCoverageIgnoreEnd
            }

            if (!method_exists($className, $targetMethod)) {
                // @codeCoverageIgnoreStart
                throw new InvalidArgumentException(sprintf('Could not find injected target method %s::%s', $className, $targetMethod));
                // @codeCoverageIgnoreEnd
            }

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

            $shadowTarget = $shadow[0]->newInstance()->target ?? $methodName;
            self::setMethodMutability($className, $shadowTarget, $mutable, $final);
        }

        self::disableJitForClass($className);
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
        $propertyInfo = Zendful::property($className, $property);
        if (!$propertyInfo->exists()) {
            // @codeCoverageIgnoreStart
            throw new InvalidArgumentException(sprintf('Could not find shadowed property %s::$%s', $className, $property));
            // @codeCoverageIgnoreEnd
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
        $function = Zendful::method($className, $method);
        if (!$function->exists()) {
            // @codeCoverageIgnoreStart
            throw new InvalidArgumentException(sprintf('Could not find shadowed method %s::%s', $className, $method));
            // @codeCoverageIgnoreEnd
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
            // @codeCoverageIgnoreStart
            throw new InvalidArgumentException(sprintf('Trait method %s may have only one #[Group]', $method->getName()));
            // @codeCoverageIgnoreEnd
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
        );
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
