<?php

declare(strict_types=1);

namespace Communism_FFI {
    /**
     * @template T
     * @mixin T
     * @implements \ArrayAccess<int, T>
     */
    final class ptr implements \ArrayAccess
    {
        public function offsetExists(mixed $offset): bool {}

        /** @return T */
        public function offsetGet(mixed $offset): mixed {}

        /** @param T $value */
        public function offsetSet(mixed $offset, mixed $value): void {}

        public function offsetUnset(mixed $offset): void {}
    }

    /** @implements \ArrayAccess<int, zend_op> */
    final class zend_op_pointer implements \ArrayAccess
    {
        public function offsetExists(mixed $offset): bool {}
        public function offsetGet(mixed $offset): zend_op {}
        public function offsetSet(mixed $offset, mixed $value): void {}
        public function offsetUnset(mixed $offset): void {}
    }

    /** @implements \ArrayAccess<int, zval> */
    final class zval_pointer implements \ArrayAccess
    {
        public function offsetExists(mixed $offset): bool {}
        public function offsetGet(mixed $offset): zval {}
        public function offsetSet(mixed $offset, mixed $value): void {}
        public function offsetUnset(mixed $offset): void {}
    }

    /** @implements \ArrayAccess<int, zend_string> */
    final class zend_string_pointer implements \ArrayAccess
    {
        public function offsetExists(mixed $offset): bool {}
        public function offsetGet(mixed $offset): zend_string {}
        public function offsetSet(mixed $offset, mixed $value): void {}
        public function offsetUnset(mixed $offset): void {}
    }

    /** @implements \ArrayAccess<int, zend_live_range> */
    final class zend_live_range_pointer implements \ArrayAccess
    {
        public function offsetExists(mixed $offset): bool {}
        public function offsetGet(mixed $offset): zend_live_range {}
        public function offsetSet(mixed $offset, mixed $value): void {}
        public function offsetUnset(mixed $offset): void {}
    }

    final class zend_live_range
    {
        public int $var;
        public int $start;
        public int $end;
    }

    /** @implements \ArrayAccess<int, zend_try_catch_element> */
    final class zend_try_catch_pointer implements \ArrayAccess
    {
        public function offsetExists(mixed $offset): bool {}
        public function offsetGet(mixed $offset): zend_try_catch_element {}
        public function offsetSet(mixed $offset, mixed $value): void {}
        public function offsetUnset(mixed $offset): void {}
    }

    final class zend_try_catch_element
    {
        public int $try_op;
        public int $catch_op;
        public int $finally_op;
        public int $finally_end;
    }

    final class MODULEENTRY32A
    {
        public int $dwSize;
        public object $szModule;
        public object $szExePath;
    }

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
        public int $lval;
        public float $dval;
    }

    /**
     * @property zend_value $value
     * @property object $u1
     * @property object $u2
     */
    final class zval
    {
        public zend_value $value;
        public zval_u1 $u1;
        public zval_u2 $u2;
    }

    final class zval_u1
    {
        public int $type_info;
        public zval_u1_v $v;
    }

    final class zval_u1_v
    {
        public int $type;
    }

    final class zval_u2
    {
        public int $extra;
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

    final class HashTable
    {
        public int $nNumUsed;
        public int $nNumOfElements;
        /** @var ptr<Bucket> */
        public ?object $arData;
        public zval_pointer $arPacked;
    }

    final class zend_file_handle {}

    final class zend_compiler_globals
    {
        public ?HashTable $function_table;
        public ?HashTable $class_table;
        public int $compiler_options;
        public ?zend_arena $arena;
    }

    final class zend_arena
    {
        public object $ptr;
        public object $end;
        public ?zend_arena $prev;
    }

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
        /** @var array<int, int> */
        public array $arg_flags;
        public int $fn_flags;
        public ?zend_string $function_name;
        public ?zend_class_entry $scope;
        public ?zend_function $prototype;
        public ?zend_string $doc_comment;
        public ?zend_property_info $prop_info;
        public int $num_args;
        public int $required_num_args;
        public mixed $arg_info;
        public ?HashTable $attributes;
        public zend_op_array $op_array;
    }

    /**
     * @property int $last
     * @property int $fn_flags
     * @property int $num_args
     * @property int $last_var
     * @property int $T
     * @property int $cache_size
     * @property object|null $run_time_cache__ptr
     * @property zend_string|null $filename
     * @property int $line_start
     * @property int $line_end
     * @property \Communism_FFI\zend_op_pointer|null $opcodes
     */
    final class zend_op_array
    {
        public int $last;
        public int $fn_flags;
        public int $last_literal;
        public int $num_args;
        public int $last_var;
        public int $T;
        public int $cache_size;
        public ?object $run_time_cache__ptr;
        public ?zend_string $filename;
        public int $line_start;
        public int $line_end;
        public int $last_live_range;
        public int $last_try_catch;
        public ?zend_live_range_pointer $live_range;
        public ?zend_try_catch_pointer $try_catch_array;
        public ?zend_op_pointer $opcodes;
        public ?zval_pointer $literals;
        public ?zend_string_pointer $vars;
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
        public object $handler;
        public int $opcode;
        public int $op1_type;
        public int $op2_type;
        public int $result_type;
        public int $extended_value;
        public int $lineno;
        public znode_op $op1;
        public znode_op $op2;
        public znode_op $result;
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
        public ?zval_pointer $zv;
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
    abstract class CData
    {
        /** @var object */
        public object $dlpi_name;
        public int $cdata;
    }
}

namespace {
    /**
     * @property int $executor_globals_id
     * @property int $executor_globals_offset
     * @property \Communism_FFI\zend_executor_globals $executor_globals
     *
     * @method static FFI cdef(string $code, string|null $lib = null)
     * @method \Communism_FFI\ptr<null> tsrm_get_ls_cache()
     * @method \Communism_FFI\zend_string zend_strpprintf(int $max_len, string $format, mixed ...$values)
     * @method \Communism_FFI\zend_class_entry zend_lookup_class(\Communism_FFI\zend_string $name)
     * @method void free_estring(object $foo)
     * @method \Communism_FFI\zend_function|null zend_hash_str_find_ptr_lc(\Communism_FFI\ptr<\Communism_FFI\HashTable>|\Communism_FFI\HashTable $ht, string $str, int $len)
    * @method \Communism_FFI\zval|null zend_hash_str_find(\Communism_FFI\ptr<\Communism_FFI\HashTable>|\Communism_FFI\HashTable $ht, string $key, int $len)
     * @method \Communism_FFI\zval|null zend_hash_str_update(\Communism_FFI\ptr<\Communism_FFI\HashTable>|\Communism_FFI\HashTable $ht, string $key, int $len, object $value)
     * @method \Communism_FFI\Bucket|null zend_hash_set_bucket_key(\Communism_FFI\HashTable $ht, \Communism_FFI\Bucket $p, \Communism_FFI\zend_string $key)
     * @method void zend_class_init_statics(\Communism_FFI\zend_class_entry $class_type)
     * @method void zend_hash_del_bucket(\Communism_FFI\HashTable $ht, object $bucket)
     * @method void zend_stream_init_filename(object $handle, string $filename)
     * @method void zend_destroy_file_handle(object $handle)
     * @method \Communism_FFI\zend_op_array|null compile_file(object $handle, int $type)
     * @method void destroy_op_array(\Communism_FFI\zend_op_array $op_array)
     * @method void zend_jit_blacklist_function(\Communism_FFI\zend_op_array $op_array)
     * @method int zend_hash_func(string $str, int $len)
     * @method \Communism_FFI\ptr<object> _emalloc(int $size)
     * @method void _efree(object $ptr)
     * @method static string string(object $ptr, int|null $len = null)
     * @method string|null zend_get_opcode_name(int $opcode)
     * @method int zend_get_opcode_id(string $name, int $length)
     * @method void zend_vm_set_opcode_handler(object $opcode)
     * @method object CreateToolhelp32Snapshot(int $flags, int $processId)
     * @method bool Module32First(object $snapshot, object $module)
     * @method bool Module32Next(object $snapshot, object $module)
     * @method bool CloseHandle(object $handle)
     * @method int dl_iterate_phdr(callable $callback, object|null $data)
     *
     * @phpstan-method (
     *     $type is 'Bucket *' ? \Communism_FFI\Bucket :
     *     ($type is 'HashTable *' ? \Communism_FFI\HashTable :
     *     ($type is 'zend_executor_globals *' ? \Communism_FFI\zend_executor_globals :
     *     ($type is 'zend_compiler_globals *' ? \Communism_FFI\zend_compiler_globals :
     *     ($type is 'zend_class_entry *' ? \Communism_FFI\zend_class_entry :
     *     ($type is 'zend_file_handle *' ? \Communism_FFI\zend_file_handle :
     *     ($type is 'zend_property_info *' ? \Communism_FFI\zend_property_info :
     *     ($type is 'void ***' ? array<int, object> :
     *     ($type is 'char *' ? \Communism_FFI\ptr<\Communism_FFI\char> :
     *     ($type is 'zend_op *' ? \Communism_FFI\zend_op_pointer :
     *     ($type is 'zend_function *' ? \Communism_FFI\zend_function :
     *     ($type is 'zend_string *' ? \Communism_FFI\zend_string :
     *     ($type is 'zval *' ? \Communism_FFI\zval_pointer :
     *     ($type is 'uintptr_t' ? \FFI\CData :
     *     object))))))))))))
     * )) cast(string $type, object|bool|float|int|null|\Communism_FFI\ptr<null> $ptr)
     */
    final class FFI
    {
        public int $executor_globals_id;
        public int $executor_globals_offset;
        public \Communism_FFI\zend_executor_globals $executor_globals;
        public int $compiler_globals_offset;
        public \Communism_FFI\zend_compiler_globals $compiler_globals;

        /**
         * @phpstan-return (
         *     $type is 'zval' ? \Communism_FFI\zval :
         *     ($type is 'zend_function' ? \Communism_FFI\zend_function :
         *     ($type is 'MODULEENTRY32A' ? \Communism_FFI\MODULEENTRY32A : object))
         * )
         */
        public function new(string $type): object {}

        public static function sizeof(object $value): int {}

        public static function memcpy(object $to, object $from, int $size): void {}

        public static function isNull(object $ptr): bool {}

        /**
         * Get the address of an object
         *
         * @template T of object
         * @param T $val
         * @return \Communism_FFI\ptr<T>
         */
        public static function addr(object $val): object {}
    }
}
