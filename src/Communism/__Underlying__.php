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
 * File: __Underlying__.php                                                   *
 * Purpose: Underlying code to access the PHP runtime                         *
 *============================================================================*/

declare(strict_types=1);

namespace Communism;

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
final class __Underlying__
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

    public static function opcodeName(int $opcode): string
    {
        $name = self::def()->zend_get_opcode_name($opcode);
        if ($name === null) {
            return '';
        }

        return $name;
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
     * Mutation of runtime metadata can leave JIT assumptions stale.
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
        if ($reflectionMethod->isStatic()) {
            $closure = $reflectionMethod->getClosure();
        } else {
            $declaringClass = $reflectionMethod->getDeclaringClass();
            if (!$declaringClass->isInstantiable()) {
                return;
            }

            $closure = $reflectionMethod->getClosure($declaringClass->newInstanceWithoutConstructor());
        }

        if ($closure === null) {
            return;
        }

        \opcache_jit_blacklist($closure);
        self::$blacklistedMethods[$methodKey] = true;
    }

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

            $function = $frame['function'] ?? null;
            if ($function === null) {
                continue;
            }

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
            ? PHP_INT_SIZE == 4 ? '__attribute__((fastcall))' : 0
            : '__vectorcall';

        $libraryPrefix = PHP_OS_FAMILY === 'Linux' ? 'lib' : '';
        $librarySuffix = PHP_OS_FAMILY === 'Linux' ? '.so' : '';
        $versionMinor = intdiv(PHP_VERSION_ID % 10_000, 100);
        $libraries = [
            $libraryPrefix . 'php8' . (ZEND_THREAD_SAFE ? 'ts' : '') . $librarySuffix,
            $libraryPrefix . 'php8.' . $versionMinor . (ZEND_THREAD_SAFE ? 'ts' : '') . $librarySuffix,
        ];
        if (PHP_OS_FAMILY === 'Linux') {
            $libraries[] = null; // This doesn't work on Windows, due to Windows not having RTLD_DEFAULT
        }

        foreach ($libraries as $library) {
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
. "zval * {$callingConvention} zend_hash_str_find(const HashTable *ht, const char *key, size_t len);\n"
. "zval * {$callingConvention} zend_hash_str_update(HashTable *ht, const char *key, size_t len, zval *pData);\n"
. "zval * {$callingConvention} zend_hash_set_bucket_key(HashTable *ht, Bucket *p, zend_string *key);\n"
. <<<'EOF'
zend_string *zend_strpprintf(size_t max_len, const char *format, ...);
zend_class_entry *zend_lookup_class(zend_string *name);
void zend_class_init_statics(zend_class_entry *class_type);

EOF
. "const char* {$callingConvention} zend_get_opcode_name(uint8_t opcode);\n"
. <<<'EOF'
void free_estring(zend_string **foo);

EOF . (ZEND_THREAD_SAFE
? "extern int executor_globals_id;\nextern size_t executor_globals_offset;\nvoid *tsrm_get_ls_cache(void);\n"
: "extern zend_executor_globals executor_globals;\n"), $library);
                break;
            } catch (\FFI\Exception $exception) {
                if ($library === $libraries[array_key_last($libraries)]) {
                    throw $exception;
                }
            }
        }

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

    public static function ffi(): FFI
    {
        return self::def();
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
        return version_compare(PHP_VERSION, '8.6.0', '>=');
    }

    /**
     * @return \Communism_FFI\zend_executor_globals
     */
    private static function executorGlobals(): object
    {
        $def = self::def();

        if (ZEND_THREAD_SAFE) {
            $lsCache = $def->tsrm_get_ls_cache();
            return $def->cast(
                'zend_executor_globals *',
                $def->cast('char *', $lsCache) + $def->executor_globals_offset,
            );
        } else {
            return $def->executor_globals;
        }
    }
}
