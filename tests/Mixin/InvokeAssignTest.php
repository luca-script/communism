<?php

declare(strict_types=1);

use Communism\Mixin\At;
use Communism\Mixin\CallbackInfo;
use Communism\Mixin\Inject;
use Communism\Mixin\Mixin;
use Communism\Reflect\ReflectionClass;

function invokeAssignValue(string $value): string
{
    return strtoupper($value);
}

function invokeAssignCaptured(): string
{
    $value = $GLOBALS['invoke_assign_captured'] ?? null;

    return is_string($value) ? $value : '';
}

#[Mixin(InvokeAssignTarget::class)]
final class InvokeAssignMixin
{
    private function __construct() {}
    #[Inject('run', new At('INVOKE_ASSIGN', 'invokeAssignValue'))]
    public function afterAssignment(CallbackInfo $info, string $result): void
    {
        $GLOBALS['invoke_assign_captured'] = $result;
    }
}


final class InvokeAssignTarget
{
    public function run(string $value): string
    {
        $result = invokeAssignValue($value);

        return $result;
    }
}

it('runs an INVOKE_ASSIGN callback after the local assignment', function (): void {
    unset($GLOBALS['invoke_assign_captured']);
    (new ReflectionClass(InvokeAssignTarget::class))->inject(InvokeAssignMixin::class);

    expect((new InvokeAssignTarget())->run('needle'))->toBe('NEEDLE')
        ->and(invokeAssignCaptured())->toBe('NEEDLE');
});

#[Mixin(InvokeAssignOrdinalTarget::class)]
final class InvokeAssignOrdinalMixin
{
    private function __construct() {}
    #[Inject('run', new At('INVOKE_ASSIGN', 'invokeAssignValue', ordinal: 1))]
    public function captureSecondAssignment(CallbackInfo $info, string $secondResult): void
    {
        $GLOBALS['invoke_assign_captured'] = $secondResult;
    }
}


final class InvokeAssignOrdinalTarget
{
    public function run(string $first, string $second): string
    {
        $firstResult = invokeAssignValue($first);
        $secondResult = invokeAssignValue($second);

        return $firstResult . ':' . $secondResult;
    }
}

it('selects one INVOKE_ASSIGN occurrence by ordinal', function (): void {
    unset($GLOBALS['invoke_assign_captured']);
    (new ReflectionClass(InvokeAssignOrdinalTarget::class))->inject(InvokeAssignOrdinalMixin::class);

    expect((new InvokeAssignOrdinalTarget())->run('first', 'second'))->toBe('FIRST:SECOND')
        ->and(invokeAssignCaptured())->toBe('SECOND');
});

#[Mixin(InvokeAssignMissingTarget::class)]
final class InvokeAssignMissingMixin
{
    private function __construct() {}
    #[Inject('run', new At('INVOKE_ASSIGN', 'invokeAssignValue'))]
    public function afterMissingAssignment(): void {}
}


final class InvokeAssignMissingTarget
{
    public function run(string $value): string
    {
        return invokeAssignValue($value);
    }
}

it('rejects INVOKE_ASSIGN when the invocation result is not assigned', function (): void {
    expect(function (): void {
        (new ReflectionClass(InvokeAssignMissingTarget::class))->inject(InvokeAssignMissingMixin::class);
    })->toThrow(InvalidArgumentException::class, 'Injection point did not match');

    expect((new InvokeAssignMissingTarget())->run('needle'))->toBe('NEEDLE');
});
