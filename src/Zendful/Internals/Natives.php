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
 * :: Zendful :: "When PHP doesn't provide it, we do!" ::                     *
 *----------------------------------------------------------------------------*
 * File: Natives.php                                                          *
 * Consumer: Internal                                                         *
 * Purpose: Source file for FFI.php.                                          *
 *============================================================================*/

declare(strict_types=1);

namespace Zendful\Internals;

use FFI;
use RuntimeException;

class Natives
{
    // Class, method, property, and class-constant flags from <PHP>/Zend/zend_compile.h.
    // These mirror the internals names so the bit twiddling below stays readable.

    // START OF EXTERNAL COPYRIGHT
    // Copyright © 1999–2026, The PHP Group and Contributors.
    // Copyright © 1999–2026, Zend Technologies Ltd., a subsidiary company of Perforce Software, Inc.

    // Zend operand types are stored in an unsigned byte in zend_op.
    public const ZEND_IS_UNUSED = 0;
    public const ZEND_IS_CONST = 1;
    public const ZEND_IS_TMP_VAR = 2;
    public const ZEND_IS_VAR = 4;
    public const ZEND_IS_CV = 8;
    public const ZEND_IS_SMART_BRANCH_JMPZ = (1 << 4);
    public const ZEND_IS_SMART_BRANCH_JMPNZ = (1 << 5);
    public const ZEND_OPERAND_TYPE_MASK = self::ZEND_IS_UNUSED
        | self::ZEND_IS_CONST
        | self::ZEND_IS_TMP_VAR
        | self::ZEND_IS_VAR
        | self::ZEND_IS_CV;
    public const ZEND_SMART_BRANCH_MASK = self::ZEND_IS_SMART_BRANCH_JMPZ | self::ZEND_IS_SMART_BRANCH_JMPNZ;
    public const ZEND_OP_TYPE_MAX = (1 << 8) - 1;
    public const ZEND_OPCODE_MAX = (1 << 8) - 1;
    public const ZEND_UINT32_MAX = 0xFFFFFFFF;
    public const ZEND_INT32_MAX = 0x7FFFFFFF;
    public const ZEND_OPERAND_TYPES = [
        self::ZEND_IS_UNUSED,
        self::ZEND_IS_CONST,
        self::ZEND_IS_TMP_VAR,
        self::ZEND_IS_VAR,
        self::ZEND_IS_CV,
    ];
    public const ZEND_FUNCTION_TYPE_USER = 2;
    public const ZEND_CLASS_TYPE_INTERNAL = 1;
    public const ZEND_CLASS_TYPE_USER = 2;
    public const HASH_FLAG_PACKED = (1 << 2);
    public const ZEND_ARGUMENT_FLAG_COUNT = 3;
    public const ZEND_RESERVED_VARIABLE_COUNT = 5;
    public const ZEND_OP_ALIGNMENT = 16;
    public const ZEND_MIN_ALLOCATION_SIZE = 1;
    /** Maximum string payload Zendful will copy from an untrusted runtime pointer. */
    public const ZEND_MAX_SAFE_STRING_LENGTH = 16 * 1024 * 1024;
    public const ZEND_CALLER_BLACKLIST_DEPTH = 8;

    public const ZEND_TYPE_UNDEF = 0;
    public const ZEND_TYPE_NULL = 1;
    public const ZEND_TYPE_FALSE = 2;
    public const ZEND_TYPE_TRUE = 3;
    public const ZEND_TYPE_LONG = 4;
    public const ZEND_TYPE_DOUBLE = 5;
    public const ZEND_TYPE_STRING = 6;
    public const ZEND_TYPE_ARRAY = 7;
    public const ZEND_TYPE_OBJECT = 8;
    public const ZEND_TYPE_RESOURCE = 9;
    public const ZEND_TYPE_CONSTANT_AST = 11;

    /**
     * Applies to: everything
     */
    public const ZEND_ACC_NONE = 0;

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
    private static ?FFI $def = null;
    private static ?string $library = null;

    /**
     * @return FFI
     */
    // @codeCoverageIgnoreStart
    private static function init(): FFI
    {
        $useLoadedModule = PHP_OS_FAMILY !== 'Windows' || PHP_VERSION_ID >= 80_500;
        $callingConvention = self::callingConvention(PHP_OS_FAMILY, PHP_INT_SIZE);
        $fallbackLibraries = self::fallbackLibraries(
            PHP_OS_FAMILY,
            ZEND_THREAD_SAFE,
            intdiv(PHP_VERSION_ID % 10_000, 100),
        );

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

                if ($library === ($libraries[array_key_last($libraries)] ?? null)) {
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
EOF . <<<'EOF'
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
void destroy_zend_function(zend_function *function);
void zval_ptr_dtor(zval *zval_ptr);
typedef union _znode_op {
    int32_t constant;
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
. "void {$callingConvention} zval_copy_ctor_func(zval *zval_ptr);\n"
. "int {$callingConvention} zend_hash_str_del(HashTable *ht, const char *key, size_t len);\n"
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
: "extern zend_executor_globals executor_globals;\nextern zend_compiler_globals compiler_globals;\n"), $useLoadedModule ? null : $library);
                self::$library = $useLoadedModule ? null : $library;
                self::functionTable();
                break;
            } catch (\FFI\Exception|RuntimeException $exception) {
                $errors[] = ($library ?? 'RTLD_DEFAULT') . ': ' . $exception->getMessage();

                // If we are at the last library, report every attempted
                // handle. This is especially useful for TS builds, whose
                // library filename is not standardized across distributions.
                if ($library === ($libraries[array_key_last($libraries)] ?? null)) {
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
    // @codeCoverageIgnoreEnd

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
        // @codeCoverageIgnoreStart
        if (!extension_loaded('FFI')) {
            throw new RuntimeException('Zendful fallback requires the FFI extension when no native backend is installed.');
        }
        // @codeCoverageIgnoreEnd

        return self::def();
    }

    private static function callingConvention(string $osFamily, int $intSize): string
    {
        return $osFamily === 'Linux'
            ? ($intSize === 4 ? '__attribute__((fastcall))' : '')
            : ($osFamily === 'Windows' ? '__vectorcall' : '');
    }

    /** @return list<string|null> */
    private static function fallbackLibraries(string $osFamily, bool $threadSafe, int $versionMinor): array
    {
        $libraryPrefix = $osFamily === 'Linux' ? 'lib' : '';
        $librarySuffix = $osFamily === 'Linux' ? '.so' : '';

        if ($osFamily === 'Windows') {
            return [
                $libraryPrefix . 'php8' . ($threadSafe ? 'ts' : '') . $librarySuffix,
                $libraryPrefix . 'php8.' . $versionMinor . ($threadSafe ? 'ts' : '') . $librarySuffix,
            ];
        }

        // Bind to the active process before trying a separate libphp
        // instance. Linux PHP packages use the same library filename
        // for TS and NTS; the ABI is selected by the package itself,
        // not by adding "ts" to the filename.
        return [
            null,
            $libraryPrefix . 'php8.' . $versionMinor . $librarySuffix,
            $libraryPrefix . 'php8' . $librarySuffix,
            $libraryPrefix . 'php' . $librarySuffix,
        ];
    }

    /**
     * @internal Used by Needle's compile-only inspection path.
     *
     * @return \Zendful_FFI\zend_compiler_globals
     */
    public static function compilerGlobals(): object
    {
        $def = self::def();

        // @codeCoverageIgnoreStart
        if (ZEND_THREAD_SAFE) {
            $compilerGlobals = $def->ts_resource_ex($def->compiler_globals_id, null);

            if ($compilerGlobals === null || FFI::isNull($compilerGlobals)) {
                throw new RuntimeException('Compiler globals are not available');
            }

            return $def->cast('zend_compiler_globals *', $compilerGlobals);
        }
        // @codeCoverageIgnoreEnd

        return $def->compiler_globals;
    }

    public static function framelessFunctionName(int $index): ?string
    {
        if (!self::supportsPhp86() || $index < 0) {
            return null;
        }

        $library = self::$library;

        // Keep this binding scalar-only. PHP 8.6's Windows TS FFI extension
        // can crash while destroying a path-backed extern pointer declaration
        // during request shutdown, so this uses the already-loaded process
        // module and is created once per process.
        self::$flf ??= FFI::cdef(
            'typedef unsigned long long zendful_uintptr; extern zendful_uintptr *zend_flf_functions;',
            $library,
        );
        $functions = self::$flf->zend_flf_functions;
        $address = null;
        for ($current = 0; ; $current++) {
            $address = $functions[$current];
            if ($address === 0) {
                break;
            }
            if ($current === $index) {
                break;
            }
        }

        if ($address === null || $address === 0) {
            return null;
        }

        $function = self::def()->cast('zend_function *', $address);
        $name = $function->function_name;
        if ($name === null || FFI::isNull($name)) {
            return null;
        }

        return self::zendString($name);
    }

    /** @param \Zendful_FFI\zend_string $string */
    private static function zendString(object $string): string
    {
        if ($string->len <= 0) {
            return '';
        }
        if ($string->len > self::ZEND_MAX_SAFE_STRING_LENGTH) {
            throw new RuntimeException('Zend string exceeds Zendful safety limits.');
        }

        return FFI::string(self::def()->cast('char *', $string->val), $string->len);
    }

    /**
     * @return \Zendful_FFI\HashTable
     */
    private static function functionTable(): object
    {
        $functionTable = self::executorGlobals()->function_table;

        // @codeCoverageIgnoreStart
        if ($functionTable === null) {
            throw new RuntimeException('Function table is not available');
        }
        // @codeCoverageIgnoreEnd

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
     * @return \Zendful_FFI\zend_executor_globals
     */
    private static function executorGlobals(): object
    {
        $def = self::def();

        // @codeCoverageIgnoreStart
        if (ZEND_THREAD_SAFE) {
            $executorGlobals = $def->ts_resource_ex($def->executor_globals_id, null);

            if ($executorGlobals === null || FFI::isNull($executorGlobals)) {
                throw new RuntimeException('Executor globals are not available');
            }

            return $def->cast('zend_executor_globals *', $executorGlobals);
        } else {
            // @codeCoverageIgnoreEnd
            return $def->executor_globals;
        }
    }
}
