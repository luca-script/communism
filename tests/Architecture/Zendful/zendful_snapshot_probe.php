<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

use Zendful\CompiledClassHandle;
use Zendful\CompiledFileHandle;
use Zendful\CompiledMethodHandle;
use Zendful\CompiledOpArrayHandle;
use Zendful\OpcodeHandle;
use Zendful\OperandHandle;

/** @template T of object
 * @param ReflectionClass<T> $reflection
 * @param array<int, mixed> $arguments
 */
function forgedSnapshot(ReflectionClass $reflection, array $arguments): object
{
    return $reflection->newInstanceArgs($arguments);
}

$opArray = new CompiledOpArrayHandle([
    'instructionCount' => 0,
    'filename' => null,
    'lineStart' => 0,
    'lineEnd' => 0,
    'variableNames' => [],
    'temporaryCount' => 0,
    'cacheSize' => 0,
], []);
$compiledClass = new ReflectionClass(CompiledClassHandle::class);
$compiledFile = new ReflectionClass(CompiledFileHandle::class);
$compiledOpArray = new ReflectionClass(CompiledOpArrayHandle::class);
$opcode = new ReflectionClass(OpcodeHandle::class);
$operand = new OperandHandle(0, 0, 0, 0, '');
$attempts = [
    static fn(): object => new CompiledMethodHandle('', $opArray),
    static fn(): object => new CompiledMethodHandle("run\0", $opArray),
    static fn(): object => new OperandHandle(-1, 0, 0, 0, ''),
    static fn(): object => forgedSnapshot($opcode, [['opcode' => 1]]),
    static fn(): object => forgedSnapshot($opcode, [[
        'opcode' => 1,
        'name' => "NOP\0",
        'extendedValue' => 0,
        'line' => 0,
        'result' => $operand,
        'operand1' => $operand,
        'operand2' => $operand,
    ]]),
    static fn(): object => forgedSnapshot($compiledOpArray, [[
        'instructionCount' => 1,
        'filename' => null,
        'lineStart' => 0,
        'lineEnd' => 0,
        'variableNames' => [],
        'temporaryCount' => 0,
        'cacheSize' => 0,
    ], []]),
    static fn(): object => forgedSnapshot($compiledOpArray, [[
        'instructionCount' => 0,
        'filename' => "file\0.php",
        'lineStart' => 0,
        'lineEnd' => 0,
        'variableNames' => [],
        'temporaryCount' => 0,
        'cacheSize' => 0,
    ], []]),
    static fn(): object => forgedSnapshot($compiledClass, ['Class', ['bad' => new stdClass()]]),
    static fn(): object => forgedSnapshot($compiledClass, ["Class\0", []]),
    static fn(): object => forgedSnapshot($compiledFile, [$opArray, [0 => new stdClass()]]),
    static fn(): object => forgedSnapshot($compiledFile, [$opArray, ["Class\0"], [], []]),
    static fn(): object => forgedSnapshot($compiledFile, [$opArray, [], [new stdClass()]]),
    static fn(): object => forgedSnapshot($compiledFile, [$opArray, [], [], [new stdClass()]]),
];

foreach ($attempts as $index => $attempt) {
    try {
        $attempt();
        exit(10 + $index);
    } catch (InvalidArgumentException) {
    }
}
