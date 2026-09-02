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
 * File: Executor.php                                                         *
 * Consumer: Internal                                                         *
 * Purpose: Source file for Executor.php.                                     *
 *============================================================================*/

declare(strict_types=1);

namespace Zendful\Internals;

use FFI;
use InvalidArgumentException;
use RuntimeException;
use Throwable;
use Zendful\FunctionHandle;
use Zendful\CompiledOpArrayHandle;
use Zendful\AssemblyPlanHandle;
use Zendful\MethodHandle;
use Zendful\OpArrayHandle;
use Zendful\OpcodeHandle;
use Zendful\OperandHandle;
use Zendful\PropertyHandle;
use Zendful\ClassHandle;

use function strtolower;
use function sprintf;
use function strlen;
use function strcasecmp;
use function str_starts_with;
use function strtoupper;

/** The only layer allowed to translate handles into unsafe Zend operations. */
final class Executor
{
    /** @var array<string, true> */
    private static array $blacklistedMethods = [];

    /** @var array<string, true> */
    private static array $blacklistedClasses = [];

    private static function ffi(): FFI
    {
        return Natives::ffi();
    }

    public static function disableJitForMethod(MethodHandle $method): void
    {
        // @codeCoverageIgnoreStart
        // @codeCoverageIgnoreStart
        if (!function_exists('opcache_jit_blacklist')) {
            return;
        }
        // @codeCoverageIgnoreEnd

        $key = strtolower($method->className() . '::' . $method->methodName());
        if (isset(self::$blacklistedMethods[$key]) || !$method->isUserDefined() || !$method->hasBytecode()) {
            return;
        }

        $reflection = new \ReflectionMethod($method->className(), $method->methodName());
        $originalFlags = self::methodFlags($method);
        self::setMethodFlags($method, $originalFlags | Natives::ZEND_ACC_STATIC);
        try {
            $closure = $reflection->getClosure();
        } finally {
            self::setMethodFlags($method, $originalFlags);
        }

        opcache_jit_blacklist($closure);
        self::$blacklistedMethods[$key] = true;
        // @codeCoverageIgnoreEnd
    }

    public static function disableJitForFunction(FunctionHandle $function): void
    {
        // @codeCoverageIgnoreStart
        // @codeCoverageIgnoreStart
        if (!function_exists('opcache_jit_blacklist') || !$function->isUserDefined() || !$function->hasBytecode()) {
            return;
        }
        // @codeCoverageIgnoreEnd

        $key = strtolower($function->name());
        if (isset(self::$blacklistedMethods[$key])) {
            return;
        }

        $callable = $function->name();
        if (!is_callable($callable)) {
            return;
        }

        opcache_jit_blacklist(\Closure::fromCallable($callable));
        self::$blacklistedMethods[$key] = true;
        // @codeCoverageIgnoreEnd
    }

    public static function disableJitForClass(ClassHandle $class): void
    {
        // @codeCoverageIgnoreStart
        // @codeCoverageIgnoreStart
        if (!function_exists('opcache_jit_blacklist')) {
            return;
        }
        // @codeCoverageIgnoreEnd

        $key = strtolower($class->name());
        if (isset(self::$blacklistedClasses[$key])) {
            return;
        }

        if (!self::loadedClassExists($class->name())) {
            return;
        }
        $className = $class->name();
        self::assertLoadedClassName($className);
        $reflection = new \ReflectionClass($className);
        if ($reflection->isInternal()) {
            return;
        }

        foreach ($reflection->getMethods() as $method) {
            self::disableJitForMethod(new MethodHandle($class->name(), $method->getName()));
        }
        self::$blacklistedClasses[$key] = true;
        // @codeCoverageIgnoreEnd
    }

    /** Invalidate callers after mutating Zend-owned runtime structures. */
    private static function blacklistCurrentCallers(int $limit = Natives::ZEND_CALLER_BLACKLIST_DEPTH): void
    {
        // @codeCoverageIgnoreStart
        // @codeCoverageIgnoreStart
        if (!function_exists('opcache_jit_blacklist')) {
            return;
        }
        // @codeCoverageIgnoreEnd

        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS | DEBUG_BACKTRACE_PROVIDE_OBJECT, $limit) as $frame) {
            if (isset($frame['object']) && $frame['object'] instanceof \Closure) {
                opcache_jit_blacklist($frame['object']);
                continue;
            }

            $function = $frame['function'];

            $class = $frame['class'] ?? null;
            if (is_string($class)) {
                if (str_starts_with($class, __NAMESPACE__ . '\\')) {
                    continue;
                }

                if (!isset($frame['object'])) {
                    continue;
                }

                self::disableJitForMethod(new MethodHandle($class, $function));
                continue;
            }

            if (function_exists($function)) {
                opcache_jit_blacklist(\Closure::fromCallable($function));
            }
        }
        // @codeCoverageIgnoreEnd
    }

    public static function opcodeName(int $opcode): string
    {
        return Natives::ffi()->zend_get_opcode_name($opcode) ?? '';
    }

    public static function opcodeId(string $opcode): int
    {
        $name = strtoupper($opcode);
        if (!str_starts_with($name, 'ZEND_')) {
            $name = 'ZEND_' . $name;
        }

        $ffi = self::ffi();
        $id = $ffi->zend_get_opcode_id($name, strlen($name));
        if (self::opcodeName($id) !== $name) {
            throw new InvalidArgumentException(sprintf('Unknown opcode: %s', $opcode));
        }

        return $id;
    }

    public static function assemble(OpArrayHandle $target, AssemblyPlanHandle $plan): void
    {
        $ffi = self::ffi();
        $source = $target->source();
        $entry = self::entry($source, $ffi);
        if ($entry === null) {
            throw new InvalidArgumentException(sprintf('Callable does not exist: %s', self::sourceName($source)));
        }

        $function = $ffi->cast('zend_function *', $entry->value->ptr);
        if ($function->type !== Natives::ZEND_FUNCTION_TYPE_USER) {
            throw new RuntimeException(sprintf('Cannot assemble an internal callable: %s', self::sourceName($source)));
        }

        $opArray = $function->op_array;
        if (($opArray->fn_flags & Natives::ZEND_ACC_IMMUTABLE) !== 0) {
            // @codeCoverageIgnoreStart
            throw new RuntimeException('Cannot rewrite an OPcache-owned function. Disable OPcache before the target file is loaded.');
            // @codeCoverageIgnoreEnd
        }
        if ($opArray->opcodes === null) {
            // @codeCoverageIgnoreStart
            throw new RuntimeException('The runtime op array has no opcode storage');
            // @codeCoverageIgnoreEnd
        }

        $oldOpcodes = $opArray->opcodes;
        $oldInstructionCount = $opArray->last;
        $oldLiteralCount = $opArray->last_literal;
        $newLiteralCount = self::validateAssemblyPlan($plan, $oldInstructionCount, $oldLiteralCount);
        $oldLiterals = $opArray->literals;
        if ($oldLiteralCount > 0 && !is_object($oldLiterals)) {
            // @codeCoverageIgnoreStart
            throw new RuntimeException('The runtime op array has no literal storage');
            // @codeCoverageIgnoreEnd
        }

        if (count($plan->instructions) === $oldInstructionCount
            && $plan->literalValues === []
            && $newLiteralCount === $oldLiteralCount) {
            self::writeExisting($ffi, $plan->instructions, $oldOpcodes, $oldLiterals);
            $opArray->T = $plan->temporaryCount;
            $opArray->cache_size = $plan->cacheSize;
            if ($plan->cacheSize > 0) {
                // @codeCoverageIgnoreStart
                $opArray->fn_flags |= Natives::ZEND_ACC_HEAP_RT_CACHE;
                // @codeCoverageIgnoreEnd
            }
            self::blacklistCurrentCallers();
            return;
        }

        $opcodeSize = FFI::sizeof($ffi->new('zend_op'));
        $zvalSize = FFI::sizeof($ffi->new('zval'));
        $opcodeBytes = $opcodeSize * count($plan->instructions);
        $alignedOpcodeBytes = self::align($opcodeBytes, Natives::ZEND_OP_ALIGNMENT);
        $opcodeAllocation = null;
        $literalAllocation = null;
        $newLiterals = null;
        $installed = false;

        try {
            if (PHP_INT_SIZE === 4) {
                // @codeCoverageIgnoreStart
                $opcodeAllocation = $ffi->_emalloc(max(Natives::ZEND_MIN_ALLOCATION_SIZE, $opcodeBytes));
                $newOpcodes = $ffi->cast('zend_op *', $ffi->cast('char *', $opcodeAllocation));
                if ($newLiteralCount > 0) {
                    $literalAllocation = $ffi->_emalloc($zvalSize * $newLiteralCount);
                    $newLiterals = $ffi->cast('zval *', $ffi->cast('char *', $literalAllocation));
                } else {
                    $newLiterals = null;
                }
                // @codeCoverageIgnoreEnd
            } else {
                $opcodeAllocation = $ffi->_emalloc($alignedOpcodeBytes + $zvalSize * $newLiteralCount);
                $opcodeBase = $ffi->cast('char *', $opcodeAllocation);
                $newOpcodes = $ffi->cast('zend_op *', $opcodeBase);
                $newLiterals = $newLiteralCount === 0
                    ? null
                    : $ffi->cast('zval *', $ffi->cast('char *', $ffi->cast('uintptr_t', $opcodeBase)->cdata + $alignedOpcodeBytes));
            }

            if ($newLiteralCount > 0) {
                $allocatedLiterals = self::requireLiteralPointer($newLiterals);
                for ($index = 0; $index < $newLiteralCount; $index++) {
                    $allocatedLiterals[$index]->value->lval = 0;
                    $allocatedLiterals[$index]->u1->type_info = Natives::ZEND_TYPE_UNDEF;
                    $allocatedLiterals[$index]->u2->extra = 0;
                }
            }

            if ($oldLiteralCount > 0 && $newLiteralCount > 0) {
                $allocatedLiterals = self::requireLiteralPointer($newLiterals);
                for ($index = 0; $index < min($oldLiteralCount, $newLiteralCount); $index++) {
                    $sourceLiteral = $oldLiterals[$index];
                    $targetLiteral = $allocatedLiterals[$index];
                    if ($sourceLiteral->u1->v->type === Natives::ZEND_TYPE_STRING && $sourceLiteral->value->ptr !== null) {
                        $sourceString = $ffi->cast('zend_string *', $sourceLiteral->value->ptr);
                        // @codeCoverageIgnoreStart
                        if ($sourceString->len > Natives::ZEND_MAX_SAFE_STRING_LENGTH) {
                            throw new RuntimeException('Assembly source literal exceeds Zendful safety limits.');
                        }
                        // @codeCoverageIgnoreEnd
                        $sourceValue = self::zendString($ffi, $sourceString);
                        // @codeCoverageIgnoreStart
                        if (str_contains($sourceValue, "\0")) {
                            throw new RuntimeException('Assembly cannot copy literals containing NUL bytes.');
                        }
                        // @codeCoverageIgnoreEnd

                        $string = $ffi->zend_strpprintf(max(Natives::ZEND_MIN_ALLOCATION_SIZE, $sourceString->len + Natives::ZEND_MIN_ALLOCATION_SIZE), '%s', $sourceValue);
                        $string->h = $sourceString->h;
                        $targetLiteral->value->ptr = $string;
                        $targetLiteral->u1->type_info = $sourceLiteral->u1->type_info;
                        $targetLiteral->u2->extra = $sourceLiteral->u2->extra;
                    } else {
                        $targetLiteral->value->lval = $sourceLiteral->value->lval;
                        $targetLiteral->u1->type_info = $sourceLiteral->u1->type_info;
                        $targetLiteral->u2->extra = $sourceLiteral->u2->extra;
                        if (in_array($sourceLiteral->u1->v->type, [
                            Natives::ZEND_TYPE_ARRAY,
                            Natives::ZEND_TYPE_OBJECT,
                            Natives::ZEND_TYPE_RESOURCE,
                            Natives::ZEND_TYPE_CONSTANT_AST,
                        ], true)) {
                            // @codeCoverageIgnoreStart
                            $ffi->zval_copy_ctor_func(FFI::addr($targetLiteral));
                            // @codeCoverageIgnoreEnd
                        }
                    }
                }
            }

            foreach ($plan->literalValues as $slot => $value) {
                $allocatedLiterals = self::requireLiteralPointer($newLiterals);
                self::writeLiteral($ffi, $allocatedLiterals[$slot], $value);
            }

            foreach ($plan->instructions as $index => $instruction) {
                $opline = $newOpcodes[$index];
                $opline->opcode = $instruction['opcode'];
                $opline->op1_type = $instruction['operand1']['type'];
                $opline->op2_type = $instruction['operand2']['type'];
                $opline->result_type = $instruction['result']['type'];
                $opline->extended_value = $instruction['extendedValue'];
                $opline->lineno = $instruction['line'];
                self::writeOperand($ffi, $opline->result, $instruction['result'], $opline, $newLiterals);
                self::writeOperand($ffi, $opline->op1, $instruction['operand1'], $opline, $newLiterals);
                self::writeOperand($ffi, $opline->op2, $instruction['operand2'], $opline, $newLiterals);
            }
            foreach ($plan->instructions as $index => $_instruction) {
                $ffi->zend_vm_set_opcode_handler(FFI::addr($newOpcodes[$index]));
            }

            self::forgetLiterals($ffi, $oldLiterals, $oldLiteralCount);
            $opArray->opcodes = $newOpcodes;
            $opArray->last = count($plan->instructions);
            $opArray->last_literal = $newLiteralCount;
            $opArray->literals = $newLiterals;
            self::relocateMetadata($opArray, $plan->instructions, $oldInstructionCount);
            $opArray->T = $plan->temporaryCount;
            $opArray->cache_size = $plan->cacheSize;
            if ($plan->cacheSize > 0) {
                // @codeCoverageIgnoreStart
                $opArray->fn_flags |= Natives::ZEND_ACC_HEAP_RT_CACHE;
                // @codeCoverageIgnoreEnd
            }
            $installed = true;

            // @codeCoverageIgnoreStart
            if (PHP_INT_SIZE === 4 && $oldLiterals !== null) {
                $ffi->_efree($oldLiterals);
            }
            // @codeCoverageIgnoreEnd
            $ffi->_efree($oldOpcodes);
            self::blacklistCurrentCallers();
            // @codeCoverageIgnoreStart
        } catch (Throwable $exception) {
            if (!$installed) {
                if ($newLiterals !== null) {
                    for ($index = 0; $index < $newLiteralCount; $index++) {
                        $ffi->zval_ptr_dtor(FFI::addr($newLiterals[$index]));
                    }
                }
                if ($literalAllocation !== null) {
                    $ffi->_efree($literalAllocation);
                }
                if ($opcodeAllocation !== null) {
                    $ffi->_efree($opcodeAllocation);
                }
            }
            // @codeCoverageIgnoreEnd
            throw $exception;
        }
        // @codeCoverageIgnoreEnd
    }

    /**
     * @param \Zendful_FFI\zval_pointer|null $literals
     * @phpstan-return \Zendful_FFI\zval_pointer
     */
    private static function requireLiteralPointer(?object $literals): object
    {
        if ($literals === null) {
            // @codeCoverageIgnoreStart
            throw new RuntimeException('A literal was planned without literal storage');
            // @codeCoverageIgnoreEnd
        }

        return $literals;
    }

    private static function validateAssemblyPlan(AssemblyPlanHandle $plan, int $oldInstructionCount, int $oldLiteralCount): int
    {
        if ($plan->temporaryCount < Natives::ZEND_IS_UNUSED
            || $plan->temporaryCount > Natives::ZEND_UINT32_MAX
            || $plan->cacheSize < Natives::ZEND_IS_UNUSED
            || $plan->cacheSize > Natives::ZEND_INT32_MAX) {
            throw new InvalidArgumentException('Assembly sizes do not fit Zend runtime fields.');
        }

        return self::validateAssemblyData($plan->instructions, $plan->literalValues, $oldInstructionCount, $oldLiteralCount);
    }

    private static function validateAssemblyData(mixed $instructions, mixed $literalValues, int $oldInstructionCount, int $oldLiteralCount): int
    {
        if (!is_array($instructions) || !array_is_list($instructions)) {
            throw new InvalidArgumentException('Assembly instructions must be a list.');
        }

        if (!is_array($literalValues)) {
            throw new InvalidArgumentException('Assembly literals must be an array.');
        }

        $validatedInstructions = [];
        foreach ($instructions as $instruction) {
            $validatedInstructions[] = self::validateInstruction($instruction, $oldInstructionCount);
        }

        $newLiteralCount = 0;
        foreach ($literalValues as $slot => $value) {
            if (!is_int($slot) || $slot < 0) {
                throw new InvalidArgumentException('Assembly literal slots must fit inside the allocated literal pool.');
            }
            $newLiteralCount = max($newLiteralCount, $slot + 1);
            if ($value !== null && !is_scalar($value)) {
                throw new InvalidArgumentException('Assembly literals must be scalar values.');
            }
            if (is_string($value) && str_contains($value, "\0")) {
                throw new InvalidArgumentException('Assembly literals must not contain NUL bytes.');
            }
        }

        foreach ($validatedInstructions as $instruction) {
            foreach ([$instruction['result'], $instruction['operand1'], $instruction['operand2']] as $operand) {
                if ($operand['literalSlot'] !== null
                    && $operand['literalSlot'] < 0) {
                    // @codeCoverageIgnoreStart
                    throw new InvalidArgumentException('An assembly operand points outside the literal pool.');
                    // @codeCoverageIgnoreEnd
                }
                if ($operand['literalSlot'] !== null) {
                    $newLiteralCount = max($newLiteralCount, $operand['literalSlot'] + 1);
                }
                if ($operand['kind'] === 'constant'
                    && $operand['literalSlot'] !== null
                    && $operand['literalSlot'] >= $oldLiteralCount
                    && !array_key_exists($operand['literalSlot'], $literalValues)) {
                    throw new InvalidArgumentException('A new constant operand has no initialized literal slot.');
                }
            }
        }

        return $newLiteralCount;
    }

    /** @return array{opcode: int, extendedValue: int, line: int, originalIndex: int|null, result: array{type: int, kind: string, value: mixed, rawValue: int|null, literalSlot: int|null}, operand1: array{type: int, kind: string, value: mixed, rawValue: int|null, literalSlot: int|null}, operand2: array{type: int, kind: string, value: mixed, rawValue: int|null, literalSlot: int|null}} */
    private static function validateInstruction(mixed $instruction, int $oldInstructionCount): array
    {
        if (!is_array($instruction)
            || !array_key_exists('opcode', $instruction)
            || !array_key_exists('extendedValue', $instruction)
            || !array_key_exists('line', $instruction)
            || !array_key_exists('originalIndex', $instruction)
            || !array_key_exists('result', $instruction)
            || !array_key_exists('operand1', $instruction)
            || !array_key_exists('operand2', $instruction)
            || !is_int($instruction['opcode'])
            || !is_int($instruction['extendedValue'])
            || !is_int($instruction['line'])
            || ($instruction['originalIndex'] !== null && !is_int($instruction['originalIndex']))) {
            throw new InvalidArgumentException('Assembly instructions have an invalid shape.');
        }

        if ($instruction['opcode'] < Natives::ZEND_IS_UNUSED
            || $instruction['opcode'] > Natives::ZEND_OPCODE_MAX
            || $instruction['extendedValue'] < Natives::ZEND_IS_UNUSED
            || $instruction['extendedValue'] > Natives::ZEND_UINT32_MAX
            || $instruction['line'] < Natives::ZEND_IS_UNUSED
            || $instruction['line'] > Natives::ZEND_UINT32_MAX) {
            throw new InvalidArgumentException('Assembly instruction values do not fit Zend opcodes.');
        }

        if ($instruction['originalIndex'] !== null
            && ($instruction['originalIndex'] < 0 || $instruction['originalIndex'] >= $oldInstructionCount)) {
            throw new InvalidArgumentException('An assembly instruction has an invalid original index.');
        }

        return [
            'opcode' => $instruction['opcode'],
            'extendedValue' => $instruction['extendedValue'],
            'line' => $instruction['line'],
            'originalIndex' => $instruction['originalIndex'],
            'result' => self::validateOperand($instruction['result'], true),
            'operand1' => self::validateOperand($instruction['operand1']),
            'operand2' => self::validateOperand($instruction['operand2']),
        ];
    }

    /** @return array{type: int, kind: string, value: mixed, rawValue: int|null, literalSlot: int|null} */
    private static function validateOperand(mixed $operand, bool $allowSmartBranch = false): array
    {
        if (!is_array($operand)
            || !array_key_exists('type', $operand)
            || !array_key_exists('kind', $operand)
            || !array_key_exists('value', $operand)
            || !array_key_exists('rawValue', $operand)
            || !array_key_exists('literalSlot', $operand)) {
            throw new InvalidArgumentException('Assembly operands have an invalid shape.');
        }

        if (!is_int($operand['type'])
            || !self::isValidOperandType($operand['type'], $allowSmartBranch)
            || !is_string($operand['kind'])
            || !in_array($operand['kind'], ['unused', 'constant', 'temporary', 'variable', 'cv', 'raw'], true)
            || ($operand['rawValue'] !== null && !is_int($operand['rawValue']))
            || ($operand['literalSlot'] !== null && !is_int($operand['literalSlot']))) {
            throw new InvalidArgumentException(sprintf(
                'Assembly operands have invalid values (type=%s, kind=%s).',
                var_export($operand['type'], true),
                var_export($operand['kind'], true),
            ));
        }

        $expectedType = match ($operand['kind']) {
            'unused' => Natives::ZEND_IS_UNUSED,
            'constant' => Natives::ZEND_IS_CONST,
            'temporary' => Natives::ZEND_IS_TMP_VAR,
            'variable' => Natives::ZEND_IS_VAR,
            'cv' => Natives::ZEND_IS_CV,
            'raw' => null,
        };
        if ($expectedType !== null && $operand['type'] !== $expectedType) {
            throw new InvalidArgumentException('Assembly operand kind and type do not agree.');
        }

        if ($operand['kind'] === 'constant') {
            if ($operand['type'] !== Natives::ZEND_IS_CONST
                || $operand['literalSlot'] === null
                || ($operand['value'] !== null && !is_scalar($operand['value']))) {
                throw new InvalidArgumentException('Constant operands have invalid values.');
            }
            return [
                'type' => $operand['type'],
                'kind' => $operand['kind'],
                'value' => $operand['value'],
                'rawValue' => $operand['rawValue'],
                'literalSlot' => $operand['literalSlot'],
            ];
        }

        if ($operand['rawValue'] === null && !is_int($operand['value'])) {
            throw new InvalidArgumentException('Non-constant operands require an integer runtime value.');
        }

        $runtimeValue = $operand['rawValue'] ?? $operand['value'];
        if ($runtimeValue < Natives::ZEND_IS_UNUSED
            || $runtimeValue > Natives::ZEND_UINT32_MAX) {
            throw new InvalidArgumentException('Non-constant operands must fit Zend unsigned operand fields.');
        }

        return [
            'type' => $operand['type'],
            'kind' => $operand['kind'],
            'value' => $operand['value'],
            'rawValue' => $operand['rawValue'],
            'literalSlot' => $operand['literalSlot'],
        ];
    }

    private static function isValidOperandType(int $type, bool $allowSmartBranch): bool
    {
        if (in_array($type, Natives::ZEND_OPERAND_TYPES, true)) {
            return true;
        }

        if (!$allowSmartBranch
            || ($type & ~(Natives::ZEND_OPERAND_TYPE_MASK | Natives::ZEND_SMART_BRANCH_MASK)) !== 0) {
            return false;
        }

        $baseType = $type & Natives::ZEND_OPERAND_TYPE_MASK;
        $smartBranch = $type & Natives::ZEND_SMART_BRANCH_MASK;

        return in_array($baseType, Natives::ZEND_OPERAND_TYPES, true)
            && in_array($smartBranch, [Natives::ZEND_IS_SMART_BRANCH_JMPZ, Natives::ZEND_IS_SMART_BRANCH_JMPNZ], true);
    }

    public static function functionExists(FunctionHandle $function): bool
    {
        $name = strtolower($function->name());
        $ffi = self::ffi();
        $table = self::functionTable($ffi);

        return $ffi->zend_hash_str_find($table, $name, strlen($name)) !== null;
    }

    public static function framelessFunction(int $index): ?FunctionHandle
    {
        $name = Natives::framelessFunctionName($index);
        if ($name === null) {
            return null;
        }

        return new FunctionHandle($name);
    }

    public static function isUserDefined(FunctionHandle $function): bool
    {
        $name = strtolower($function->name());
        $ffi = self::ffi();
        $table = self::functionTable($ffi);
        $entry = $ffi->zend_hash_str_find($table, $name, strlen($name));
        if ($entry === null) {
            return false;
        }

        $runtimeFunction = $ffi->cast('zend_function *', $entry->value->ptr);

        return $runtimeFunction->type === Natives::ZEND_FUNCTION_TYPE_USER;
    }

    public static function methodExists(MethodHandle $method): bool
    {
        return self::methodEntry($method) !== null;
    }

    public static function propertyExists(PropertyHandle $property): bool
    {
        return self::propertyInfo($property) !== null;
    }

    public static function classExists(ClassHandle $class): bool
    {
        return self::classInfo($class) !== null;
    }

    public static function implementInterface(ClassHandle $class, ClassHandle $interface): void
    {
        $classInfo = self::writableClassInfo($class);
        $interfaceInfo = self::classInfo($interface);
        if ($interfaceInfo === null || ord($interfaceInfo->type) !== Natives::ZEND_CLASS_TYPE_USER
            || ($interfaceInfo->ce_flags & Natives::ZEND_ACC_INTERFACE) === 0
        ) {
            throw new InvalidArgumentException(sprintf('Not a declared user interface: %s', $interface->name()));
        }
        $interfaces = class_implements($class->name(), false);
        if ($interfaces !== false && in_array($interface->name(), $interfaces, true)) {
            return;
        }

        self::ffi()->zend_class_implements($classInfo, 1, $interfaceInfo);
    }

    private static function classFlags(ClassHandle $class): int
    {
        $info = self::classInfo($class);
        if ($info === null) {
            throw new InvalidArgumentException(sprintf('Class does not exist: %s', $class->name()));
        }

        return $info->ce_flags;
    }

    private static function setClassFlags(ClassHandle $class, int $flags): void
    {
        $info = self::writableClassInfo($class);

        $info->ce_flags = $flags;
    }

    /** @phpstan-return \Zendful_FFI\zend_class_entry */
    private static function writableClassInfo(ClassHandle $class): object
    {
        $info = self::classInfo($class);
        if ($info === null) {
            throw new InvalidArgumentException(sprintf('Class does not exist: %s', $class->name()));
        }
        if (ord($info->type) !== Natives::ZEND_CLASS_TYPE_USER) {
            throw new InvalidArgumentException(sprintf('Internal class cannot be modified: %s', $class->name()));
        }
        if (($info->ce_flags & Natives::ZEND_ACC_IMMUTABLE) !== 0) {
            throw new InvalidArgumentException(sprintf('Class is immutable and cannot be modified: %s', $class->name()));
        }

        return $info;
    }

    public static function classIsImmutable(ClassHandle $class): bool
    {
        return (self::classFlags($class) & Natives::ZEND_ACC_IMMUTABLE) !== 0;
    }

    public static function setClassFinal(ClassHandle $class, bool $enabled): void
    {
        $flags = self::classFlags($class);
        if ($enabled && ($flags & Natives::ZEND_ACC_ABSTRACT) !== 0) {
            throw new InvalidArgumentException(sprintf(
                'An abstract class cannot be made final: %s',
                $class->name(),
            ));
        }
        self::setClassFlags($class, $enabled ? ($flags | Natives::ZEND_ACC_FINAL) : ($flags & ~Natives::ZEND_ACC_FINAL));
    }

    public static function setClassReadonly(ClassHandle $class, bool $enabled): void
    {
        $flags = self::classFlags($class);
        if ($enabled) {
            $className = $class->name();
            self::assertLoadedClassName($className);
            $reflection = new \ReflectionClass($className);
            $parent = $reflection->getParentClass();
            if ($parent !== false && !$parent->isReadOnly()) {
                throw new InvalidArgumentException(sprintf(
                    'A readonly class must extend a readonly class: %s',
                    $class->name(),
                ));
            }
            foreach ($reflection->getProperties() as $property) {
                if ($property->isStatic()
                    || !$property->hasType()
                    || $property->hasDefaultValue()) {
                    throw new InvalidArgumentException(sprintf(
                        'Class properties are incompatible with readonly class semantics: %s::$%s',
                        $property->getDeclaringClass()->getName(),
                        $property->getName(),
                    ));
                }
            }
        }
        self::setClassFlags($class, $enabled ? ($flags | Natives::ZEND_ACC_READONLY_CLASS) : ($flags & ~Natives::ZEND_ACC_READONLY_CLASS));
    }

    public static function setClassAbstract(ClassHandle $class, bool $enabled): void
    {
        $flags = self::classFlags($class);
        if ($enabled && ($flags & Natives::ZEND_ACC_FINAL) !== 0) {
            throw new InvalidArgumentException(sprintf(
                'A final class cannot be made abstract: %s',
                $class->name(),
            ));
        }
        self::setClassFlags($class, $enabled ? ($flags | Natives::ZEND_ACC_ABSTRACT) : ($flags & ~Natives::ZEND_ACC_ABSTRACT));
    }

    public static function setClassKind(ClassHandle $class, ?string $kind): void
    {
        $kindFlag = match ($kind) {
            null => Natives::ZEND_ACC_NONE,
            'interface' => Natives::ZEND_ACC_INTERFACE,
            'trait' => Natives::ZEND_ACC_TRAIT,
            'enum' => Natives::ZEND_ACC_ENUM,
            default => throw new InvalidArgumentException('Invalid class kind.'),
        };
        $flags = self::classFlags($class);
        $currentKind = match (true) {
            ($flags & Natives::ZEND_ACC_INTERFACE) !== 0 => 'interface',
            ($flags & Natives::ZEND_ACC_TRAIT) !== 0 => 'trait',
            ($flags & Natives::ZEND_ACC_ENUM) !== 0 => 'enum',
            default => null,
        };
        if ($currentKind === 'enum' || $kind === 'enum') {
            throw new InvalidArgumentException('Enum class kinds cannot be synthesized or cleared by Zendful.');
        }
        if ($currentKind !== null && $kind !== null && $currentKind !== $kind) {
            throw new InvalidArgumentException(sprintf(
                'Class kind must be cleared before changing from %s to %s.',
                $currentKind,
                $kind,
            ));
        }
        $mask = Natives::ZEND_ACC_INTERFACE
            | Natives::ZEND_ACC_ENUM
            | Natives::ZEND_ACC_TRAIT;
        self::setClassFlags($class, ($flags & ~$mask) | $kindFlag);
    }

    public static function setClassAnonymous(ClassHandle $class, bool $enabled): void
    {
        $flags = self::classFlags($class);
        $className = $class->name();
        self::assertLoadedClassName($className);
        if ((new \ReflectionClass($className))->isAnonymous() !== $enabled) {
            throw new InvalidArgumentException(sprintf(
                'Class anonymity cannot be changed after declaration: %s',
                $className,
            ));
        }
        self::setClassFlags($class, $enabled ? ($flags | Natives::ZEND_ACC_ANON_CLASS) : ($flags & ~Natives::ZEND_ACC_ANON_CLASS));
    }

    public static function classStaticsInitialized(ClassHandle $class): bool
    {
        $info = self::classInfo($class);
        if ($info === null) {
            throw new InvalidArgumentException(sprintf('Class does not exist: %s', $class->name()));
        }

        return $info->static_members_table__ptr !== null;
    }

    public static function initializeClassStatics(ClassHandle $class): void
    {
        $info = self::writableClassInfo($class);

        self::ffi()->zend_class_init_statics($info);
    }

    private static function propertyFlags(PropertyHandle $property): int
    {
        $info = self::propertyInfo($property);
        if ($info === null) {
            throw new InvalidArgumentException(sprintf('Property does not exist: %s::$%s', $property->className(), $property->propertyName()));
        }

        return $info->flags;
    }

    public static function propertyHasHooks(PropertyHandle $property): bool
    {
        $info = self::propertyInfo($property);

        return $info !== null && $info->hooks !== null;
    }

    private static function setPropertyFlags(PropertyHandle $property, int $flags): void
    {
        $info = self::propertyInfo($property);
        if ($info === null) {
            throw new InvalidArgumentException(sprintf('Property does not exist: %s::$%s', $property->className(), $property->propertyName()));
        }
        self::writableClassInfo(new ClassHandle($property->className()));

        $info->flags = $flags;
    }

    public static function setPropertyVisibility(PropertyHandle $property, string $visibility): void
    {
        $flag = self::visibilityFlag($visibility);
        self::setPropertyFlags($property, (self::propertyFlags($property) & ~Natives::ZEND_ACC_PPP_MASK) | $flag);
    }

    public static function clearPropertyVisibility(PropertyHandle $property): void
    {
        self::setPropertyFlags($property, self::propertyFlags($property) & ~Natives::ZEND_ACC_PPP_MASK);
    }

    public static function setPropertyReadonly(PropertyHandle $property, bool $enabled): void
    {
        $info = self::propertyInfo($property);
        if ($info === null) {
            throw new InvalidArgumentException(sprintf(
                'Property does not exist: %s::$%s',
                $property->className(),
                $property->propertyName(),
            ));
        }

        $flags = $info->flags;
        if ($enabled && ($flags & Natives::ZEND_ACC_STATIC) !== 0) {
            throw new InvalidArgumentException(sprintf(
                'Static properties cannot be readonly: %s::$%s',
                $property->className(),
                $property->propertyName(),
            ));
        }
        if ($enabled && $info->type->type_mask === 0) {
            throw new InvalidArgumentException(sprintf(
                'Readonly properties must be typed: %s::$%s',
                $property->className(),
                $property->propertyName(),
            ));
        }
        self::setPropertyFlags($property, $enabled ? ($flags | Natives::ZEND_ACC_READONLY) : ($flags & ~Natives::ZEND_ACC_READONLY));
    }

    public static function setPropertySetVisibility(PropertyHandle $property, string $visibility, bool $enabled): void
    {
        if (!self::propertyHasHooks($property)) {
            throw new InvalidArgumentException(sprintf(
                'Property setter visibility requires hooks: %s::$%s',
                $property->className(),
                $property->propertyName(),
            ));
        }
        $flag = match ($visibility) {
            'public' => Natives::ZEND_ACC_PUBLIC_SET,
            'protected' => Natives::ZEND_ACC_PROTECTED_SET,
            'private' => Natives::ZEND_ACC_PRIVATE_SET,
            default => throw new InvalidArgumentException('Invalid property-set visibility.'),
        };
        $flags = self::propertyFlags($property) & ~Natives::ZEND_ACC_PPP_SET_MASK;
        self::setPropertyFlags($property, $enabled ? ($flags | $flag) : $flags);
    }

    public static function withPropertyVisibility(PropertyHandle $property, string $visibility, callable $callback): mixed
    {
        $originalFlags = self::propertyFlags($property);
        self::setPropertyVisibility($property, $visibility);
        try {
            return $callback();
        } finally {
            self::setPropertyFlags($property, $originalFlags);
        }
    }

    public static function isUserDefinedMethod(MethodHandle $method): bool
    {
        $entry = self::methodEntry($method);
        if ($entry === null) {
            return false;
        }

        return self::ffi()->cast('zend_function *', $entry->value->ptr)->type === Natives::ZEND_FUNCTION_TYPE_USER;
    }

    private static function methodFlags(MethodHandle $method): int
    {
        $entry = self::methodEntry($method);
        if ($entry === null) {
            throw new InvalidArgumentException(sprintf('Method does not exist: %s::%s', $method->className(), $method->methodName()));
        }

        return self::ffi()->cast('zend_function *', $entry->value->ptr)->fn_flags;
    }

    private static function setMethodFlags(MethodHandle $method, int $flags): void
    {
        $entry = self::methodEntry($method);
        if ($entry === null) {
            throw new InvalidArgumentException(sprintf('Method does not exist: %s::%s', $method->className(), $method->methodName()));
        }
        self::assertWritableMethod($method, $entry);

        self::ffi()->cast('zend_function *', $entry->value->ptr)->fn_flags = $flags;
    }

    /**
     * @phpstan-param \Zendful_FFI\zval $entry
     * @phpstan-return \Zendful_FFI\zend_function
     */
    private static function assertWritableMethod(MethodHandle $method, object $entry): object
    {
        self::writableClassInfo(new ClassHandle($method->className()));
        $function = self::ffi()->cast('zend_function *', $entry->value->ptr);
        if ($function->type !== Natives::ZEND_FUNCTION_TYPE_USER) {
            // @codeCoverageIgnoreStart
            throw new InvalidArgumentException(sprintf(
                'Internal method cannot be modified: %s::%s',
                $method->className(),
                $method->methodName(),
            ));
            // @codeCoverageIgnoreEnd
        }
        if (($function->fn_flags & Natives::ZEND_ACC_IMMUTABLE) !== 0) {
            throw new InvalidArgumentException(sprintf(
                'Method is immutable and cannot be modified: %s::%s',
                $method->className(),
                $method->methodName(),
            ));
        }

        return $function;
    }

    public static function setMethodVisibility(MethodHandle $method, string $visibility): void
    {
        $flag = self::visibilityFlag($visibility);
        self::setMethodFlags($method, (self::methodFlags($method) & ~Natives::ZEND_ACC_PPP_MASK) | $flag);
    }

    public static function clearMethodVisibility(MethodHandle $method): void
    {
        self::setMethodFlags($method, self::methodFlags($method) & ~Natives::ZEND_ACC_PPP_MASK);
    }

    public static function setMethodStatic(MethodHandle $method, bool $enabled): void
    {
        $flags = self::methodFlags($method);
        self::setMethodFlags($method, $enabled ? ($flags | Natives::ZEND_ACC_STATIC) : ($flags & ~Natives::ZEND_ACC_STATIC));
    }

    public static function setMethodFinal(MethodHandle $method, bool $enabled): void
    {
        $flags = self::methodFlags($method);
        if ($enabled && ($flags & Natives::ZEND_ACC_ABSTRACT) !== 0) {
            throw new InvalidArgumentException(sprintf(
                'An abstract method cannot be made final: %s::%s',
                $method->className(),
                $method->methodName(),
            ));
        }
        self::setMethodFlags($method, $enabled ? ($flags | Natives::ZEND_ACC_FINAL) : ($flags & ~Natives::ZEND_ACC_FINAL));
    }

    public static function withMethodVisibility(MethodHandle $method, string $visibility, callable $callback): mixed
    {
        $originalFlags = self::methodFlags($method);
        self::setMethodVisibility($method, $visibility);
        try {
            return $callback();
        } finally {
            self::setMethodFlags($method, $originalFlags);
        }
    }

    private static function visibilityFlag(string $visibility): int
    {
        return match ($visibility) {
            'public' => Natives::ZEND_ACC_PUBLIC,
            'protected' => Natives::ZEND_ACC_PROTECTED,
            'private' => Natives::ZEND_ACC_PRIVATE,
            default => throw new InvalidArgumentException('Invalid visibility.'),
        };
    }

    public static function swapMethods(MethodHandle $methodA, MethodHandle $methodB): void
    {
        if (strcasecmp($methodA->className(), $methodB->className()) === 0
            && strcasecmp($methodA->methodName(), $methodB->methodName()) === 0) {
            return;
        }

        $entryA = self::methodEntry($methodA);
        $entryB = self::methodEntry($methodB);
        if ($entryA === null || $entryB === null) {
            throw new InvalidArgumentException(sprintf(
                'Both methods must exist before they can be swapped: %s::%s, %s::%s',
                $methodA->className(),
                $methodA->methodName(),
                $methodB->className(),
                $methodB->methodName(),
            ));
        }
        if (!self::classDerivesFrom($methodB->className(), $methodA->className())) {
            throw new InvalidArgumentException(sprintf(
                '%s must derive from %s before their methods can be swapped',
                $methodB->className(),
                $methodA->className(),
            ));
        }
        // @codeCoverageIgnoreStart
        self::assertWritableMethod($methodA, $entryA);
        self::assertWritableMethod($methodB, $entryB);

        $functionA = $entryA->value->ptr;
        $entryA->value->ptr = $entryB->value->ptr;
        $entryB->value->ptr = $functionA;
        // @codeCoverageIgnoreEnd
    }

    private static function classDerivesFrom(string $candidate, string $base): bool
    {
        if (strcasecmp($candidate, $base) === 0) {
            return true;
        }

        $parents = class_parents($candidate, false);
        if ($parents !== false) {
            foreach ($parents as $parent) {
                if (strcasecmp($parent, $base) === 0) {
                    return true;
                }
            }
        }

        $interfaces = class_implements($candidate, false);
        if ($interfaces !== false) {
            foreach ($interfaces as $interface) {
                if (strcasecmp($interface, $base) === 0) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function installMethod(MethodHandle $source, ClassHandle $target, string $name, bool $final): void
    {
        $sourceEntry = self::methodEntry($source);
        if ($sourceEntry === null) {
            throw new InvalidArgumentException(sprintf(
                'Method does not exist: %s::%s',
                $source->className(),
                $source->methodName(),
            ));
        }

        $targetInfo = self::classInfo($target);
        if ($targetInfo === null) {
            throw new InvalidArgumentException(sprintf('Class does not exist: %s', $target->name()));
        }
        self::writableClassInfo($target);

        $ffi = self::ffi();
        $functionTable = $targetInfo->function_table;
        $methodName = strtolower($name);
        $sourceFunction = $ffi->cast('zend_function *', $sourceEntry->value->ptr);
        if ($sourceFunction->type !== Natives::ZEND_FUNCTION_TYPE_USER) {
            throw new InvalidArgumentException(sprintf(
                'Only user-defined methods can be installed: %s::%s',
                $source->className(),
                $source->methodName(),
            ));
        }
        $existingEntry = $ffi->zend_hash_str_find(
            $ffi->cast('HashTable *', FFI::addr($functionTable)),
            $methodName,
            strlen($methodName),
        );
        if ($existingEntry !== null) {
            $existingFunction = $ffi->cast('zend_function *', $existingEntry->value->ptr);
            $existingScope = $existingFunction->scope;
            $inherited = $existingScope !== null
                && !FFI::isNull($existingScope)
                && $existingScope->name !== null
                && !FFI::isNull($existingScope->name)
                && strcasecmp(self::zendString($ffi, $existingScope->name), $target->name()) !== 0;
            if ($inherited) {
                // A child class may shadow an inherited method. The inherited
                // function table entry is replaced with a clone below; the
                // parent's function table remains untouched.
            } else {
                throw new InvalidArgumentException(sprintf(
                    'Method already exists: %s::%s',
                    $target->name(),
                    $name,
                ));
            }
        }
        $installedFunction = self::cloneUserFunction($ffi, $sourceFunction, $name);
        $installedFunction->scope = $targetInfo;
        if ($final) {
            $installedFunction->fn_flags |= Natives::ZEND_ACC_FINAL;
        }
        $entryValue = $ffi->new('zval');
        $entryValue->value->ptr = $installedFunction;
        $entryValue->u1->type_info = $sourceEntry->u1->type_info;
        $ownedByTable = false;
        try {
            if ($ffi->zend_hash_str_update(
                $ffi->cast('HashTable *', FFI::addr($functionTable)),
                $methodName,
                strlen($methodName),
                FFI::addr($entryValue),
            ) === null) {
                // @codeCoverageIgnoreStart
                throw new RuntimeException(sprintf('Failed to install method %s::%s', $target->name(), $name));
                // @codeCoverageIgnoreEnd
            }
            $ownedByTable = true;
        } finally {
            if (!$ownedByTable) {
                // @codeCoverageIgnoreStart
                self::destroyClonedFunction($ffi, $installedFunction);
                // @codeCoverageIgnoreEnd
            }
        }
        self::blacklistCurrentCallers();
    }

    public static function renameMethod(MethodHandle $method, string $name): void
    {
        $entry = self::methodEntry($method);
        if ($entry === null) {
            throw new InvalidArgumentException(sprintf(
                'Method does not exist: %s::%s',
                $method->className(),
                $method->methodName(),
            ));
        }
        self::assertWritableMethod($method, $entry);

        $class = self::classInfo(new ClassHandle($method->className()));
        if ($class === null) {
            // @codeCoverageIgnoreStart
            throw new InvalidArgumentException(sprintf('Class does not exist: %s', $method->className()));
            // @codeCoverageIgnoreEnd
        }

        $ffi = self::ffi();
        $currentName = strtolower($method->methodName());
        $newName = strtolower($name);
        if ($currentName === $newName) {
            return;
        }
        if ($ffi->zend_hash_str_find(
            $ffi->cast('HashTable *', FFI::addr($class->function_table)),
            $newName,
            strlen($newName),
        ) !== null) {
            throw new InvalidArgumentException(sprintf(
                'Method already exists: %s::%s',
                $method->className(),
                $name,
            ));
        }
        $key = $ffi->zend_strpprintf(max(Natives::ZEND_MIN_ALLOCATION_SIZE, strlen($name) + Natives::ZEND_MIN_ALLOCATION_SIZE), '%s', $name);
        try {
            $bucket = $ffi->cast('Bucket *', $entry);
            if ($ffi->zend_hash_set_bucket_key(
                $ffi->cast('HashTable *', FFI::addr($class->function_table)),
                $bucket,
                $key,
            ) === null) {
                // @codeCoverageIgnoreStart
                throw new RuntimeException(sprintf(
                    'Failed to rename method %s::%s to %s',
                    $method->className(),
                    $method->methodName(),
                    $name,
                ));
                // @codeCoverageIgnoreEnd
            }
        } finally {
            self::releaseKey($ffi, $key);
        }
        self::blacklistCurrentCallers();
    }

    public static function installGeneratedMethod(
        MethodHandle $source,
        MethodHandle $template,
        ClassHandle $target,
        string $name,
    ): void {
        $sourceEntry = self::methodEntry($source);
        $templateEntry = self::methodEntry($template);
        $targetInfo = self::classInfo($target);
        if ($sourceEntry === null || $templateEntry === null) {
            throw new InvalidArgumentException('Generated methods require existing source and template methods.');
        }
        if ($targetInfo === null) {
            throw new InvalidArgumentException(sprintf('Class does not exist: %s', $target->name()));
        }
        self::writableClassInfo($target);

        $ffi = self::ffi();
        $templateFunction = $ffi->cast('zend_function *', $templateEntry->value->ptr);
        $sourceFunction = $ffi->cast('zend_function *', $sourceEntry->value->ptr);
        if ($sourceFunction->type !== Natives::ZEND_FUNCTION_TYPE_USER) {
            // @codeCoverageIgnoreStart
            throw new InvalidArgumentException(sprintf(
                'Only user-defined methods can provide generated visibility: %s::%s',
                $source->className(),
                $source->methodName(),
            ));
            // @codeCoverageIgnoreEnd
        }
        if ($ffi->zend_hash_str_find(
            $ffi->cast('HashTable *', FFI::addr($targetInfo->function_table)),
            strtolower($name),
            strlen($name),
        ) !== null) {
            throw new InvalidArgumentException(sprintf(
                'Method already exists: %s::%s',
                $target->name(),
                $name,
            ));
        }
        $generated = self::cloneUserFunction($ffi, $templateFunction, $name);
        $generated->scope = $targetInfo;
        $generated->prototype = null;
        $generated->fn_flags = ($templateFunction->fn_flags & ~Natives::ZEND_ACC_PPP_MASK) | ($sourceFunction->fn_flags & Natives::ZEND_ACC_PPP_MASK);
        $generated->attributes = null;

        $entryValue = $ffi->new('zval');
        $entryValue->value->ptr = $generated;
        $entryValue->u1->type_info = $templateEntry->u1->type_info;
        $methodName = strtolower($name);
        $ownedByTable = false;
        try {
            if ($ffi->zend_hash_str_update(
                $ffi->cast('HashTable *', FFI::addr($targetInfo->function_table)),
                $methodName,
                strlen($methodName),
                FFI::addr($entryValue),
            ) === null) {
                // @codeCoverageIgnoreStart
                throw new RuntimeException(sprintf('Failed to install generated method %s::%s', $target->name(), $name));
                // @codeCoverageIgnoreEnd
            }
            $ownedByTable = true;
        } finally {
            if (!$ownedByTable) {
                // @codeCoverageIgnoreStart
                self::destroyClonedFunction($ffi, $generated);
                // @codeCoverageIgnoreEnd
            }
        }
        self::blacklistCurrentCallers();
    }

    /**
     * @phpstan-param \Zendful_FFI\zend_function $source
     * @phpstan-return \Zendful_FFI\zend_function
     */
    private static function cloneUserFunction(FFI $ffi, object $source, string $name): object
    {
        if ($source->type !== Natives::ZEND_FUNCTION_TYPE_USER || $source->op_array->refcount === null) {
            throw new InvalidArgumentException('Only user-defined methods with refcounted op arrays can be installed.');
        }

        $allocation = $ffi->_emalloc(FFI::sizeof($ffi->new('zend_function')));
        $clone = $ffi->cast('zend_function *', $allocation);
        FFI::memcpy($clone, $source, FFI::sizeof($ffi->new('zend_function')));
        $refcount = $clone->op_array->refcount;
        if ($refcount === null) {
            // @codeCoverageIgnoreStart
            self::destroyClonedFunction($ffi, $clone);
            throw new RuntimeException('User function op array has no reference count.');
            // @codeCoverageIgnoreEnd
        }
        $refcount[0] = $refcount[0] + 1;
        $clone->function_name = $ffi->zend_strpprintf(
            max(Natives::ZEND_MIN_ALLOCATION_SIZE, strlen($name) + Natives::ZEND_MIN_ALLOCATION_SIZE),
            '%s',
            $name,
        );

        return $clone;
    }

    /** @phpstan-param \Zendful_FFI\zend_function $function */
    private static function destroyClonedFunction(FFI $ffi, object $function): void
    {
        // @codeCoverageIgnoreStart
        $ffi->destroy_zend_function($function);
        $ffi->_efree($function);
        // @codeCoverageIgnoreEnd
    }

    public static function methodHasBytecode(MethodHandle $method): bool
    {
        $entry = self::methodEntry($method);
        if ($entry === null) {
            return false;
        }

        $function = self::ffi()->cast('zend_function *', $entry->value->ptr);
        if ($function->type !== Natives::ZEND_FUNCTION_TYPE_USER) {
            return false;
        }

        return $function->op_array->opcodes !== null;
    }

    public static function hasBytecode(FunctionHandle $function): bool
    {
        $name = strtolower($function->name());
        $ffi = self::ffi();
        $table = self::functionTable($ffi);
        $entry = $ffi->zend_hash_str_find($table, $name, strlen($name));
        if ($entry === null) {
            return false;
        }

        $runtimeFunction = $ffi->cast('zend_function *', $entry->value->ptr);
        if ($runtimeFunction->type !== Natives::ZEND_FUNCTION_TYPE_USER) {
            return false;
        }

        return $runtimeFunction->op_array->opcodes !== null;
    }

    /** @return array{instructionCount: int, argumentCount: int, variableCount: int, temporaryCount: int, cacheSize: int, literalCount: int, immutable: bool, filename: string|null, lineStart: int, lineEnd: int, variableNames: array<int, string>} */
    public static function opArrayMetadata(FunctionHandle|MethodHandle $source): array
    {
        $ffi = self::ffi();
        $entry = self::entry($source, $ffi);
        if ($entry === null) {
            throw new InvalidArgumentException(sprintf('Callable does not exist: %s', self::sourceName($source)));
        }

        $function = $ffi->cast('zend_function *', $entry->value->ptr);
        if ($function->type !== Natives::ZEND_FUNCTION_TYPE_USER) {
            throw new RuntimeException(sprintf('Op array is unavailable for internal callable %s', self::sourceName($source)));
        }

        $opArray = $function->op_array;
        if ($opArray->opcodes === null) {
            // @codeCoverageIgnoreStart
            throw new RuntimeException(sprintf('Bytecode is unavailable for %s', self::sourceName($source)));
            // @codeCoverageIgnoreEnd
        }

        $variableNames = [];
        if ($opArray->last_var > 0 && $opArray->vars !== null) {
            $zvalSize = FFI::sizeof($ffi->new('zval'));
            for ($index = 0; $index < $opArray->last_var; $index++) {
                $variableNames[($index + Natives::ZEND_RESERVED_VARIABLE_COUNT) * $zvalSize] = self::zendString($ffi, $opArray->vars[$index]);
            }
        }

        return [
            'instructionCount' => $opArray->last,
            'argumentCount' => $opArray->num_args,
            'variableCount' => $opArray->last_var,
            'temporaryCount' => $opArray->T,
            'cacheSize' => $opArray->cache_size,
            'literalCount' => $opArray->last_literal,
            'immutable' => ($opArray->fn_flags & Natives::ZEND_ACC_IMMUTABLE) !== 0,
            'filename' => $opArray->filename === null
                ? null
                : self::zendString($ffi, $opArray->filename),
            'lineStart' => $opArray->line_start,
            'lineEnd' => $opArray->line_end,
            'variableNames' => $variableNames,
        ];
    }

    public static function opcode(OpArrayHandle $opArray, int $index): OpcodeHandle
    {
        $ffi = self::ffi();
        $source = $opArray->source();
        $entry = self::entry($source, $ffi);
        if ($entry === null) {
            // @codeCoverageIgnoreStart
            throw new InvalidArgumentException(sprintf('Callable does not exist: %s', self::sourceName($source)));
            // @codeCoverageIgnoreEnd
        }

        $function = $ffi->cast('zend_function *', $entry->value->ptr);
        if ($function->type !== Natives::ZEND_FUNCTION_TYPE_USER) {
            // @codeCoverageIgnoreStart
            throw new RuntimeException(sprintf('Op array is unavailable for internal callable %s', self::sourceName($source)));
            // @codeCoverageIgnoreEnd
        }

        $opArrayNative = $function->op_array;
        if ($opArrayNative->opcodes === null) {
            // @codeCoverageIgnoreStart
            throw new RuntimeException(sprintf('Bytecode is unavailable for %s', self::sourceName($source)));
            // @codeCoverageIgnoreEnd
        }
        if ($index < 0 || $index >= $opArrayNative->last) {
            // @codeCoverageIgnoreStart
            throw new \OutOfRangeException(sprintf('Opcode index is out of range: %d', $index));
            // @codeCoverageIgnoreEnd
        }

        $opline = $opArrayNative->opcodes[$index];

        return new OpcodeHandle([
            'opcode' => $opline->opcode,
            'name' => $ffi->zend_get_opcode_name($opline->opcode) ?? '',
            'extendedValue' => $opline->extended_value,
            'line' => $opline->lineno,
            'result' => self::operand($ffi, $opArrayNative, $index, $opline, $opline->result_type, $opline->result),
            'operand1' => self::operand($ffi, $opArrayNative, $index, $opline, $opline->op1_type, $opline->op1),
            'operand2' => self::operand($ffi, $opArrayNative, $index, $opline, $opline->op2_type, $opline->op2),
        ]);
    }

    /** @phpstan-param \Zendful_FFI\zend_op_array $opArray */
    public static function snapshotOpArray(object $opArray): CompiledOpArrayHandle
    {
        $ffi = self::ffi();
        if ($opArray->opcodes === null) {
            throw new RuntimeException('The runtime op array has no opcode storage');
        }

        $variableNames = [];
        if ($opArray->last_var > 0 && $opArray->vars !== null) {
            $zvalSize = FFI::sizeof($ffi->new('zval'));
            for ($index = 0; $index < $opArray->last_var; $index++) {
                $variableNames[($index + Natives::ZEND_RESERVED_VARIABLE_COUNT) * $zvalSize] = self::zendString($ffi, $opArray->vars[$index]);
            }
        }

        $opcodes = [];
        for ($index = 0; $index < $opArray->last; $index++) {
            $opline = $opArray->opcodes[$index];
            $opcodes[] = new OpcodeHandle([
                'opcode' => $opline->opcode,
                'name' => $ffi->zend_get_opcode_name($opline->opcode) ?? '',
                'extendedValue' => $opline->extended_value,
                'line' => $opline->lineno,
                'result' => self::operand($ffi, $opArray, $index, $opline, $opline->result_type, $opline->result),
                'operand1' => self::operand($ffi, $opArray, $index, $opline, $opline->op1_type, $opline->op1),
                'operand2' => self::operand($ffi, $opArray, $index, $opline, $opline->op2_type, $opline->op2),
            ]);
        }

        return new CompiledOpArrayHandle([
            'instructionCount' => $opArray->last,
            'filename' => $opArray->filename === null ? null : self::zendString($ffi, $opArray->filename),
            'lineStart' => $opArray->line_start,
            'lineEnd' => $opArray->line_end,
            'variableNames' => $variableNames,
            'temporaryCount' => $opArray->T,
            'cacheSize' => $opArray->cache_size,
        ], $opcodes);
    }

    /** @param list<array{opcode: int, extendedValue: int, line: int, originalIndex: int|null, result: array{type: int, kind: string, value: mixed, rawValue: int|null, literalSlot: int|null}, operand1: array{type: int, kind: string, value: mixed, rawValue: int|null, literalSlot: int|null}, operand2: array{type: int, kind: string, value: mixed, rawValue: int|null, literalSlot: int|null}}> $instructions
     * @phpstan-param \Zendful_FFI\zend_op_pointer $opcodes
     * @phpstan-param \Zendful_FFI\zval_pointer|null $literals
     */
    private static function writeExisting(FFI $ffi, array $instructions, object $opcodes, ?object $literals): void
    {
        foreach ($instructions as $index => $instruction) {
            $opline = $opcodes[$index];
            $opline->opcode = $instruction['opcode'];
            $opline->op1_type = $instruction['operand1']['type'];
            $opline->op2_type = $instruction['operand2']['type'];
            $opline->result_type = $instruction['result']['type'];
            $opline->extended_value = $instruction['extendedValue'];
            $opline->lineno = $instruction['line'];
            self::writeOperand($ffi, $opline->result, $instruction['result'], $opline, $literals);
            self::writeOperand($ffi, $opline->op1, $instruction['operand1'], $opline, $literals);
            self::writeOperand($ffi, $opline->op2, $instruction['operand2'], $opline, $literals);
        }
        foreach ($instructions as $index => $_instruction) {
            $ffi->zend_vm_set_opcode_handler(FFI::addr($opcodes[$index]));
        }
    }

    /**
     * @param array{type: int, kind: string, value: mixed, rawValue: int|null, literalSlot: int|null} $operand
     * @phpstan-param \Zendful_FFI\znode_op $raw
     * @phpstan-param \Zendful_FFI\zend_op $opline
     * @phpstan-param \Zendful_FFI\zval_pointer|null $literals
     */
    private static function writeOperand(FFI $ffi, object $raw, array $operand, object $opline, ?object $literals): void
    {
        if ($operand['kind'] === 'constant') {
            $slot = $operand['literalSlot'];
            if ($slot === null || $literals === null) {
                throw new RuntimeException('A constant operand has no literal slot');
            }

            if (PHP_INT_SIZE === 4) {
                // @codeCoverageIgnoreStart
                $raw->zv = $ffi->cast('zval *', $ffi->cast('char *', $ffi->cast('uintptr_t', $literals)->cdata + $slot * FFI::sizeof($ffi->new('zval'))));
                return;
                // @codeCoverageIgnoreEnd
            }

            $oplineAddress = $ffi->cast('uintptr_t', FFI::addr($opline))->cdata;
            $literalAddress = $ffi->cast('uintptr_t', $literals)->cdata;
            $raw->constant = $literalAddress + FFI::sizeof($ffi->new('zval')) * $slot - $oplineAddress;
            return;
        }

        $value = $operand['rawValue'] ?? $operand['value'];
        if (!is_int($value)) {
            throw new InvalidArgumentException('An opcode operand must contain an integer runtime value');
        }

        match ($operand['type']) {
            Natives::ZEND_IS_TMP_VAR, Natives::ZEND_IS_VAR, Natives::ZEND_IS_CV => $raw->var = $value,
            default => $raw->num = $value,
        };
    }

    /** @phpstan-param \Zendful_FFI\zval $literal */
    private static function writeLiteral(FFI $ffi, object $literal, mixed $value): ?object
    {
        if (is_string($value) && str_contains($value, "\0")) {
            throw new InvalidArgumentException('Assembly literals must not contain NUL bytes.');
        }

        if ($value === null) {
            $literal->value->lval = 0;
            $literal->u1->type_info = Natives::ZEND_TYPE_NULL;
        } elseif (is_bool($value)) {
            $literal->value->lval = 0;
            $literal->u1->type_info = $value ? Natives::ZEND_TYPE_TRUE : Natives::ZEND_TYPE_FALSE;
        } elseif (is_int($value)) {
            $literal->value->lval = $value;
            $literal->u1->type_info = Natives::ZEND_TYPE_LONG;
        } elseif (is_float($value)) {
            $literal->value->dval = $value;
            $literal->u1->type_info = Natives::ZEND_TYPE_DOUBLE;
        } elseif (is_string($value)) {
            $string = $ffi->zend_strpprintf(max(Natives::ZEND_MIN_ALLOCATION_SIZE, strlen($value) + Natives::ZEND_MIN_ALLOCATION_SIZE), '%s', $value);
            $string->h = $ffi->zend_hash_func($value, strlen($value));
            $literal->value->ptr = $string;
            $literal->u1->type_info = Natives::ZEND_TYPE_STRING;
            $literal->u2->extra = 0;
            return $string;
        } else {
            throw new RuntimeException('Only scalar constants can be encoded into a rewritten literal pool');
        }

        $literal->u2->extra = 0;

        return null;
    }

    /** @phpstan-param \Zendful_FFI\zval_pointer|null $literals */
    private static function forgetLiterals(FFI $ffi, ?object $literals, int $count): void
    {
        if ($literals === null) {
            return;
        }

        for ($index = 0; $index < $count; $index++) {
            $ffi->zval_ptr_dtor(FFI::addr($literals[$index]));
            $literals[$index]->u1->type_info = Natives::ZEND_TYPE_UNDEF;
        }
    }

    /**
     * @phpstan-param \Zendful_FFI\zend_op_array $opArray
     * @param list<array{opcode: int, extendedValue: int, line: int, originalIndex: int|null, result: array{type: int, kind: string, value: mixed, rawValue: int|null, literalSlot: int|null}, operand1: array{type: int, kind: string, value: mixed, rawValue: int|null, literalSlot: int|null}, operand2: array{type: int, kind: string, value: mixed, rawValue: int|null, literalSlot: int|null}}> $instructions
     */
    private static function relocateMetadata(object $opArray, array $instructions, int $oldCount): void
    {
        $newCount = count($instructions);
        if ($oldCount === $newCount) {
            return;
        }

        $map = [];
        foreach ($instructions as $newIndex => $instruction) {
            if ($instruction['originalIndex'] !== null) {
                $map[$instruction['originalIndex']] = $newIndex;
            }
        }
        $relocate = static function (int $oldIndex) use ($map, $newCount): int {
            if (isset($map[$oldIndex])) {
                return $map[$oldIndex];
            }
            $next = $newCount;
            foreach ($map as $original => $new) {
                if ($original > $oldIndex && $new < $next) {
                    $next = $new;
                }
            }
            return $next;
        };

        if ($opArray->live_range !== null) {
            for ($index = 0; $index < $opArray->last_live_range; $index++) {
                $range = $opArray->live_range[$index];
                $range->start = $relocate($range->start);
                $range->end = $relocate($range->end);
            }
        }
        if ($opArray->try_catch_array !== null) {
            // @codeCoverageIgnoreStart
            for ($index = 0; $index < $opArray->last_try_catch; $index++) {
                $catch = $opArray->try_catch_array[$index];
                $catch->try_op = $relocate($catch->try_op);
                $catch->catch_op = $relocate($catch->catch_op);
                $catch->finally_op = $relocate($catch->finally_op);
                $catch->finally_end = $relocate($catch->finally_end);
            }
            // @codeCoverageIgnoreEnd
        }
    }

    private static function align(int $size, int $alignment): int
    {
        return ($size + $alignment - 1) & (~($alignment - 1));
    }

    /**
     * @phpstan-param \Zendful_FFI\zend_op_array $opArray
     * @phpstan-param \Zendful_FFI\zend_op $opline
     * @phpstan-param \Zendful_FFI\znode_op $operand
     */
    private static function operand(FFI $ffi, object $opArray, int $index, object $opline, int $type, object $operand): OperandHandle
    {
        $constantDescription = '';
        $value = null;
        $literalIndex = null;
        if ($type === Natives::ZEND_IS_CONST) {
            $opcodesAddress = $ffi->cast('uintptr_t', $opArray->opcodes)->cdata;
            $oplineAddress = $opcodesAddress + $index * FFI::sizeof($ffi->new('zend_op'));
            $literalAddress = $oplineAddress + $operand->constant;
            $literal = $ffi->cast('zval *', $ffi->cast('char *', $literalAddress))[0];
            $literalType = $literal->u1->v->type;
            $value = self::constantValue($ffi, $literal);
            $displayValue = match ($literalType) {
                Natives::ZEND_TYPE_NULL => 'NULL',
                Natives::ZEND_TYPE_FALSE => 'false',
                Natives::ZEND_TYPE_TRUE => 'true',
                Natives::ZEND_TYPE_LONG => (string) $literal->value->lval,
                Natives::ZEND_TYPE_DOUBLE => (string) $literal->value->dval,
                Natives::ZEND_TYPE_STRING => var_export(
                    self::zendString($ffi, $ffi->cast('zend_string *', $literal->value->ptr)),
                    true,
                ),
                default => match ($literalType) {
                    Natives::ZEND_TYPE_ARRAY => 'ARRAY',
                    Natives::ZEND_TYPE_OBJECT => 'OBJECT',
                    Natives::ZEND_TYPE_RESOURCE => 'RESOURCE',
                    default => sprintf('TYPE(%d)', $literalType),
                },
            };
            $constantDescription = sprintf('CONST#%d (%s)', $operand->constant, $displayValue);
            if ($opArray->literals !== null) {
                $literalBase = $ffi->cast('uintptr_t', $opArray->literals)->cdata;
                $literalIndex = intdiv($literalAddress - $literalBase, FFI::sizeof($ffi->new('zval')));
            }
        }

        return new OperandHandle(
            $type,
            $operand->constant,
            $operand->var,
            $operand->num,
            $constantDescription,
            $value,
            $literalIndex,
        );
    }

    /** @phpstan-param \Zendful_FFI\zval $literal */
    private static function constantValue(FFI $ffi, object $literal): mixed
    {
        return match ($literal->u1->v->type) {
            Natives::ZEND_TYPE_NULL => null,
            Natives::ZEND_TYPE_FALSE => false,
            Natives::ZEND_TYPE_TRUE => true,
            Natives::ZEND_TYPE_LONG => $literal->value->lval,
            Natives::ZEND_TYPE_DOUBLE => $literal->value->dval,
            Natives::ZEND_TYPE_STRING => self::zendString($ffi, $ffi->cast('zend_string *', $literal->value->ptr)),
            Natives::ZEND_TYPE_ARRAY => self::arrayValue($ffi, $literal),
            default => sprintf('TYPE(%d)', $literal->u1->v->type),
        };
    }

    /** @phpstan-param \Zendful_FFI\zval $literal
     * @return array<int, mixed>|string
     */
    private static function arrayValue(FFI $ffi, object $literal): mixed
    {
        $pointer = $literal->value->ptr;
        if ($pointer === null) {
            return [];
        }

        $array = $ffi->cast('HashTable *', $pointer);
        if (($array->u->flags & Natives::HASH_FLAG_PACKED) === 0) {
            return 'ARRAY';
        }

        $values = [];
        for ($index = 0; $index < $array->nNumOfElements; $index++) {
            $values[] = self::constantValue($ffi, $array->arPacked[$index]);
        }

        return $values;
    }

    /** @phpstan-param \Zendful_FFI\zend_string $string */
    private static function zendString(FFI $ffi, object $string): string
    {
        if ($string->len <= 0) {
            return '';
        }
        if ($string->len > Natives::ZEND_MAX_SAFE_STRING_LENGTH) {
            throw new RuntimeException('Zend string exceeds Zendful safety limits.');
        }

        return FFI::string($ffi->cast('char *', $string->val), $string->len);
    }

    /** @phpstan-return \Zendful_FFI\zval|null */
    private static function entry(FunctionHandle|MethodHandle $source, FFI $ffi): ?object
    {
        if ($source instanceof MethodHandle) {
            return self::methodEntry($source);
        }

        $name = strtolower($source->name());

        return $ffi->zend_hash_str_find(self::functionTable($ffi), $name, strlen($name));
    }

    private static function sourceName(FunctionHandle|MethodHandle $source): string
    {
        return $source instanceof MethodHandle
            ? $source->className() . '::' . $source->methodName()
            : $source->name();
    }

    public static function swapFunctions(FunctionHandle $functionA, FunctionHandle $functionB): void
    {
        $nameA = $functionA->name();
        $nameB = $functionB->name();
        if (strcasecmp($nameA, $nameB) === 0) {
            return;
        }

        $ffi = self::ffi();
        $functionALower = strtolower($nameA);
        $functionBLower = strtolower($nameB);
        $functionTable = self::functionTable($ffi);
        $entryA = $ffi->zend_hash_str_find($functionTable, $functionALower, strlen($functionALower));
        $entryB = $ffi->zend_hash_str_find($functionTable, $functionBLower, strlen($functionBLower));

        if ($entryA === null || $entryB === null) {
            throw new InvalidArgumentException(sprintf(
                'Both functions must exist before they can be swapped: %s, %s',
                $nameA,
                $nameB,
            ));
        }
        self::assertWritableFunction($entryA, $nameA);
        self::assertWritableFunction($entryB, $nameB);

        $functionPointer = $entryA->value->ptr;
        $entryA->value->ptr = $entryB->value->ptr;
        $entryB->value->ptr = $functionPointer;

        self::blacklistCurrentCallers();
    }

    /** @phpstan-param \Zendful_FFI\zval $entry */
    private static function assertWritableFunction(object $entry, string $name): void
    {
        $function = self::ffi()->cast('zend_function *', $entry->value->ptr);
        if ($function->type !== Natives::ZEND_FUNCTION_TYPE_USER) {
            throw new InvalidArgumentException(sprintf('Internal function cannot be modified: %s', $name));
        }
        if (($function->fn_flags & Natives::ZEND_ACC_IMMUTABLE) !== 0) {
            throw new InvalidArgumentException(sprintf('Function is immutable and cannot be modified: %s', $name));
        }
    }

    /** @phpstan-param \Zendful_FFI\zend_string $key */
    private static function releaseKey(FFI $ffi, object $key): void
    {
        $key->gc->refcount--;
        if ($key->gc->refcount === 0) {
            $ffi->free_estring(FFI::addr($key));
        }
    }

    /** @phpstan-return \Zendful_FFI\HashTable */
    private static function functionTable(FFI $ffi): object
    {
        if (ZEND_THREAD_SAFE) {
            // @codeCoverageIgnoreStart
            $globals = $ffi->ts_resource_ex($ffi->executor_globals_id, null);
            if ($globals === null || FFI::isNull($globals)) {
                throw new RuntimeException('Executor globals are not available');
            }

            $functionTable = $ffi->cast('zend_executor_globals *', $globals)->function_table;
            // @codeCoverageIgnoreEnd
        } else {
            $functionTable = $ffi->executor_globals->function_table;
        }
        // @codeCoverageIgnoreEnd

        // @codeCoverageIgnoreStart
        if ($functionTable === null) {
            throw new RuntimeException('Function table is not available');
        }
        // @codeCoverageIgnoreEnd

        return $functionTable;
    }

    /** @phpstan-return \Zendful_FFI\zval|null */
    private static function methodEntry(MethodHandle $method): ?object
    {
        if (!self::loadedClassExists($method->className())) {
            return null;
        }

        $ffi = self::ffi();
        $name = $ffi->zend_strpprintf(
            max(Natives::ZEND_MIN_ALLOCATION_SIZE, strlen($method->className()) + Natives::ZEND_MIN_ALLOCATION_SIZE),
            '%s',
            $method->className(),
        );
        $class = $ffi->zend_lookup_class($name);
        $ffi->free_estring(FFI::addr($name));
        if ($class === null || FFI::isNull($class)) {
            // @codeCoverageIgnoreStart
            return null;
            // @codeCoverageIgnoreEnd
        }

        $methodName = strtolower($method->methodName());

        return $ffi->zend_hash_str_find(
            FFI::addr($class->function_table),
            $methodName,
            strlen($methodName),
        );
    }

    /** @phpstan-return \Zendful_FFI\zend_property_info|null */
    private static function propertyInfo(PropertyHandle $property): ?object
    {
        if (!self::loadedClassExists($property->className())) {
            return null;
        }

        $ffi = self::ffi();
        $name = $ffi->zend_strpprintf(
            max(Natives::ZEND_MIN_ALLOCATION_SIZE, strlen($property->className()) + Natives::ZEND_MIN_ALLOCATION_SIZE),
            '%s',
            $property->className(),
        );
        $class = $ffi->zend_lookup_class($name);
        $ffi->free_estring(FFI::addr($name));
        if ($class === null || FFI::isNull($class)) {
            // @codeCoverageIgnoreStart
            return null;
            // @codeCoverageIgnoreEnd
        }

        $propertyName = $property->propertyName();
        $entry = $ffi->zend_hash_str_find(
            FFI::addr($class->properties_info),
            $propertyName,
            strlen($propertyName),
        );
        if ($entry === null) {
            // @codeCoverageIgnoreStart
            return null;
            // @codeCoverageIgnoreEnd
        }

        return $ffi->cast('zend_property_info *', $entry->value->ptr);
    }

    /** @phpstan-return \Zendful_FFI\zend_class_entry|null */
    private static function classInfo(ClassHandle $class): ?object
    {
        if (!self::loadedClassExists($class->name())) {
            return null;
        }

        $ffi = self::ffi();
        $name = $ffi->zend_strpprintf(
            max(Natives::ZEND_MIN_ALLOCATION_SIZE, strlen($class->name()) + Natives::ZEND_MIN_ALLOCATION_SIZE),
            '%s',
            $class->name(),
        );
        $entry = $ffi->zend_lookup_class($name);
        $ffi->free_estring(FFI::addr($name));

        return $entry === null || FFI::isNull($entry) ? null : $entry;
    }

    private static function loadedClassExists(string $name): bool
    {
        foreach (array_merge(get_declared_classes(), get_declared_interfaces(), get_declared_traits()) as $declaredType) {
            if (strcasecmp($declaredType, $name) === 0) {
                return true;
            }
        }

        return false;
    }

    /** @phpstan-assert class-string $name */
    private static function assertLoadedClassName(string $name): void
    {
        if (!self::loadedClassExists($name)) {
            throw new InvalidArgumentException(sprintf('Class does not exist: %s', $name));
        }
    }

}
