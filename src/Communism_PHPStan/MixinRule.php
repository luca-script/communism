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
 * File: MixinRule.php                                                        *
 * Consumer: Users                                                            *
 * Purpose: PHPStan validation for Mixin target declarations.                 *
 *============================================================================*/

declare(strict_types=1);

namespace Communism_PHPStan;

use Communism\Mixin\At;
use Communism\Mixin\Accessor;
use Communism\Mixin\Inject;
use Communism\Mixin\Invoker;
use Communism\Mixin\Mixin;
use Communism\Mixin\ModifyArg;
use Communism\Mixin\ModifyArgs;
use Communism\Mixin\ModifyConstant;
use Communism\Mixin\ModifyVariable;
use Communism\Mixin\Overwrite;
use Communism\Mixin\Pseudo;
use Communism\Mixin\Redirect;
use Communism\Mixin\Shadow;
use Communism\Internals\Needle\Matcher;
use PhpParser\Node;
use PhpParser\Node\Attribute as AttributeNode;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\DNumber;
use PhpParser\Node\Scalar\LNumber;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Checks Mixin target declarations and target members using PHPStan's static
 * reflection provider. No target file is included or executed by this rule.
 *
 * @implements Rule<Class_>
 */
final class MixinRule implements Rule
{
    public function __construct(
        private ReflectionProvider $reflectionProvider,
        private BytecodeIndex $bytecodeIndex,
    ) {}

    public function getNodeType(): string
    {
        return Class_::class;
    }

    /**
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $mixin = $this->findAttribute($node->attrGroups, Mixin::class, $scope);
        if ($mixin === null) {
            return [];
        }

        $errors = [];
        if (!$node->isFinal()) {
            $errors[] = $this->error($node, 'Mixin declarations must be final classes');
        }
        $constructor = null;
        foreach ($node->stmts ?? [] as $statement) {
            if ($statement instanceof ClassMethod && $statement->name->toString() === '__construct') {
                $constructor = $statement;
                break;
            }
        }
        if ($constructor === null || !$constructor->isPrivate() || $constructor->params !== []) {
            $errors[] = $this->error($mixin, 'Mixin declarations must have a private zero-argument constructor');
        }

        $targets = [];
        foreach ($mixin->args as $argument) {
            $targetName = $this->stringValue($argument->value, $scope);
            if ($targetName === null) {
                return [...$errors, $this->error($argument, 'Mixin targets must be class names or string literals')];
            }

            $targets[] = $targetName;
        }

        if ($targets === []) {
            return [...$errors, $this->error($mixin, 'Mixin must declare at least one target')];
        }

        $pseudo = $this->findAttribute($node->attrGroups, Pseudo::class, $scope) !== null;
        foreach ($targets as $target) {
            if ($target === '*') {
                continue;
            }

            if (!$this->reflectionProvider->hasClass($target)) {
                if (!$pseudo) {
                    $errors[] = $this->error($mixin, sprintf('Mixin target %s does not exist', $target));
                }

                continue;
            }

            $reflection = $this->reflectionProvider->getClass($target);
            $errors = [...$errors, ...$this->checkMembers($node, $scope, $reflection)];
        }

        return $errors;
    }

    /**
     * @param array<Node\AttributeGroup> $groups
     */
    private function findAttribute(array $groups, string $expected, Scope $scope): ?AttributeNode
    {
        foreach ($groups as $group) {
            foreach ($group->attrs as $attribute) {
                if ($scope->resolveName($attribute->name) === $expected) {
                    return $attribute;
                }
            }
        }

        return null;
    }

    /**
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    private function checkMembers(Class_ $mixin, Scope $scope, ClassReflection $target): array
    {
        $errors = [];
        foreach ($mixin->stmts ?? [] as $statement) {
            if ($statement instanceof ClassMethod) {
                $errors = [...$errors, ...$this->checkMethod($statement, $scope, $target)];
                continue;
            }

            if (!$statement instanceof Property) {
                continue;
            }

            $shadow = $this->findAttribute($statement->attrGroups, Shadow::class, $scope);
            if ($shadow === null) {
                continue;
            }

            $name = $this->stringArgument($shadow, 0, $scope);
            foreach ($statement->props as $property) {
                $propertyName = $name ?? $property->name->toString();
                if (!$target->hasProperty($propertyName)) {
                    $errors[] = $this->error($shadow, sprintf(
                        'Mixin shadows missing property %s::$%s',
                        $target->getName(),
                        $propertyName,
                    ));
                }
            }
        }

        return $errors;
    }

    /**
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    private function checkMethod(ClassMethod $method, Scope $scope, ClassReflection $target): array
    {
        $errors = [];
        $methodName = $method->name->toString();
        $attributes = [
            Inject::class,
            ModifyArg::class,
            ModifyArgs::class,
            ModifyConstant::class,
            ModifyVariable::class,
            Redirect::class,
        ];

        foreach ($attributes as $attributeName) {
            foreach ($this->attributes($method->attrGroups, $attributeName, $scope) as $attribute) {
                $targetMethod = $this->stringArgument($attribute, 0, $scope);
                if ($targetMethod !== null && !$target->hasMethod($targetMethod)) {
                    $errors[] = $this->error($attribute, sprintf(
                        'Mixin injection targets missing method %s::%s',
                        $target->getName(),
                        $targetMethod,
                    ));
                }

                if ($targetMethod !== null && $target->hasMethod($targetMethod)) {
                    $errors = [...$errors, ...$this->checkBytecodeTarget($attribute, $targetMethod, $scope, $target)];
                }
            }
        }

        foreach ($this->attributes($method->attrGroups, Shadow::class, $scope) as $attribute) {
            $targetMethod = $this->stringArgument($attribute, 0, $scope) ?? $methodName;
            if (!$target->hasMethod($targetMethod)) {
                $errors[] = $this->error($attribute, sprintf(
                    'Mixin shadows missing method %s::%s',
                    $target->getName(),
                    $targetMethod,
                ));
            }
        }

        foreach ($this->attributes($method->attrGroups, Overwrite::class, $scope) as $attribute) {
            $targetMethod = $this->stringArgument($attribute, 0, $scope) ?? $methodName;
            if (!$target->hasMethod($targetMethod)) {
                $errors[] = $this->error($attribute, sprintf(
                    'Mixin overwrite targets missing method %s::%s',
                    $target->getName(),
                    $targetMethod,
                ));
            }
        }

        foreach ($this->attributes($method->attrGroups, Accessor::class, $scope) as $attribute) {
            $property = $this->stringArgument($attribute, 0, $scope) ?? $this->accessorProperty($methodName);
            if ($property !== null && !$target->hasProperty($property)) {
                $errors[] = $this->error($attribute, sprintf(
                    'Mixin accessor targets missing property %s::$%s',
                    $target->getName(),
                    $property,
                ));
            }
        }

        foreach ($this->attributes($method->attrGroups, Invoker::class, $scope) as $attribute) {
            $targetMethod = $this->stringArgument($attribute, 0, $scope) ?? $this->invokerMethod($methodName);
            if ($targetMethod !== null && !$target->hasMethod($targetMethod)) {
                $errors[] = $this->error($attribute, sprintf(
                    'Mixin invoker targets missing method %s::%s',
                    $target->getName(),
                    $targetMethod,
                ));
            }
        }

        return $errors;
    }

    /**
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    private function checkBytecodeTarget(
        AttributeNode $attribute,
        string $targetMethod,
        Scope $scope,
        ClassReflection $target,
    ): array {
        $at = $this->atArgument($attribute, $scope);
        if ($at === null) {
            return [];
        }
        $at = $this->effectiveAt($attribute, $at, $scope);

        try {
            $compiledMethod = $this->bytecodeIndex->method($target, $targetMethod, $scope);
        } catch (\Throwable $exception) {
            return [$this->error(
                $attribute,
                sprintf('Could not inspect bytecode for %s: %s', $target->getName(), $exception->getMessage()),
            )];
        }

        if ($compiledMethod === null) {
            return [$this->error(
                $attribute,
                sprintf('Bytecode target method %s::%s has no transformable body', $target->getName(), $targetMethod),
            )];
        }

        try {
            $matches = Matcher::find(
                $compiledMethod->body,
                $at,
                $this->argumentIndex($attribute, $scope),
                constantType: $this->constantType($attribute, $scope),
            );
        } catch (\Throwable $exception) {
            return [$this->error(
                $attribute,
                sprintf('Could not resolve bytecode target %s::%s: %s', $target->getName(), $targetMethod, $exception->getMessage()),
            )];
        }

        if ($matches !== [] || $this->allowsNoMatch($attribute, $scope)) {
            return [];
        }

        return [$this->error(
            $attribute,
            sprintf('Bytecode injection point %s was not found in %s::%s', $at->description(), $target->getName(), $targetMethod),
        )];
    }

    private function atArgument(AttributeNode $attribute, Scope $scope): ?At
    {
        $argument = $attribute->args[1] ?? null;
        if ($argument === null || !$argument->value instanceof New_) {
            return null;
        }

        $new = $argument->value;
        if (!$new->class instanceof Name || $scope->resolveName($new->class) !== At::class) {
            return null;
        }

        $values = [];
        foreach ($new->args as $index => $newArgument) {
            if (!$newArgument instanceof \PhpParser\Node\Arg) {
                return null;
            }

            $value = $this->constantValue($newArgument->value, $scope);
            if ($value === null && !$this->isNull($newArgument->value)) {
                return null;
            }

            $key = $newArgument->name?->toString() ?? $index;
            $values[$key] = $value;
        }

        $value = $values['value'] ?? $values[0] ?? null;
        $ordinal = $values['ordinal'] ?? $values[2] ?? -1;
        $shift = $values['shift'] ?? $values[3] ?? 'BEFORE';
        $by = $values['by'] ?? $values[4] ?? 0;
        $action = $values['action'] ?? $values[5] ?? '';
        $opcode = $values['opcode'] ?? $values[6] ?? '';
        if (!is_string($value) || !is_int($ordinal) || !is_string($shift) || !is_int($by) || !is_string($action) || !is_string($opcode)) {
            return null;
        }

        return new At(
            $value,
            $values['target'] ?? $values[1] ?? '',
            $ordinal,
            $shift,
            $by,
            $action,
            $opcode,
        );
    }

    private function argumentIndex(AttributeNode $attribute, Scope $scope): ?int
    {
        if ($scope->resolveName($attribute->name) !== ModifyArg::class) {
            return null;
        }

        $value = $this->constantValue($attribute->args[2]->value ?? new String_(''), $scope);

        return is_int($value) ? $value : null;
    }

    private function effectiveAt(AttributeNode $attribute, At $at, Scope $scope): At
    {
        $attributeName = $scope->resolveName($attribute->name);
        if ($attributeName === ModifyConstant::class) {
            $constant = $this->attributeArgument($attribute, 2, 'constant', $scope);
            $type = $this->constantType($attribute, $scope);
            $target = $constant === null && $type !== 'null' ? '' : $constant;

            return new At($at->value, $target, $at->ordinal, $at->shift, $at->by, opcode: $at->opcode);
        }

        if ($attributeName === ModifyVariable::class) {
            $name = $this->attributeArgument($attribute, 2, 'name', $scope);
            $ordinal = $this->attributeArgument($attribute, 3, 'ordinal', $scope);
            if (!is_int($ordinal) || $ordinal < 0) {
                $ordinal = $at->ordinal;
            }

            return new At($at->value, is_string($name) ? $name : '', $ordinal, $at->shift, $at->by, opcode: $at->opcode);
        }

        return $at;
    }

    private function constantType(AttributeNode $attribute, Scope $scope): ?string
    {
        $attributeName = $scope->resolveName($attribute->name);
        $value = match ($attributeName) {
            ModifyConstant::class => $this->attributeArgument($attribute, 3, 'type', $scope),
            Inject::class => $this->attributeArgument($attribute, 9, 'constantType', $scope),
            default => null,
        };

        return is_string($value) ? $value : null;
    }

    private function allowsNoMatch(AttributeNode $attribute, Scope $scope): bool
    {
        $attributeName = $scope->resolveName($attribute->name);
        [$requireIndex, $expectIndex] = match ($attributeName) {
            Inject::class => [3, 4],
            ModifyVariable::class => [5, 6],
            default => [-1, -1],
        };
        if ($requireIndex < 0) {
            return false;
        }

        return $this->attributeArgument($attribute, $requireIndex, 'require', $scope) === 0
            || $this->attributeArgument($attribute, $expectIndex, 'expect', $scope) === 0;
    }

    private function attributeArgument(AttributeNode $attribute, int $index, string $name, Scope $scope): mixed
    {
        $argument = $attribute->args[$index] ?? null;
        foreach ($attribute->args as $candidate) {
            if ($candidate->name?->toString() === $name) {
                $argument = $candidate;
                break;
            }
        }

        return $argument === null ? null : $this->constantValue($argument->value, $scope);
    }

    private function constantValue(Expr $expression, Scope $scope): mixed
    {
        if ($expression instanceof String_) {
            return $expression->value;
        }
        if ($expression instanceof LNumber) {
            return $expression->value;
        }
        if ($expression instanceof DNumber) {
            return $expression->value;
        }
        if ($expression instanceof ConstFetch) {
            return match (strtolower($scope->resolveName($expression->name))) {
                'true' => true,
                'false' => false,
                'null' => null,
                default => null,
            };
        }
        if ($expression instanceof Array_) {
            $values = [];
            foreach ($expression->items as $index => $item) {
                $key = $item->key === null ? $index : $this->constantValue($item->key, $scope);
                if (!is_int($key) && !is_string($key)) {
                    return null;
                }
                $values[$key] = $this->constantValue($item->value, $scope);
            }

            return $values;
        }

        return null;
    }

    private function isNull(Expr $expression): bool
    {
        return $expression instanceof ConstFetch && strtolower($expression->name->toString()) === 'null';
    }

    /**
     * @param array<Node\AttributeGroup> $groups
     * @return list<AttributeNode>
     */
    private function attributes(array $groups, string $expected, Scope $scope): array
    {
        $attributes = [];
        foreach ($groups as $group) {
            foreach ($group->attrs as $attribute) {
                if ($scope->resolveName($attribute->name) === $expected) {
                    $attributes[] = $attribute;
                }
            }
        }

        return $attributes;
    }

    private function stringArgument(AttributeNode $attribute, int $index, Scope $scope): ?string
    {
        $argument = $attribute->args[$index] ?? null;

        return $argument === null ? null : $this->stringValue($argument->value, $scope);
    }

    private function stringValue(Expr $expression, Scope $scope): ?string
    {
        if ($expression instanceof String_) {
            return $expression->value;
        }

        if (
            $expression instanceof ClassConstFetch
            && $expression->class instanceof Name
            && $expression->name instanceof Identifier
            && strtolower($expression->name->toString()) === 'class'
        ) {
            return $scope->resolveName($expression->class);
        }

        return null;
    }

    private function accessorProperty(string $method): ?string
    {
        foreach (['get', 'set', 'is'] as $prefix) {
            if (str_starts_with($method, $prefix) && strlen($method) > strlen($prefix)) {
                return lcfirst(substr($method, strlen($prefix)));
            }
        }

        return null;
    }

    private function invokerMethod(string $method): ?string
    {
        foreach (['call', 'invoke'] as $prefix) {
            if (str_starts_with($method, $prefix) && strlen($method) > strlen($prefix)) {
                return lcfirst(substr($method, strlen($prefix)));
            }
        }

        return null;
    }

    private function error(Node $node, string $message): \PHPStan\Rules\IdentifierRuleError
    {
        return RuleErrorBuilder::message($message)
            ->identifier('communism.mixin.target')
            ->line($node->getStartLine())
            ->build();
    }
}
