<?php

declare(strict_types=1);

namespace Communism_FFI {
    /**
     * @template T
     */
    final class ptr {}

    /**
     * @property int $refcount
     */
    final class zend_refcounted_h
    {
        public int $refcount;
    }

    /**
     * @property zend_refcounted_h $gc
     * @property int $h
     * @property int $len
     * @property string $val
     */
    final class zend_string
    {
        public zend_refcounted_h $gc;
        public int $h;
        public int $len;
        /** @type ptr<char> */
        public object $val;
    }

    final class char {}

    final class char_1 {}

    /**
     * @property object|null $ptr
     * @property zend_function|null $func
     */
    final class zend_value
    {
        public ?object $ptr;
        public ?zend_function $func;
    }

    /**
     * @property zend_value $value
     * @property object $u1
     * @property object $u2
     */
    final class zval
    {
        public zend_value $value;
        public object $u1;
        public object $u2;
    }

    /**
     * @property zval $val
     * @property int $h
     * @property zend_string $key
     */
    final class Bucket
    {
        public zval $val;
        public int $h;
        public zend_string $key;
    }

    final class HashTable {}

    /**
     * @property mixed $ptr
     * @property int $type_mask
     */
    final class zend_type
    {
        public mixed $ptr;
        public int $type_mask;
    }

    /**
     * @property int $fn_flags
     * @property zend_string|null $function_name
     * @property zend_class_entry|null $scope
     * @property zend_function|null $prototype
     * @property zend_string|null $doc_comment
     * @property zend_property_info|null $prop_info
     * @property zend_op_array $op_array
     * @property int $type
     */
    final class zend_function
    {
        public int $type;
        public int $fn_flags;
        public ?zend_string $function_name;
        public ?zend_class_entry $scope;
        public ?zend_function $prototype;
        public ?zend_string $doc_comment;
        public ?zend_property_info $prop_info;
        public zend_op_array $op_array;
    }

    /**
     * @property int $last
     * @property int $num_args
     * @property int $last_var
     * @property int $T
     * @property zend_string|null $filename
     * @property int $line_start
     * @property int $line_end
     * @property array<int, \Communism_FFI\zend_op>|null $opcodes
     */
    final class zend_op_array
    {
        public int $last;
        public int $num_args;
        public int $last_var;
        public int $T;
        public ?zend_string $filename;
        public int $line_start;
        public int $line_end;
        /** @var array<int, zend_op>|null */
        public ?array $opcodes;
    }

    /**
     * @property int $opcode
     * @property int $op1_type
     * @property int $op2_type
     * @property int $result_type
     * @property int $extended_value
     * @property int $lineno
     * @property znode_op $op1
     * @property znode_op $op2
     * @property znode_op $result
     */
    final class zend_op
    {
        public int $opcode;
        public int $op1_type;
        public int $op2_type;
        public int $result_type;
        public int $extended_value;
        public int $lineno;
        public object $op1;
        public object $op2;
        public object $result;
    }

    /**
     * @property int $constant
     * @property int $var
     * @property int $num
     * @property int $opline_num
     */
    final class znode_op
    {
        public int $constant;
        public int $var;
        public int $num;
        public int $opline_num;
    }

    /**
     * @property int $offset
     * @property int $flags
     * @property zend_string|null $name
     * @property zend_string|null $doc_comment
     * @property HashTable|null $attributes
     * @property zend_class_entry|null $ce
     * @property zend_type $type
     * @property zend_property_info|null $prototype
     * @property array<int, zend_function>|null $hooks
     */
    final class zend_property_info
    {
        public int $offset;
        public int $flags;
        public ?zend_string $name;
        public ?zend_string $doc_comment;
        public ?HashTable $attributes;
        public ?zend_class_entry $ce;
        public zend_type $type;
        public ?zend_property_info $prototype;
        /** @var array<int, zend_function>|null */
        public ?array $hooks;
    }

    /**
     * @property string $type
     * @property zend_string|null $name
     * @property zend_class_entry|null $parent
     * @property zend_string|null $parent_name
     * @property int $refcount
     * @property int $ce_flags
     * @property int|null $ce_flags2
     * @property int $default_properties_count
     * @property int $default_static_members_count
     * @property array<int, zval>|null $default_properties_table
     * @property array<int, zval>|null $default_static_members_table
     * @property zval|null $static_members_table__ptr
     * @property ptr<HashTable> $function_table
     * @property HashTable $properties_info
     * @property HashTable $constants_table
     */
    final class zend_class_entry
    {
        public string $type;
        public ?zend_string $name;
        public ?zend_class_entry $parent;
        public ?zend_string $parent_name;
        public int $refcount;
        public int $ce_flags;
        public ?int $ce_flags2;
        public int $default_properties_count;
        public int $default_static_members_count;
        /** @var array<int, zval>|null */
        public ?array $default_properties_table;
        /** @var array<int, zval>|null */
        public ?array $default_static_members_table;
        public ?zval $static_members_table__ptr;
        public HashTable $function_table;
        public HashTable $properties_info;
        public HashTable $constants_table;
    }

    /**
     * @property zval $uninitialized_zval
     * @property zval $error_zval
     * @property array<int, HashTable|null> $symtable_cache
     * @property array<int, HashTable>|null $symtable_cache_limit
     * @property array<int, HashTable>|null $symtable_cache_ptr
     * @property HashTable $symbol_table
     * @property HashTable $included_files
     * @property mixed $bailout
     * @property int $error_reporting
     * @property int $exit_status
     * @property HashTable|null $function_table
     * @property HashTable|null $class_table
     * @property HashTable|null $zend_constants
     */
    final class zend_executor_globals
    {
        public zval $uninitialized_zval;
        public zval $error_zval;
        /** @var array<int, HashTable|null> */
        public array $symtable_cache;
        /** @var array<int, HashTable>|null */
        public ?array $symtable_cache_limit;
        /** @var array<int, HashTable>|null */
        public ?array $symtable_cache_ptr;
        public HashTable $symbol_table;
        public HashTable $included_files;
        public mixed $bailout;
        public int $error_reporting;
        public int $exit_status;
        public ?HashTable $function_table;
        public ?HashTable $class_table;
        public ?HashTable $zend_constants;
    }
}

namespace FFI {
    abstract class CData {}
}

namespace {
    /**
     * @property int $executor_globals_id
     * @property int $executor_globals_offset
     * @property \Communism_FFI\zend_executor_globals $executor_globals
     *
     * @method static FFI cdef(string $code, string $lib = '')
     * @method object tsrm_get_ls_cache()
     * @method \Communism_FFI\zend_string zend_strpprintf(int $max_len, string $format, mixed ...$values)
     * @method \Communism_FFI\zend_class_entry zend_lookup_class(\Communism_FFI\zend_string $name)
     * @method void free_estring(object $foo)
     * @method \Communism_FFI\zend_function|null zend_hash_str_find_ptr_lc(ptr<\Communism_FFI\HashTable> $ht, string $str, int $len)
     * @method \Communism_FFI\zval|null zend_hash_str_find(ptr<\Communism_FFI\HashTable> $ht, string $key, int $len)
     * @method \Communism_FFI\Bucket|null zend_hash_set_bucket_key(\Communism_FFI\HashTable $ht, \Communism_FFI\Bucket $p, \Communism_FFI\zend_string $key)
     * @method void zend_class_init_statics(\Communism_FFI\zend_class_entry $class_type)
     * @method void zend_jit_blacklist_function(\Communism_FFI\zend_op_array $op_array)
     * @method static string string(\Communism_FFI\ptr<\Communism_FFI\char> $ptr, int $len)
     * @method string|null zend_get_opcode_name(int $opcode)
     *
     * @phpstan-method (
     *     $type is 'Bucket *' ? \Communism_FFI\Bucket :
     *     ($type is 'zend_executor_globals *' ? \Communism_FFI\zend_executor_globals :
     *     ($type is 'zend_property_info *' ? \Communism_FFI\zend_property_info :
     *     ($type is 'void ***' ? array<int, object> :
     *     ($type is 'char *' ? \Communism_FFI\ptr<\Communism_FFI\char> :
     *     ($type is 'zend_function *' ? \Communism_FFI\zend_function :
     *     object)))))
     * ) cast(string $type, object|bool|float|int|null $ptr)
     */
    final class FFI
    {
        public int $executor_globals_id;
        public int $executor_globals_offset;
        public \Communism_FFI\zend_executor_globals $executor_globals;

        /**
         * Undocumented function
         *
         * @template T of object
         * @param T $val
         * @return \Communism_FFI\ptr<T>
         */
        public static function addr(object $val): object {}
    }
}
