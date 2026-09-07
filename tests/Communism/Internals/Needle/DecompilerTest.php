<?php

declare(strict_types=1);

use Communism\Internals\Needle\Decompiler;
use Communism\Mixin\Invoker;
use Communism\Mixin\Mixin;
use Communism\Reflect\ReflectionClass;
use Zendful\CompiledOpArrayHandle;
use Zendful\OpcodeHandle;
use Zendful\OperandHandle;

describe('Decompiler', function (): void {
    covers(Decompiler::class);

    #[Mixin(Decompiler::class)]
    final class DecompilerInvokerMixin
    {
        private function __construct() {}

        #[Invoker('handleOpcodeName')]
        public static function invokeHandleOpcodeName(string $name, int $opcode): string
        {
            throw new LogicException('Mixin invoker stub');
        }
    }

    it('formats opcodes whose runtime name is empty', function (): void {
        (new ReflectionClass(Decompiler::class))->inject(DecompilerInvokerMixin::class);

        $method = new ReflectionMethod(Decompiler::class, 'invokeHandleOpcodeName');

        expect($method->invoke(null, '', 123))->toBe('OP_123');
    });

    it('resolves frameless opcode metadata from compiled op arrays', function (): void {
        $unused = new OperandHandle(OperandHandle::TYPE_UNUSED, 0, 0, 0, '');
        $opcode = new OpcodeHandle([
            'opcode' => 0,
            'name' => 'FRAMELESS_ICALL_1',
            'extendedValue' => 0,
            'line' => 1,
            'result' => $unused,
            'operand1' => $unused,
            'operand2' => $unused,
        ]);
        $compiled = new CompiledOpArrayHandle([
            'instructionCount' => 1,
            'filename' => null,
            'lineStart' => 1,
            'lineEnd' => 1,
            'variableNames' => [],
            'temporaryCount' => 0,
            'cacheSize' => 0,
        ], [$opcode]);

        expect(Decompiler::decompileCompiledOpArray($compiled, 'frameless')->instructions()[0]->name)
            ->toBe('FRAMELESS_ICALL_1');
    });
});
