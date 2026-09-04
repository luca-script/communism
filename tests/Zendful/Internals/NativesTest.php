<?php

declare(strict_types=1);

use Zendful\Internals\Natives;

describe('Natives', function (): void {
    covers(Natives::class);

    it('exposes validated fallback globals and ABI layout selection', function (): void {
        $supportsPhp86 = new ReflectionMethod(Natives::class, 'supportsPhp86');
        $supportsPhp86->setAccessible(true);

        expect($supportsPhp86->invoke(null))->toBeBool()
            ->and(Natives::ffi())->toBeInstanceOf(FFI::class)
            ->and(Natives::compilerGlobals())->toBeObject();
    });

    it('selects platform ABI conventions and fallback library names', function (): void {
        $callingConvention = new ReflectionMethod(Natives::class, 'callingConvention');
        $libraries = new ReflectionMethod(Natives::class, 'fallbackLibraries');
        $callingConvention->setAccessible(true);
        $libraries->setAccessible(true);

        expect($callingConvention->invoke(null, 'Linux', 4))->toBe('__attribute__((fastcall))')
            ->and($callingConvention->invoke(null, 'Linux', 8))->toBe('')
            ->and($callingConvention->invoke(null, 'Windows', 8))->toBe('__vectorcall')
            ->and($callingConvention->invoke(null, 'Other', 8))->toBe('')
            ->and($libraries->invoke(null, 'Windows', true, 4))->toBe(['php8ts', 'php8.4ts'])
            ->and($libraries->invoke(null, 'Windows', false, 4))->toBe(['php8', 'php8.4'])
            ->and($libraries->invoke(null, 'Linux', true, 5))->toBe([null, 'libphp8.5.so', 'libphp8.so', 'libphp.so'])
            ->and($libraries->invoke(null, 'Other', false, 5))->toBe([null, 'php8.5', 'php8', 'php']);
    });

    it('exposes the complete named Zend constant surface without magic values', function (): void {
        $constants = (new ReflectionClass(Natives::class))->getConstants();

        expect($constants)->toHaveKeys([
            'ZEND_IS_UNUSED',
            'ZEND_IS_CONST',
            'ZEND_IS_TMP_VAR',
            'ZEND_IS_VAR',
            'ZEND_IS_CV',
            'ZEND_OPERAND_TYPES',
            'ZEND_FUNCTION_TYPE_USER',
            'ZEND_CLASS_TYPE_INTERNAL',
            'ZEND_CLASS_TYPE_USER',
            'ZEND_ACC_PUBLIC',
            'ZEND_ACC_PROTECTED',
            'ZEND_ACC_PRIVATE',
            'ZEND_ACC_STATIC',
            'ZEND_ACC_FINAL',
            'ZEND_ACC_ABSTRACT',
            'ZEND_ACC_READONLY',
            'ZEND_ACC_INTERFACE',
            'ZEND_ACC_TRAIT',
            'ZEND_ACC_ENUM',
            'ZEND_TYPE_UNDEF',
            'ZEND_TYPE_NULL',
            'ZEND_TYPE_FALSE',
            'ZEND_TYPE_TRUE',
            'ZEND_TYPE_LONG',
            'ZEND_TYPE_DOUBLE',
            'ZEND_TYPE_STRING',
            'ZEND_TYPE_ARRAY',
            'ZEND_TYPE_OBJECT',
            'ZEND_TYPE_RESOURCE',
            'ZEND_TYPE_CONSTANT_AST',
            'ZEND_MAX_SAFE_STRING_LENGTH',
        ])->and(Natives::ZEND_OPERAND_TYPES)->toBe([
            Natives::ZEND_IS_UNUSED,
            Natives::ZEND_IS_CONST,
            Natives::ZEND_IS_TMP_VAR,
            Natives::ZEND_IS_VAR,
            Natives::ZEND_IS_CV,
        ]);
    });
});
