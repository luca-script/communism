<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

use Zendful\Internals\Natives;
use Zendful\Zendful;

function forgedPlan(mixed $arguments): ?object
{
    if (!is_array($arguments)) {
        exit(1);
    }

    $reflection = new ReflectionClass('Zendful\\AssemblyPlanHandle');

    try {
        return call_user_func([$reflection, 'newInstanceArgs'], $arguments);
    } catch (InvalidArgumentException) {
        return null;
    }
}

eval('function probeTarget(): string { return "target"; }');

$operand = [
    'type' => 0,
    'kind' => 'unused',
    'value' => 0,
    'rawValue' => 0,
    'literalSlot' => null,
];
$instruction = [
    'opcode' => Zendful::opcodeId('NOP'),
    'extendedValue' => 0,
    'line' => 1,
    'originalIndex' => 0,
    'result' => $operand,
    'operand1' => $operand,
    'operand2' => $operand,
];
$constantOperand = [
    ...$operand,
    'type' => Natives::ZEND_IS_CONST,
    'kind' => 'constant',
    'value' => 'forged',
    'rawValue' => 0,
    'literalSlot' => 0,
];
$plans = [
    forgedPlan([-1, 0, [], []]),
    forgedPlan([0, 0, [1 => $instruction], []]),
    forgedPlan([0, 0, [[...$instruction, 'result' => ['type' => 0]]], []]),
    forgedPlan([0, 0, [[...$instruction, 'operand1' => [...$operand, 'kind' => 'forged']]], []]),
    forgedPlan([0, 0, [[...$instruction, 'operand1' => [...$operand, 'value' => new stdClass(), 'rawValue' => null]]], []]),
    forgedPlan([0, 0, [[...$instruction, 'result' => ['type' => 1, 'kind' => 'constant', 'value' => 'forged', 'rawValue' => null, 'literalSlot' => 99]]], [0 => 'literal']]),
    forgedPlan([0, 0, [[...$instruction, 'opcode' => Natives::ZEND_OPCODE_MAX + 1]], []]),
    forgedPlan([0, 0, [[...$instruction, 'line' => -1]], []]),
    forgedPlan([Natives::ZEND_UINT32_MAX + 1, 0, [], []]),
    forgedPlan([0, Natives::ZEND_INT32_MAX + 1, [], []]),
    forgedPlan([0, 0, [[...$instruction, 'extendedValue' => Natives::ZEND_UINT32_MAX + 1]], []]),
    forgedPlan([0, 0, [[...$instruction, 'operand1' => [...$operand, 'type' => Natives::ZEND_OP_TYPE_MAX]]], []]),
    forgedPlan([0, 0, [[...$instruction, 'operand1' => [...$operand, 'type' => Natives::ZEND_IS_CONST, 'kind' => 'unused']]], []]),
    forgedPlan([0, 0, [[...$instruction, 'operand1' => [...$operand, 'value' => -1, 'rawValue' => -1]]], []]),
    forgedPlan([0, 0, [[...$instruction, 'operand1' => [...$operand, 'value' => Natives::ZEND_UINT32_MAX + 1, 'rawValue' => Natives::ZEND_UINT32_MAX + 1]]], []]),
    forgedPlan([0, 0, [[...$instruction, 'result' => $constantOperand]], [0 => "bad\0literal"]]),
];
$target = Zendful::function('probeTarget')->opArray();
$assemble = new ReflectionMethod('Zendful\\Zendful', 'assemble');

foreach ($plans as $plan) {
    if ($plan === null) {
        continue;
    }

    try {
        $assemble->invoke(null, $target, $plan);
        exit(2);
    } catch (InvalidArgumentException) {
    }
}
