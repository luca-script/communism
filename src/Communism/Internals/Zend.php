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
 * Purpose: Unsafe access to PHP internals; NOT FOR PUBLIC CONSUMPTION! It is *
 *          very easy to crash PHP this way.                                  *
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
use FFI;
use InvalidArgumentException;
use RuntimeException;

use function sprintf;

use const PHP_VERSION_ID;
use const PHP_OS_FAMILY;
use const ZEND_THREAD_SAFE;

/**
 * Access to the underlying PHP runtime
 * @internal Its easily possible to crash your PHP by using this class
 */
final class Zend
{
    /** @var array<string, true> */
    private static array $blacklistedFunctions = [];

    /** @var array<string, true> */
    private static array $blacklistedMethods = [];

    /** @var array<string, true> */
    private static array $blacklistedClasses = [];

    // Class, method, property, and class-constant flags from <PHP>/Zend/zend_compile.h.
    // These mirror the internals names so the bit twiddling below stays readable.

    // START OF EXTERNAL COPYRIGHT
    // Copyright © 1999–2026, The PHP Group and Contributors.
    // Copyright © 1999–2026, Zend Technologies Ltd., a subsidiary company of Perforce Software, Inc.

    /**
     * Applies to: methods, properties, constants
     */
    public const ZEND_ACC_PUBLIC = (1 << 0);

    /**
     * Applies to: methods, properties, constants
     */
    public const ZEND_ACC_PROTECTED = (1 << 1);

    /**
     * Applies to: methods, properties, constants
     */
    public const ZEND_ACC_PRIVATE = (1 << 2);

    /**
     * Applies to: methods, properties
     */
    public const ZEND_ACC_CHANGED = (1 << 3);

    /**
     * Applies to: methods, properties, constants
     */
    public const ZEND_ACC_STATIC = (1 << 4);

    /**
     * Applies to: classes, methods, properties, constants
     */
    public const ZEND_ACC_FINAL = (1 << 5);

    /**
     * Applies to: classes, methods, properties
     */
    public const ZEND_ACC_ABSTRACT = (1 << 6);

    /**
     * Applies to: classes
     */
    public const ZEND_ACC_EXPLICIT_ABSTRACT_CLASS = (1 << 6);

    /**
     * Applies to: properties
     * For classes, see ZEND_ACC_READONLY_CLASS
     */
    public const ZEND_ACC_READONLY = (1 << 7);

    /**
     * Applies to: classes, methods
     */
    public const ZEND_ACC_IMMUTABLE = (1 << 7);

    /**
     * Applies to: classes, methods
     */
    public const ZEND_ACC_HAS_TYPE_HINTS = (1 << 8);

    /**
     * Applies to: classes, methods
     */
    public const ZEND_ACC_TOP_LEVEL = (1 << 9);

    /**
     * Applies to: classes, methods
     */
    public const ZEND_ACC_PRELOADED = (1 << 10);

    /**
     * Applies to: properties
     */
    public const ZEND_CLASS_CONST_IS_CASE = (1 << 6);

    /**
     * Applies to: classes, methods, constants
     */
    public const ZEND_ACC_DEPRECATED = (1 << 11);

    /**
     * Applies to: methods, properties
     */
    public const ZEND_ACC_OVERRIDE = (1 << 28);

    /**
     * Applies to: properties
     */
    public const ZEND_ACC_PROMOTED = (1 << 8);

    /**
     * Applies to: properties
     */
    public const ZEND_ACC_VIRTUAL = (1 << 9);

    /**
     * Applies to: properties
     */
    public const ZEND_ACC_PUBLIC_SET = (1 << 10);

    /**
     * Applies to: properties
     */
    public const ZEND_ACC_PROTECTED_SET = (1 << 11);

    /**
     * Applies to: properties
     */
    public const ZEND_ACC_PRIVATE_SET = (1 << 12);

    /**
     * Applies to: classes
     */
    public const ZEND_ACC_INTERFACE = (1 << 0);

    /**
     * Applies to: classes
     */
    public const ZEND_ACC_TRAIT = (1 << 1);

    /**
     * Applies to: classes
     */
    public const ZEND_ACC_ANON_CLASS = (1 << 2);

    /**
     * Applies to: classes
     */
    public const ZEND_ACC_ENUM = (1 << 28);

    /**
     * Applies to: classes
     */
    public const ZEND_ACC_LINKED = (1 << 3);

    /**
     * Applies to: classes
     */
    public const ZEND_ACC_IMPLICIT_ABSTRACT_CLASS = (1 << 4);

    /**
     * Applies to: classes
     */
    public const ZEND_ACC_USE_GUARDS = (1 << 30);

    /**
     * Applies to: classes
     */
    public const ZEND_ACC_CONSTANTS_UPDATED = (1 << 12);

    /**
     * Applies to: classes
     */
    public const ZEND_ACC_NO_DYNAMIC_PROPERTIES = (1 << 13);

    /**
     * Applies to: classes
     */
    public const ZEND_HAS_STATIC_IN_METHODS = (1 << 14);

    /**
     * Applies to: classes
     */
    public const ZEND_ACC_ALLOW_DYNAMIC_PROPERTIES = (1 << 15);

    /**
     * Applies to: classes
     * For properties, see ZEND_ACC_READONLY
     */
    public const ZEND_ACC_READONLY_CLASS = (1 << 16);

    /**
     * Applies to: classes
     */
    public const ZEND_ACC_RESOLVED_PARENT = (1 << 17);

    /**
     * Applies to: classes
     */
    public const ZEND_ACC_RESOLVED_INTERFACES = (1 << 18);

    /**
     * Applies to: classes
     */
    public const ZEND_ACC_UNRESOLVED_VARIANCE = (1 << 19);

    /**
     * Applies to: classes
     */
    public const ZEND_ACC_NEARLY_LINKED = (1 << 20);

    /**
     * Applies to: classes
     */
    public const ZEND_ACC_HAS_READONLY_PROPS = (1 << 21);

    /**
     * Applies to: classes
     */
    public const ZEND_ACC_CACHED = (1 << 22);

    /**
     * Applies to: classes
     */
    public const ZEND_ACC_CACHEABLE = (1 << 23);

    /**
     * Applies to: classes
     */
    public const ZEND_ACC_HAS_AST_CONSTANTS = (1 << 24);

    /**
     * Applies to: classes
     */
    public const ZEND_ACC_HAS_AST_PROPERTIES = (1 << 25);

    /**
     * Applies to: classes
     */
    public const ZEND_ACC_HAS_AST_STATICS = (1 << 26);

    /**
     * Applies to: classes
     */
    public const ZEND_ACC_FILE_CACHED = (1 << 27);

    /**
     * Applies to: classes
     */
    public const ZEND_ACC_NOT_SERIALIZABLE = (1 << 29);

    /**
     * Applies to: methods
     */
    public const ZEND_ACC_RETURN_REFERENCE = (1 << 12);

    /**
     * Applies to: methods
     */
    public const ZEND_ACC_HAS_RETURN_TYPE = (1 << 13);

    /**
     * Applies to: methods
     */
    public const ZEND_ACC_VARIADIC = (1 << 14);

    /**
     * Applies to: methods
     */
    public const ZEND_ACC_HAS_FINALLY_BLOCK = (1 << 15);

    /**
     * Applies to: methods
     */
    public const ZEND_ACC_EARLY_BINDING = (1 << 16);

    /**
     * Applies to: methods
     */
    public const ZEND_ACC_USES_THIS = (1 << 17);

    /**
     * Applies to: methods
     */
    public const ZEND_ACC_CALL_VIA_TRAMPOLINE = (1 << 18);

    /**
     * Applies to: methods
     */
    public const ZEND_ACC_NEVER_CACHE = (1 << 19);

    /**
     * Applies to: methods
     */
    public const ZEND_ACC_TRAIT_CLONE = (1 << 20);

    /**
     * Applies to: methods
     */
    public const ZEND_ACC_CTOR = (1 << 21);

    /**
     * Applies to: methods
     */
    public const ZEND_ACC_CLOSURE = (1 << 22);

    /**
     * Applies to: methods
     */
    public const ZEND_ACC_FAKE_CLOSURE = (1 << 23);

    /**
     * Applies to: methods
     */
    public const ZEND_ACC_GENERATOR = (1 << 24);

    /**
     * Applies to: methods
     */
    public const ZEND_ACC_DONE_PASS_TWO = (1 << 25);

    /**
     * Applies to: methods
     */
    public const ZEND_ACC_ARENA_ALLOCATED = (1 << 25);

    /**
     * Applies to: methods
     */
    public const ZEND_ACC_HEAP_RT_CACHE = (1 << 26);

    /**
     * Applies to: methods
     */
    public const ZEND_ACC_USER_ARG_INFO = (1 << 26);

    /**
     * Applies to: methods
     */
    public const ZEND_ACC_COMPILE_TIME_EVAL = (1 << 27);

    /**
     * Applies to: methods
     */
    public const ZEND_ACC_PTR_OPS = (1 << 28);

    /**
     * Applies to: methods
     */
    public const ZEND_ACC_NODISCARD = (1 << 29);

    /**
     * Applies to: methods
     */
    public const ZEND_ACC_STRICT_TYPES = (1 << 31);

    /**
     * Applies to: methods
     */
    public const ZEND_ACC2_FORBID_DYN_CALLS = (1 << 0);

    /**
     * Applies to: methods, properties, constants
     */
    public const ZEND_ACC_PPP_MASK = (self::ZEND_ACC_PUBLIC | self::ZEND_ACC_PROTECTED | self::ZEND_ACC_PRIVATE);

    /**
     * Applies to: properties
     */
    public const ZEND_ACC_PPP_SET_MASK = (self::ZEND_ACC_PUBLIC_SET | self::ZEND_ACC_PROTECTED_SET | self::ZEND_ACC_PRIVATE_SET);

    /**
     * Applies to: methods
     */
    public const int ZEND_ACC_CALL_VIA_HANDLER = self::ZEND_ACC_CALL_VIA_TRAMPOLINE;

    // END OF EXTERNAL COPYRIGHT

    public const int IS_PTR = 13;
    public const int ZEND_INTERNAL_FUNCTION = 1;
    public const int ZEND_USER_FUNCTION = 2;

    private static ?FFI $def = null;

    /**
     * @param class-string $cls
     *
     * @return \Communism_FFI\zend_class_entry
     */
    public static function lookupClass(string $cls): object
    {
        $def = self::def();
        $name = $def->zend_strpprintf(128, '%s', $cls);
        $clazz = $def->zend_lookup_class($name);
        $def->free_estring(FFI::addr($name));

        return $clazz;
    }

    /**
     * @param class-string $cls
     * @param non-empty-string $method
     *
     * @return \Communism_FFI\zend_function|null
     */
    public static function lookupMethod(string $cls, string $method): ?object
    {
        $def = self::def();
        $clazz = self::lookupClass($cls);
        $functionTable = $clazz->function_table;
        $methodLower = mb_strtolower($method);
        $funcPtr = $def->zend_hash_str_find_ptr_lc(FFI::addr($functionTable), $methodLower, mb_strlen($methodLower));
        if ($funcPtr === null) {
            return null;
        }

        return $def->cast('zend_function *', $funcPtr);
    }

    /**
     * @param class-string $cls
     * @param non-empty-string $method
     *
     * @return \Communism_FFI\zval|null
     */
    private static function lookupMethodEntry(string $cls, string $method): ?object
    {
        $def = self::def();
        $clazz = self::lookupClass($cls);
        $functionTable = $clazz->function_table;
        $methodLower = mb_strtolower($method);

        return $def->zend_hash_str_find(
            FFI::addr($functionTable),
            $methodLower,
            mb_strlen($methodLower),
        );
    }

    public static function opcodeName(int $opcode): string
    {
        $name = self::def()->zend_get_opcode_name($opcode);
        if ($name === null) {
            return '';
        }

        return $name;
    }

    public static function opcodeId(string $opcode): int
    {
        $name = strtoupper($opcode);
        if (!str_starts_with($name, 'ZEND_')) {
            $name = 'ZEND_' . $name;
        }

        $id = self::def()->zend_get_opcode_id($name, mb_strlen($name));
        if (self::opcodeName($id) !== $name) {
            throw new InvalidArgumentException(sprintf('Unknown opcode: %s', $opcode));
        }

        return $id;
    }

    /** @param object $opline A zend_op CData value */
    public static function refreshOpcodeHandler(object $opline): void
    {
        self::def()->zend_vm_set_opcode_handler(FFI::addr($opline));
    }

    /**
     * @return \Communism_FFI\zend_function|null
     */
    public static function lookupFunction(string $function): ?object
    {
        $def = self::def();
        $functionLower = mb_strtolower($function);
        $functionTable = self::functionTable();
        $funcPtr = $def->zend_hash_str_find_ptr_lc(
            $functionTable,
            $functionLower,
            mb_strlen($functionLower),
        );

        if ($funcPtr === null) {
            return null;
        }

        return $def->cast('zend_function *', $funcPtr);
    }

    /**
     * @return \Communism_FFI\zval|null
     */
    public static function lookupFunctionEntry(string $function): ?object
    {
        $def = self::def();
        $functionLower = mb_strtolower($function);
        $functionTable = self::functionTable();

        return $def->zend_hash_str_find(
            $functionTable,
            $functionLower,
            mb_strlen($functionLower),
        );
    }

    /**
     * @param class-string $cls
     *
     * @return \Communism_FFI\zend_property_info|null
     */
    public static function lookupPropertyInfo(string $cls, string $property): ?object
    {
        $def = self::def();
        $clazz = self::lookupClass($cls);
        $propertiesInfo = $clazz->properties_info;
        $propInfoZval = $def->zend_hash_str_find(FFI::addr($propertiesInfo), $property, mb_strlen($property));

        if ($propInfoZval === null) {
            return null;
        }

        return $def->cast('zend_property_info *', $propInfoZval->value->ptr);
    }

    /**
     * @param class-string $cls
     */
    public static function initClassStatics(string $cls): void
    {
        self::def()->zend_class_init_statics(self::lookupClass($cls));
    }

    /**
     * @param class-string $cls
     *
     * @return bool
     */
    public static function classStaticsInitialized(string $cls): bool
    {
        $clazz = self::lookupClass($cls);

        return $clazz->static_members_table__ptr !== null;
    }

    /**
     * Swaps the two functions in the global function HashTable
     *
     * @param callable-string $functionA
     * @param callable-string $functionB
     * @return void
     */
    public static function swapFunctions(string $functionA, string $functionB): void
    {
        if (strcasecmp($functionA, $functionB) === 0) {
            return;
        }

        $def = self::def();
        $functionALower = mb_strtolower($functionA);
        $functionBLower = mb_strtolower($functionB);

        $entryA = self::lookupFunctionEntry($functionA);
        $entryB = self::lookupFunctionEntry($functionB);

        if ($entryA === null || $entryB === null) {
            throw new InvalidArgumentException(sprintf('Both functions must exist before they can be swapped: %s, %s', $functionA, $functionB));
        }

        try {
            $functionTable = self::functionTable();
            $bucketA = $def->cast('Bucket *', $entryA);
            $bucketB = $def->cast('Bucket *', $entryB);

            $tempName = sprintf('__communism_swap__%s__%s__', $functionALower, $functionBLower);
            $tempKey = $def->zend_strpprintf(mb_strlen($tempName), '%s', $tempName);
            $keyA = $def->zend_strpprintf(mb_strlen($functionALower), '%s', $functionALower);
            $keyB = $def->zend_strpprintf(mb_strlen($functionBLower), '%s', $functionBLower);

            if ($def->zend_hash_set_bucket_key($functionTable, $bucketA, $tempKey) === null) {
                throw new RuntimeException(sprintf('Failed to stage function rename for %s', $functionA));
            }

            if ($def->zend_hash_set_bucket_key($functionTable, $bucketB, $keyA) === null) {
                throw new RuntimeException(sprintf('Failed to rename %s to %s', $functionB, $functionA));
            }

            if ($def->zend_hash_set_bucket_key($functionTable, $bucketA, $keyB) === null) {
                throw new RuntimeException(sprintf('Failed to rename %s to %s', $functionA, $functionB));
            }

            // This one shouldn't be referenced, so clear it
            $def->free_estring(FFI::addr($tempKey));
            // We remove a ref, refcount should equal 2 here
            // set_bucket_key adds a ref
            // So we end up with 1, which is correct
            $keyA->gc->refcount--;
            if ($keyA->gc->refcount === 0) {
                $def->free_estring(FFI::addr($keyA));
            }

            $keyB->gc->refcount--;
            if ($keyB->gc->refcount === 0) {
                $def->free_estring(FFI::addr($keyB));
            }
        } finally {
            self::disableJitForFunction($functionA);
            self::disableJitForFunction($functionB);
            self::blacklistCurrentCallers();
        }
    }

    /**
     * Swaps two method implementations, where the second class derives from
     * the first one.
     *
     * @param class-string $classA
     * @param non-empty-string $methodA
     * @param class-string $classB
     * @param non-empty-string $methodB
     */
    public static function swapMethods(string $classA, string $methodA, string $classB, string $methodB): void
    {
        if (strcasecmp($classA, $classB) === 0 && strcasecmp($methodA, $methodB) === 0) {
            return;
        }

        if (!is_a($classB, $classA, true)) {
            throw new InvalidArgumentException(sprintf('%s must derive from %s before their methods can be swapped', $classB, $classA));
        }

        $entryA = self::lookupMethodEntry($classA, $methodA);
        $entryB = self::lookupMethodEntry($classB, $methodB);

        if ($entryA === null || $entryB === null) {
            throw new InvalidArgumentException(sprintf('Both methods must exist before they can be swapped: %s::%s, %s::%s', $classA, $methodA, $classB, $methodB));
        }

        // Invalidate JIT assumptions while the original implementations are
        // still installed. This also matters when one of the methods being
        // swapped is disableJitForMethod itself.
        self::disableJitForMethod($classA, $methodA);
        self::disableJitForMethod($classB, $methodB);

        $functionA = $entryA->value->ptr;
        $entryA->value->ptr = $entryB->value->ptr;
        $entryB->value->ptr = $functionA;

        self::blacklistCurrentCallers();
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
                    throw new InvalidArgumentException(sprintf(
                        'Trait %s declares more than one surrogate for handler %s',
                        $traitName,
                        $handlerName,
                    ));
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
                throw new InvalidArgumentException(sprintf('Trait method %s::%s cannot combine #[Final] and #[Mutable]', $traitName, $methodName));
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

        $def = self::def();
        $ffi = self::ffi();
        $target = self::lookupClass($className);
        $functionTable = $target->function_table;
        $functionTablePointer = $def->cast('HashTable *', FFI::addr($functionTable));

        foreach ($methodNames as $methodName) {
            $method = $availableMethods[$methodName];
            $accessor = $method->getAttributes(Accessor::class);
            $invoker = $method->getAttributes(Invoker::class);
            if ($accessor === [] && $invoker === []) {
                continue;
            }

            $generated = $accessor !== [] ? $accessor[0]->newInstance() : $invoker[0]->newInstance();
            $kind = $accessor !== [] ? 'accessor' : 'invoker';
            $entry = self::cloneGeneratedMethod($traitName, $methodName, $className, $kind, $method);
            $methodLower = mb_strtolower($methodName);
            $entryValue = $ffi->new('zval');
            $entryValue->value->ptr = $entry;
            $templateEntry = self::lookupMethodEntry(AccessorInvokerTemplates::class, $kind === 'accessor'
                ? ($method->isStatic() ? 'accessorStatic' : 'accessor')
                : ($method->isStatic() ? 'invokerStatic' : 'invoker'));
            if ($templateEntry === null) {
                throw new InvalidArgumentException('Generated accessor/invoker template was not found');
            }
            $entryValue->u1->type_info = $templateEntry->u1->type_info;
            $def->zend_hash_str_update($functionTablePointer, $methodLower, mb_strlen($methodLower), FFI::addr($entryValue));

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
                continue;
            }
            $entry = self::lookupMethodEntry($traitName, $methodName);
            if ($entry === null) {
                throw new InvalidArgumentException(sprintf('Could not find mixin method %s::%s', $traitName, $methodName));
            }

            $overwrite = $availableMethods[$methodName]->getAttributes(Overwrite::class);
            $targetMethod = $overwrite === [] ? $methodName : ($overwrite[0]->newInstance()->method ?? $methodName);
            $methodLower = mb_strtolower($targetMethod);
            if ($targetMethod === '') {
                throw new InvalidArgumentException('Injected method names must not be empty');
            }

            if ($overwrite !== []) {
                $oldEntry = self::lookupMethodEntry($className, $targetMethod);
                $bucket = $def->cast('Bucket *', $oldEntry);
                $deletedName = '';
                for ($deleted = 0; $deletedName === ''; $deleted++) {
                    $candidate = '__deleted' . $deleted;
                    if (self::lookupMethodEntry($className, $candidate) === null) {
                        $deletedName = $candidate;
                    }
                }

                $deletedKey = $def->zend_strpprintf(mb_strlen($deletedName), '%s', $deletedName);
                $def->zend_hash_set_bucket_key($functionTablePointer, $bucket, $deletedKey);
                $def->free_estring(FFI::addr($deletedKey));
            }

            if ($overwrite === [] && self::lookupMethodEntry($className, $targetMethod) !== null) {
                $uniqueName = '__unique_' . mb_strtolower($traitName) . '_' . $targetMethod;
                $uniqueName = preg_replace('/[^a-z0-9_]+/i', '_', $uniqueName) ?? ('__unique_' . $targetMethod);
                for ($suffix = 0; self::lookupMethodEntry($className, $uniqueName) !== null; $suffix++) {
                    $uniqueName = '__unique_' . $targetMethod . '_' . $suffix;
                }
                $targetMethod = $uniqueName;
                $methodLower = mb_strtolower($targetMethod);
            }

            $def->zend_hash_str_update($functionTablePointer, $methodLower, mb_strlen($methodLower), $entry);

            $injected = self::lookupMethod($className, $targetMethod);
            if ($injected !== null) {
                $injected->scope = $target;
                if ($availableMethods[$methodName]->getAttributes(Final_::class) !== []) {
                    $injected->fn_flags |= self::ZEND_ACC_FINAL;
                }
                if ($targetMethod !== $methodName) {
                    $functionName = $def->zend_strpprintf(mb_strlen($targetMethod), '%s', $targetMethod);
                    $injected->function_name = $functionName;
                }
            }
        }

        foreach ($rewrittenBodies as $targetMethod => $rewrittenBody) {
            if ($targetMethod === '') {
                throw new InvalidArgumentException('Injected target method names must not be empty');
            }

            $targetFunction = self::lookupMethod($className, $targetMethod);
            if ($targetFunction === null) {
                throw new InvalidArgumentException(sprintf('Could not find injected target method %s::%s', $className, $targetMethod));
            }

            \Communism\Internals\Needle\Assembler::write($rewrittenBody, $targetFunction->op_array);
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
        self::blacklistCurrentCallers();
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

    /**
     * @param class-string $traitName
     * @param class-string $className
     * @param non-empty-string $methodName
     * @return object
     */
    private static function cloneGeneratedMethod(string $traitName, string $methodName, string $className, string $kind, \ReflectionMethod $declaration): object
    {
        $templateName = match ($kind) {
            'accessor' => $declaration->isStatic() ? 'accessorStatic' : 'accessor',
            'invoker' => $declaration->isStatic() ? 'invokerStatic' : 'invoker',
            default => throw new InvalidArgumentException(sprintf('Unknown generated method kind %s', $kind)),
        };
        $template = self::lookupMethod(AccessorInvokerTemplates::class, $templateName);
        $source = self::lookupMethod($traitName, $methodName);
        $target = self::lookupClass($className);
        if ($template === null || $source === null) {
            throw new InvalidArgumentException(sprintf('Could not prepare generated method %s::%s', $traitName, $methodName));
        }

        $ffi = self::ffi();
        $def = self::def();
        $allocation = $def->_emalloc(FFI::sizeof($ffi->new('zend_function')));
        $generated = $def->cast('zend_function *', $allocation);
        FFI::memcpy($generated, $template, FFI::sizeof($ffi->new('zend_function')));

        $functionName = $def->zend_strpprintf(max(1, mb_strlen($methodName) + 1), '%s', $methodName);
        $generated->function_name = $functionName;
        $generated->scope = $target;
        $generated->prototype = null;
        $generated->fn_flags = $template->fn_flags;
        $generated->fn_flags = ($generated->fn_flags & ~self::ZEND_ACC_PPP_MASK) | ($source->fn_flags & self::ZEND_ACC_PPP_MASK);
        $generated->num_args = $template->num_args;
        $generated->required_num_args = $template->required_num_args;
        $generated->arg_info = $template->arg_info;
        $generated->attributes = null;
        for ($index = 0; $index < 3; $index++) {
            $generated->arg_flags[$index] = $template->arg_flags[$index];
        }

        return $generated;
    }

    /** @param class-string $className */
    private static function setPropertyMutability(string $className, string $property, bool $mutable, bool $final): void
    {
        $propertyInfo = self::lookupPropertyInfo($className, $property);
        if ($propertyInfo === null) {
            throw new InvalidArgumentException(sprintf('Could not find shadowed property %s::$%s', $className, $property));
        }

        if ($mutable) {
            $propertyInfo->flags &= ~self::ZEND_ACC_READONLY;
            $propertyInfo->flags &= ~self::ZEND_ACC_PPP_SET_MASK;
        }
        if ($final) {
            $propertyInfo->flags |= self::ZEND_ACC_READONLY;
            if (($propertyInfo->flags & (self::ZEND_ACC_PUBLIC | self::ZEND_ACC_READONLY | self::ZEND_ACC_PPP_SET_MASK)) === (self::ZEND_ACC_PUBLIC | self::ZEND_ACC_READONLY)) {
                $propertyInfo->flags |= self::ZEND_ACC_PROTECTED_SET;
            }
        }
    }

    /** @param class-string $className */
    private static function setMethodMutability(string $className, string $method, bool $mutable, bool $final): void
    {
        if ($method === '') {
            throw new InvalidArgumentException(sprintf('Could not find shadowed method %s::%s', $className, $method));
        }
        $function = self::lookupMethod($className, $method);
        if ($function === null) {
            throw new InvalidArgumentException(sprintf('Could not find shadowed method %s::%s', $className, $method));
        }

        if ($mutable) {
            $function->fn_flags &= ~self::ZEND_ACC_FINAL;
        }
        if ($final) {
            $function->fn_flags |= self::ZEND_ACC_FINAL;
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
        );
    }

    /**
     * Mutation of runtime metadata can leave JIT assumptions stale.
     *
     * @param callable-string $function
     */
    public static function disableJitForFunction(string $function): void
    {
        if (!function_exists('opcache_jit_blacklist')) {
            return;
        }

        $functionKey = mb_strtolower($function);
        if (isset(self::$blacklistedFunctions[$functionKey])) {
            return;
        }

        $func = self::lookupFunction($function);
        if ($func === null || $func->type !== self::ZEND_USER_FUNCTION || $func->op_array->opcodes === null) {
            return;
        }

        \opcache_jit_blacklist(\Closure::fromCallable($function));
        self::$blacklistedFunctions[$functionKey] = true;
    }

    /**
     * Disables JIT for a specific method to stop opcache from breaking things
     *
     * @param class-string $className
     * @param non-empty-string $method
     */
    public static function disableJitForMethod(string $className, string $method): void
    {
        if (!function_exists('opcache_jit_blacklist')) {
            return;
        }

        $methodKey = mb_strtolower($className . '::' . $method);
        if (isset(self::$blacklistedMethods[$methodKey])) {
            return;
        }

        $func = self::lookupMethod($className, $method);
        if ($func === null || $func->type !== self::ZEND_USER_FUNCTION || $func->op_array->opcodes === null) {
            return;
        }

        $reflectionMethod = new \ReflectionMethod($className, $method);
        $originalFlags = $func->fn_flags;

        // ReflectionMethod requires an object for non-static methods. Temporarily
        // presenting the function as static gives us a closure without creating
        // an object whose destructor could run during cleanup.
        $func->fn_flags |= self::ZEND_ACC_STATIC;
        try {
            $closure = $reflectionMethod->getClosure();
        } finally {
            $func->fn_flags = $originalFlags;
        }

        \opcache_jit_blacklist($closure);
        self::$blacklistedMethods[$methodKey] = true;
    }

    /**
     * Disable JIT for a specific class to prevent opcache from breaking things
     *
     * @param class-string $className
     */
    public static function disableJitForClass(string $className): void
    {
        if (!function_exists('opcache_jit_blacklist')) {
            return;
        }

        $classKey = mb_strtolower($className);
        if (isset(self::$blacklistedClasses[$classKey])) {
            return;
        }

        $classRef = new \ReflectionClass($className);
        if ($classRef->isInternal()) {
            return;
        }

        if ($classRef->isTrait()) {
            foreach (get_declared_classes() as $declaredClass) {
                $declaredRef = new \ReflectionClass($declaredClass);
                if ($declaredRef->isInternal() || $declaredRef->isTrait()) {
                    continue;
                }

                if (!in_array($className, $declaredRef->getTraitNames(), true)) {
                    continue;
                }

                foreach ($declaredRef->getMethods() as $reflectionMethod) {
                    self::disableJitForMethod($declaredRef->getName(), $reflectionMethod->getName());
                }
            }

            self::$blacklistedClasses[$classKey] = true;
            return;
        }

        foreach ($classRef->getMethods() as $reflectionMethod) {
            self::disableJitForMethod($classRef->getName(), $reflectionMethod->getName());
        }

        self::$blacklistedClasses[$classKey] = true;
    }

    public static function blacklistCurrentCallers(int $limit = 8): void
    {
        if (!function_exists('opcache_jit_blacklist')) {
            return;
        }

        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS | DEBUG_BACKTRACE_PROVIDE_OBJECT, $limit) as $frame) {
            if (isset($frame['object']) && $frame['object'] instanceof \Closure) {
                \opcache_jit_blacklist($frame['object']);
                continue;
            }

            /** @var callable-string $function */
            $function = $frame['function'];

            $class = $frame['class'] ?? null;
            if ($class !== null) {
                if (str_starts_with($class, __NAMESPACE__ . '\\')) {
                    continue;
                }

                self::disableJitForMethod($class, $function);
                continue;
            }

            self::disableJitForFunction($function);
        }
    }

    /**
     * @return FFI
     */
    private static function init(): FFI
    {
        $callingConvention = PHP_OS_FAMILY === 'Linux'
            ? (PHP_INT_SIZE === 4 ? '__attribute__((fastcall))' : '')
            : (PHP_OS_FAMILY === 'Windows' ? '__vectorcall' : '');

        $libraryPrefix = PHP_OS_FAMILY === 'Linux' ? 'lib' : '';
        $librarySuffix = PHP_OS_FAMILY === 'Linux' ? '.so' : '';
        $versionMinor = intdiv(PHP_VERSION_ID % 10_000, 100);
        $fallbackLibraries = PHP_OS_FAMILY === 'Windows'
            ? [
                $libraryPrefix . 'php8' . (ZEND_THREAD_SAFE ? 'ts' : '') . $librarySuffix,
                $libraryPrefix . 'php8.' . $versionMinor . (ZEND_THREAD_SAFE ? 'ts' : '') . $librarySuffix,
            ]
            : [
                // Bind to the active process before trying a separate libphp
                // instance. Linux PHP packages use the same library filename
                // for TS and NTS; the ABI is selected by the package itself,
                // not by adding "ts" to the filename.
                null,
                $libraryPrefix . 'php8.' . $versionMinor . $librarySuffix,
                $libraryPrefix . 'php8' . $librarySuffix,
                $libraryPrefix . 'php' . $librarySuffix,
            ];

        // Ask the operating system for the PHP library already loaded into this
        // process before trying names that may not exist for nightly builds.
        $libraries = array_values(array_unique([
            ...FindLoadedLibrary::php(),
            ...$fallbackLibraries,
        ]));
        $errors = [];

        // REASON: RTLD_DEFAULT (see v1v) does not work on Windows, don't even try.
        // 1: https://www.php.net/manual/en/ffi.cdef.php#refsect1-ffi.cdef-parameters
        foreach ($libraries as $library) {
            if (PHP_OS_FAMILY === 'Linux' && $library !== null && !FindLoadedLibrary::isLoaded($library)) {
                $errors[] = $library . ': not loaded (RTLD_NOLOAD)';

                if ($library === $libraries[array_key_last($libraries)]) {
                    throw new RuntimeException(
                        "Unable to load the Zend FFI binding:\n" . implode("\n", $errors),
                    );
                }

                continue;
            }

            try {
                self::$def = FFI::cdef(<<<'EOF'
typedef struct _zval_struct zval;
typedef uint64_t zend_ulong;
typedef union _zend_function zend_function;
typedef struct zend_class_entry zend_class_entry;
typedef struct _zend_executor_globals zend_executor_globals;
typedef struct zend_refcounted_h {
    uint32_t refcount;
    union { uint32_t type_info; } u;
} zend_refcounted_h;
typedef struct _zend_string {
    zend_refcounted_h gc;
    zend_ulong h;
    size_t len;
    char val[1];
} zend_string;
typedef union _zend_value {
    void *ptr;
    zend_function *func;
    int64_t lval;
    double dval;
} zend_value;
typedef struct _zval_struct {
    zend_value value;
    union {
        uint32_t type_info;
        struct {
            uint8_t type;
            uint8_t type_flags;
            union {
                uint16_t extra;
            } u;
        } v;
    } u1;
    union {
        uint32_t next;
        uint32_t cache_slot;
        uint32_t opline_num;
        uint32_t lineno;
        uint32_t num_args;
        uint32_t fe_pos;
        uint32_t fe_iter_idx;
        uint32_t guard;
        uint32_t constant_flags;
        uint32_t extra;
    } u2;
} zval;
typedef void (*dtor_func_t)(zval *pDest);
typedef struct _Bucket {
    zval val;
    zend_ulong h;
    zend_string *key;
} Bucket;
typedef struct zend_array {
    zend_refcounted_h gc;
    union {
        struct {
            uint8_t flags;
            uint8_t _unused;
            uint8_t nIteratorsCount;
            uint8_t _unused2;
        } v;
        uint32_t flags;
    } u;
    uint32_t nTableMask;
    union {
        uint32_t *arHash;
        Bucket *arData;
        zval *arPacked;
    };
    uint32_t nNumUsed;
    uint32_t nNumOfElements;
    uint32_t nTableSize;
    uint32_t nInternalPointer;
    int64_t nNextFreeElement;
    dtor_func_t pDestructor;
} HashTable;
typedef struct _zend_stream {
    void *handle;
    int isatty;
    void *reader;
    void *fsizer;
    void *closer;
} zend_stream;
typedef struct _zend_file_handle {
    union {
        void *fp;
        zend_stream stream;
    } handle;
    zend_string *filename;
    zend_string *opened_path;
    uint8_t type;
    bool primary_script;
    bool in_list;
    char *buf;
    size_t len;
} zend_file_handle;
typedef struct zend_class_entry {
    char type;
    zend_string *name;
    union {
        zend_class_entry *parent;
        zend_string *parent_name;
    };
    int refcount;
    uint32_t ce_flags;

EOF . (self::supportsPhp86() ? "    uint32_t ce_flags2;\n\n" : '') . <<<'EOF'
    int default_properties_count;
    int default_static_members_count;
    zval *default_properties_table;
    zval *default_static_members_table;
    zval *static_members_table__ptr;
    HashTable function_table;
    HashTable properties_info;
    HashTable constants_table;
} zend_class_entry;
typedef struct _zend_type {
    void *ptr;
    uint32_t type_mask;
} zend_type;
typedef struct _zend_property_info zend_property_info;
typedef struct _zend_op zend_op;
typedef struct _zend_op_array zend_op_array;
typedef struct _zend_live_range zend_live_range;
typedef struct _zend_try_catch_element zend_try_catch_element;
typedef struct _zend_property_info {
    uint32_t offset;
    uint32_t flags;
    zend_string *name;
    zend_string *doc_comment;
    HashTable *attributes;
    zend_class_entry *ce;
    zend_type type;
    const zend_property_info *prototype;
    zend_function **hooks;
} zend_property_info;
typedef union _zend_function {
    struct {
        uint8_t type;
        uint8_t arg_flags[3];
        uint32_t fn_flags;
        zend_string *function_name;
        zend_class_entry *scope;
        zend_function *prototype;
        uint32_t num_args;
        uint32_t required_num_args;
        void *arg_info;
        HashTable *attributes;
        void *run_time_cache;
        zend_string *doc_comment;
        uint32_t T;

EOF . (self::supportsPhp86() ? "        uint32_t fn_flags2;\n" : '') . <<<'EOF'
        const zend_property_info *prop_info;
    };
    struct _zend_op_array {
        uint8_t type;
        uint8_t arg_flags[3];
        uint32_t fn_flags;
        zend_string *function_name;
        zend_class_entry *scope;
        zend_function *prototype;
        uint32_t num_args;
        uint32_t required_num_args;
        void *arg_info;
        HashTable *attributes;
        void *run_time_cache__ptr;
        zend_string *doc_comment;
        uint32_t T;
EOF . (self::supportsPhp86() ? "        uint32_t fn_flags2;\n" : '') . <<<'EOF'
        const zend_property_info *prop_info;
        int cache_size;
        int last_var;
        uint32_t last;
        zend_op *opcodes;
        HashTable *static_variables_ptr__ptr;
        HashTable *static_variables;
        zend_string **vars;
        uint32_t *refcount;
        int last_live_range;
        int last_try_catch;
        zend_live_range *live_range;
        zend_try_catch_element *try_catch_array;
        zend_string *filename;
        uint32_t line_start;
        uint32_t line_end;
        int last_literal;
        uint32_t num_dynamic_func_defs;
        zval *literals;
        zend_op_array **dynamic_func_defs;
        void *reserved[6];
    } op_array;
} zend_function;
typedef struct _zend_stack {
    int size;
    int top;
    int max;
    void *elements;
} zend_stack;
typedef struct _zend_declarables {
    int64_t ticks;
} zend_declarables;
typedef struct _zend_oparray_context {
    void *prev;
    zend_op_array *op_array;
    uint32_t opcodes_size;
    uint32_t vars_size;
    uint32_t literals_size;
    uint32_t fast_call_var;
    uint32_t try_catch_offset;
    int current_brk_cont;
    int last_brk_cont;
    void *brk_cont_array;
    HashTable *labels;
    zend_string *active_property_info_name;
    int active_property_hook_kind;
    bool in_jmp_frameless_branch;
    bool has_assigned_to_http_response_header;
} zend_oparray_context;
typedef struct _zend_file_context {
    zend_declarables declarables;
    zend_string *current_namespace;
    bool in_namespace;
    bool has_bracketed_namespaces;
    HashTable *imports;
    HashTable *imports_function;
    HashTable *imports_const;
    HashTable seen_symbols;
} zend_file_context;
typedef struct _zend_arena {
    char *ptr;
    char *end;
    struct _zend_arena *prev;
} zend_arena;
typedef struct _zend_compiler_globals {
    zend_stack loop_var_stack;
    zend_class_entry *active_class_entry;
    zend_string *compiled_filename;
    uint32_t zend_lineno;
    zend_op_array *active_op_array;
    HashTable *function_table;
    HashTable *class_table;
    HashTable *auto_globals;
    uint8_t parse_error;
    bool in_compilation;
    bool short_tags;
    bool unclean_shutdown;
    bool ini_parser_unbuffered_errors;
    zend_stack open_files;
    void *ini_parser_param;
    bool skip_shebang;
    bool increment_lineno;
    bool variable_width_locale;
    bool ascii_compatible_locale;
    zend_string *doc_comment;
    uint32_t extra_fn_flags;
    uint32_t compiler_options;
    zend_oparray_context context;
    zend_file_context file_context;
    zend_arena *arena;
} zend_compiler_globals;
extern zend_op_array *(*zend_compile_file)(zend_file_handle *file_handle, int type);
zend_op_array *compile_file(zend_file_handle *file_handle, int type);
void zend_stream_init_filename(zend_file_handle *handle, const char *filename);
void zend_destroy_file_handle(zend_file_handle *file_handle);
void destroy_op_array(zend_op_array *op_array);
typedef union _znode_op {
    uint32_t constant;
    uint32_t var;
    uint32_t num;
    uint32_t opline_num;
EOF . (PHP_INT_SIZE === 4 ? <<<'EOF'
    uint32_t jmp_offset;
    zval *zv;
    zend_op *jmp_addr;
EOF
 : '') . <<<'EOF'
} znode_op;
struct _zend_live_range {
    uint32_t var;
    uint32_t start;
    uint32_t end;
};
struct _zend_try_catch_element {
    uint32_t try_op;
    uint32_t catch_op;
    uint32_t finally_op;
    uint32_t finally_end;
};
struct _zend_op {
    void *handler;
    znode_op op1;
    znode_op op2;
    znode_op result;
    uint32_t extended_value;
    uint32_t lineno;
    uint8_t opcode;
    uint8_t op1_type;
    uint8_t op2_type;
    uint8_t result_type;
};
typedef struct _jmp_buf JMP_BUF;
typedef struct _zend_executor_globals {
    zval uninitialized_zval;
    zval error_zval;
    HashTable *symtable_cache[32];
    HashTable **symtable_cache_limit;
    HashTable **symtable_cache_ptr;
    HashTable symbol_table;
    HashTable included_files;
    JMP_BUF *bailout;
    int error_reporting;

EOF . (PHP_VERSION_ID >= 80_500 ? <<<'EOF'
    bool fatal_error_backtrace_on;
    zval last_fatal_error_backtrace;
EOF : '') . <<<'EOF'

    int exit_status;
    HashTable *function_table;
    HashTable *class_table;
    HashTable *zend_constants;
} zend_executor_globals;
void *  zend_hash_str_find_ptr_lc(const HashTable *ht, const char *str, size_t len);

EOF
. "void {$callingConvention} zend_hash_del_bucket(HashTable *ht, Bucket *bucket);\n"
. "zval * {$callingConvention} zend_hash_str_find(const HashTable *ht, const char *key, size_t len);\n"
. "zval * {$callingConvention} zend_hash_str_update(HashTable *ht, const char *key, size_t len, zval *pData);\n"
. "zval * {$callingConvention} zend_hash_set_bucket_key(HashTable *ht, Bucket *p, zend_string *key);\n"
. "void * {$callingConvention} _emalloc(size_t size);\n"
. "void {$callingConvention} _efree(void *ptr);\n"
. <<<'EOF'
zend_string *zend_strpprintf(size_t max_len, const char *format, ...);
zend_class_entry *zend_lookup_class(zend_string *name);
void zend_class_init_statics(zend_class_entry *class_type);

EOF
. "const char* {$callingConvention} zend_get_opcode_name(uint8_t opcode);\n"
. "uint8_t zend_get_opcode_id(const char *name, size_t length);\n"
. "uint64_t {$callingConvention} zend_hash_func(const char *str, size_t len);\n"
. "void {$callingConvention} zend_vm_set_opcode_handler(zend_op *opcode);\n"
. <<<'EOF'
void free_estring(zend_string **foo);

EOF . (ZEND_THREAD_SAFE
? "extern int executor_globals_id;\nextern size_t executor_globals_offset;\nextern int compiler_globals_id;\nextern size_t compiler_globals_offset;\nvoid *tsrm_get_ls_cache(void);\nvoid *ts_resource_ex(int id, void *thread_id);\n"
: "extern zend_executor_globals executor_globals;\nextern zend_compiler_globals compiler_globals;\n"), $library);
                self::functionTable();
                break;
            } catch (\FFI\Exception|RuntimeException $exception) {
                $errors[] = ($library ?? 'RTLD_DEFAULT') . ': ' . $exception->getMessage();

                // If we are at the last library, report every attempted
                // handle. This is especially useful for TS builds, whose
                // library filename is not standardized across distributions.
                if ($library === $libraries[array_key_last($libraries)]) {
                    throw new \RuntimeException(
                        "Unable to load the Zend FFI binding:\n" . implode("\n", $errors),
                        0,
                        $exception,
                    );
                }
            }
        }

        // Required to shut-up PHPStan
        assert(self::$def !== null);

        return self::$def;
    }

    /**
     * @return FFI
     */
    private static function def(): FFI
    {
        if (self::$def instanceof FFI) {
            return self::$def;
        }

        return self::init();
    }

    /**
     * @internal This FFI is unsafe, API consumers should not use this function
     *
     * @return FFI
     */
    public static function ffi(): FFI
    {
        return self::def();
    }

    /**
     * @internal Used by Needle's compile-only inspection path.
     *
     * @return \Communism_FFI\zend_compiler_globals
     */
    public static function compilerGlobals(): object
    {
        $def = self::def();

        if (ZEND_THREAD_SAFE) {
            $compilerGlobals = $def->ts_resource_ex($def->compiler_globals_id, null);

            if ($compilerGlobals === null || FFI::isNull($compilerGlobals)) {
                throw new RuntimeException('Compiler globals are not available');
            }

            return $def->cast('zend_compiler_globals *', $compilerGlobals);
        }

        return $def->compiler_globals;
    }

    /**
     * @return \Communism_FFI\HashTable
     */
    private static function functionTable(): object
    {
        $functionTable = self::executorGlobals()->function_table;

        if ($functionTable === null) {
            throw new RuntimeException('Function table is not available');
        }

        return $functionTable;
    }

    private static function supportsPhp86(): bool
    {
        // PHP development snapshots report versions such as 8.6.0-dev.
        // version_compare() sorts those below the unreleased 8.6.0 stable
        // version even though their ABI already contains the 8.6 layout.
        // @phpstan-ignore greaterOrEqual.alwaysFalse
        return PHP_VERSION_ID >= 80_600;
    }

    /**
     * @return \Communism_FFI\zend_executor_globals
     */
    private static function executorGlobals(): object
    {
        $def = self::def();

        if (ZEND_THREAD_SAFE) {
            $executorGlobals = $def->ts_resource_ex($def->executor_globals_id, null);

            if ($executorGlobals === null || FFI::isNull($executorGlobals)) {
                throw new RuntimeException('Executor globals are not available');
            }

            return $def->cast('zend_executor_globals *', $executorGlobals);
        } else {
            return $def->executor_globals;
        }
    }
}
