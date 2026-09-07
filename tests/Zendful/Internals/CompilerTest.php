<?php

declare(strict_types=1);

use Zendful\Internals\Compiler as ZendfulCompiler;
use Zendful\Internals\Natives;

describe('Compiler', function (): void {
    covers([ZendfulCompiler::class, Natives::class]);

    it('compiles valid source into detached Zendful data', function (): void {
        $compiled = ZendfulCompiler::compileFile(__DIR__ . '/../../StaticAnalysis/compile-only.php');

        expect($compiled->opArray)->toBeInstanceOf(\Zendful\CompiledOpArrayHandle::class)
            ->and($compiled->classes)->toHaveCount(1)
            ->and($compiled->classes[0]->method('run'))->toBeInstanceOf(\Zendful\CompiledMethodHandle::class)
            ->and($compiled->functions)->toBeArray();
    });

    it('detaches top-level functions and multiple class declarations', function (): void {
        $compiled = ZendfulCompiler::compileFile(__DIR__ . '/../../StaticAnalysis/compile-targets.php');

        expect($compiled->functions)->toHaveCount(1)
            ->and($compiled->functions[0]->name)->toBe('CompileFixture\\combine')
            ->and($compiled->classes)->toHaveCount(2)
            ->and($compiled->classes[0]->name)->toBe('CompileFixture\\FirstTarget')
            ->and($compiled->classes[1]->name)->toBe('CompileFixture\\SecondTarget');
    });

    it('reports compiler failure for syntax-invalid source', function (): void {
        expect(static fn(): mixed => ZendfulCompiler::compileFile(__DIR__ . '/../Fixtures/uncompilable.txt'))
            ->toThrow(ParseError::class);
    });

    it('rejects oversized compiler strings before reading their payload', function (): void {
        $ffi = Natives::ffi();
        $string = new class {
            public int $len;
        };
        $string->len = Natives::ZEND_MAX_SAFE_STRING_LENGTH + 1;
        $method = new \ReflectionMethod(ZendfulCompiler::class, 'zendString');

        expect(static fn(): mixed => $method->invoke(null, $string, $ffi))
            ->toThrow(RuntimeException::class);
    });

    it('accepts empty compiler strings without reading a payload', function (): void {
        $ffi = Natives::ffi();
        $string = new class {
            public int $len = 0;
        };
        $method = new ReflectionMethod(ZendfulCompiler::class, 'zendString');

        expect($method->invoke(null, $string, $ffi))->toBe('');
    });

    it('fails closed for absent compiler hash tables and preserves detached entry keys', function (): void {
        $invoke = static function (string $name, mixed ...$arguments): mixed {
            $method = new ReflectionMethod(ZendfulCompiler::class, $name);

            return $method->invoke(null, ...$arguments);
        };
        $ffi = Natives::ffi();
        $entries = [
            ['key' => 'first', 'length' => 5, 'pointer' => new stdClass()],
            ['key' => 'second', 'length' => 6, 'pointer' => new stdClass()],
        ];

        expect($invoke('entries', null, $ffi))->toBe([])
            ->and($invoke('keys', null, $ffi))->toBe([])
            ->and($invoke('newEntries', null, [], $ffi))->toBe([])
            ->and($invoke('entryKeys', $entries))->toBe([
                ['key' => 'first', 'length' => 5],
                ['key' => 'second', 'length' => 6],
            ])
            ->and($invoke('removeEntries', null, [], $ffi))->toBeNull();
    });

    it('keeps compiler cleanup idempotent when no Zend resources were acquired', function (): void {
        $invoke = static function (string $name, mixed ...$arguments): mixed {
            $method = new ReflectionMethod(ZendfulCompiler::class, $name);

            return $method->invoke(null, ...$arguments);
        };
        $ffi = Natives::ffi();
        $handle = $ffi->new('zend_file_handle');
        $globals = new stdClass();
        $globals->compiler_options = 0;
        $classEntries = [];
        $functionEntries = [];
        $cleanup = new ReflectionMethod(ZendfulCompiler::class, 'cleanup');

        expect($invoke('restoreCompilerOptions', $globals, 123))->toBeNull()
            ->and($globals->compiler_options)->toBe(123)
            ->and($invoke('releaseArena', $globals, null, null, $ffi))->toBeNull()
            ->and($cleanup->invokeArgs(null, [
                $ffi,
                $handle,
                null,
                null,
                null,
                &$classEntries,
                &$functionEntries,
                null,
                [],
                null,
                [],
                null,
                null,
            ]))->toBeNull();
    });

    it('continues compiler cleanup after each unsafe operation fails', function (): void {
        $ffi = Natives::ffi();
        $handle = $ffi->new('zend_file_handle');
        $globals = new stdClass();
        $globals->compiler_options = 0;
        $classTable = new stdClass();
        $classTable->arData = new stdClass();
        $functionTable = new stdClass();
        $functionTable->arData = new stdClass();
        $classEntries = [];
        $functionEntries = [];
        $cleanup = new ReflectionMethod(ZendfulCompiler::class, 'cleanup');

        $failure = $cleanup->invokeArgs(null, [
            $ffi,
            $handle,
            $globals,
            0,
            new stdClass(),
            &$classEntries,
            &$functionEntries,
            $classTable,
            [['key' => 'class', 'length' => 5]],
            $functionTable,
            [['key' => 'function', 'length' => 8]],
            null,
            null,
        ]);

        expect($failure)->toBeInstanceOf(Throwable::class)
            ->and($classEntries)->toBe([])
            ->and($functionEntries)->toBe([])
            ->and($globals->compiler_options)->toBe(0);
    });

    it('restores an unchanged compiler arena checkpoint without freeing it', function (): void {
        $ffi = Natives::ffi();
        /** @var \Zendful_FFI\zend_arena $arena */
        $arena = $ffi->new('zend_arena');
        $arenaPointer = FFI::addr($arena);
        $checkpoint = $ffi->cast('char *', $arenaPointer);
        $checkpointAddress = $ffi->cast('uintptr_t', $checkpoint)->cdata;
        $globals = new stdClass();
        $globals->arena = $arenaPointer;

        $releaseArena = new ReflectionMethod(ZendfulCompiler::class, 'releaseArena');

        $releaseArena->invoke(null, $globals, $arenaPointer, $checkpointAddress, $ffi);

        expect($globals->arena)->toBe($arenaPointer)
            ->and($arena->ptr)->toEqual($ffi->cast('char *', $checkpointAddress));
    });

    it('reports the first hash-table cleanup failure for either declaration table', function (): void {
        $ffi = Natives::ffi();
        $handle = $ffi->new('zend_file_handle');
        $cleanup = new ReflectionMethod(ZendfulCompiler::class, 'cleanup');

        foreach (['class' => 'classKeys', 'function' => 'functionKeys'] as $table => $keyName) {
            $tableObject = new stdClass();
            $tableObject->arData = new stdClass();
            $classTable = $table === 'class' ? $tableObject : null;
            $functionTable = $table === 'function' ? $tableObject : null;
            $classKeys = $table === 'class' ? [['key' => 'class', 'length' => 5]] : [];
            $functionKeys = $table === 'function' ? [['key' => 'function', 'length' => 8]] : [];
            $classEntries = [];
            $functionEntries = [];

            $failure = $cleanup->invokeArgs(null, [
                $ffi,
                $handle,
                null,
                null,
                null,
                &$classEntries,
                &$functionEntries,
                $classTable,
                $classKeys,
                $functionTable,
                $functionKeys,
                null,
                null,
            ]);

            expect($failure)->toBeInstanceOf(Throwable::class, $keyName);
        }
    });

    it('skips malformed compiler hash buckets with null runtime pointers', function (): void {
        $ffi = Natives::ffi();
        /** @var \Zendful_FFI\HashTable $table */
        $table = $ffi->new('HashTable');
        /** @var \Zendful_FFI\Bucket $bucket */
        $bucket = $ffi->new('Bucket');
        /** @var \Zendful_FFI\zval $dummy */
        $dummy = $ffi->new('zval');
        $table->arData = FFI::addr($bucket);
        $table->nNumUsed = 1;
        $bucket->val->value->ptr = FFI::addr($dummy);
        $bucket->key = $ffi->cast('zend_string *', 0);

        $entries = new ReflectionMethod(ZendfulCompiler::class, 'entries');

        expect($entries->invoke(null, $table, $ffi))->toBe([]);
    });

    it('skips compiler declarations without names or user bytecode', function (): void {
        $ffi = Natives::ffi();
        /** @var \Zendful_FFI\zend_class_entry $class */
        $class = $ffi->new('zend_class_entry');
        /** @var \Zendful_FFI\zend_function $function */
        $function = $ffi->new('zend_function');
        $function->type = Natives::ZEND_CLASS_TYPE_INTERNAL;

        $classes = new ReflectionMethod(ZendfulCompiler::class, 'classSnapshots');
        $functions = new ReflectionMethod(ZendfulCompiler::class, 'functionSnapshots');
        $entries = [[
            'key' => 'declaration',
            'length' => 11,
            'pointer' => FFI::addr($class),
        ]];
        $functionEntries = [[
            'key' => 'function',
            'length' => 8,
            'pointer' => FFI::addr($function),
        ]];

        expect($classes->invoke(null, $entries, $ffi))->toBe([])
            ->and($functions->invoke(null, $functionEntries, $ffi))->toBe([]);
    });
});
