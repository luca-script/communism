<?php

declare(strict_types=1);

use Communism\Mixin\Args;
use Communism\Mixin\At;
use Communism\Mixin\Mixin;
use Communism\Mixin\ModifyArgs;
use Communism\Internals\Needle\Decompiler;
use Communism\Internals\Needle\Instruction;
use Communism\Reflect\ReflectionClass;

function modifyArgsJoin(mixed ...$values): string
{
    if (!isset($values[0], $values[1]) || !is_scalar($values[0]) || !is_scalar($values[1])) {
        throw new InvalidArgumentException('Expected two scalar values');
    }
    return sprintf('%s%s', $values[0], $values[1]);
}

#[Mixin(ModifyArgsTarget::class)]
final class ModifyArgsMixin
{
    private function __construct() {}
    #[ModifyArgs('run', new At('INVOKE', 'modifyArgsJoin'))]
    public function rewriteArguments(Args $args): void
    {
        $args->set(0, 10);
        $args->set(1, '!');
    }
}


final class ModifyArgsTarget
{
    public function run(int $first, string $second): string
    {
        return modifyArgsJoin($first, $second);
    }
}

it('lowers ModifyArgs mutations without creating an Args object', function (): void {
    (new ReflectionClass(ModifyArgsTarget::class))->inject(ModifyArgsMixin::class);

    expect((new ModifyArgsTarget())->run(2, 'original'))->toBe('10!');

    $body = Decompiler::decompile(ModifyArgsTarget::class . '::run');
    $names = array_map(static fn(Instruction $instruction): string => $instruction->name, $body->instructions());
    $sendValues = [];
    foreach ($body->instructions() as $instruction) {
        if ($instruction->name === 'SEND_VAL' && $instruction->operand1->kind === 'constant') {
            $sendValues[] = $instruction->operand1->value;
        }
    }

    expect($names)->not->toContain('INIT_ARRAY')
        ->and($names)->not->toContain('NEW')
        ->and($sendValues)->toContain(10)
        ->and($sendValues)->toContain('!');
});

#[Mixin(ModifyArgsSetAllTarget::class)]
final class ModifyArgsSetAllMixin
{
    private function __construct() {}
    #[ModifyArgs('run', new At('INVOKE', 'modifyArgsJoin'))]
    public function rewriteAllArguments(Args $args): void
    {
        $args->setAll([10, '!']);
    }
}


final class ModifyArgsSetAllTarget
{
    public function run(int $first, string $second): string
    {
        return modifyArgsJoin($first, $second);
    }
}

it('lowers ModifyArgs setAll directly into invocation operands', function (): void {
    (new ReflectionClass(ModifyArgsSetAllTarget::class))->inject(ModifyArgsSetAllMixin::class);

    expect((new ModifyArgsSetAllTarget())->run(2, 'original'))->toBe('10!');

    $body = Decompiler::decompile(ModifyArgsSetAllTarget::class . '::run');
    $names = array_map(static fn(Instruction $instruction): string => $instruction->name, $body->instructions());

    expect($names)->not->toContain('INIT_ARRAY')
        ->and($names)->not->toContain('NEW');
});

function modifyArgsCount(int $first, int $second, int $third): int
{
    return $first + $second + $third;
}

function modifyArgsIncrement(mixed $value, int $count): int
{
    if (!is_int($value)) {
        throw new InvalidArgumentException('Expected an integer argument');
    }

    return $value + $count;
}

#[Mixin(ModifyArgsReadTarget::class, ModifyArgsReadStructuralTarget::class)]
final class ModifyArgsReadMixin
{
    private function __construct() {}
    #[ModifyArgs('run', new At('INVOKE', 'modifyArgsCount'))]
    public function rewriteUsingReads(Args $args): void
    {
        $args->set(0, modifyArgsIncrement($args->get(0), $args->getCount()));
    }
}


final class ModifyArgsReadTarget
{
    public function run(int $first, int $second, int $third): int
    {
        return modifyArgsCount($first, $second, $third);
    }
}

final class ModifyArgsReadStructuralTarget
{
    public function run(int $first, int $second, int $third): int
    {
        return modifyArgsCount($first, $second, $third);
    }
}

it('lowers ModifyArgs reads and count into the matched invocation', function (): void {
    (new ReflectionClass(ModifyArgsReadTarget::class))->inject(ModifyArgsReadMixin::class);

    expect((new ModifyArgsReadTarget())->run(2, 3, 4))->toBe(12);
});

it('does not emit INIT_ARRAY while lowering ModifyArgs reads', function (): void {
    (new ReflectionClass(ModifyArgsReadStructuralTarget::class))->inject(ModifyArgsReadMixin::class);
    $body = Decompiler::decompile(ModifyArgsReadStructuralTarget::class . '::run');
    $names = array_map(static fn(Instruction $instruction): string => $instruction->name, $body->instructions());

    expect($names)->not->toContain('INIT_ARRAY')
        ->and($names)->not->toContain('NEW');
});

it('keeps the Args surface virtual at runtime', function (): void {
    expect(fn(): Args => new Args())
        ->toThrow(LogicException::class, 'virtual');
});

#[Mixin(InvalidModifyArgsTarget::class)]
final class InvalidModifyArgsMixin
{
    private function __construct() {}
    #[ModifyArgs('run', new At('INVOKE', 'modifyArgsJoin'))]
    public function invalidHandler(string $value): void {}
}

final class InvalidModifyArgsTarget
{
    public function run(int $first, string $second): string
    {
        return modifyArgsJoin($first, $second);
    }
}


it('rejects ModifyArgs handlers without exactly one Args parameter', function (): void {
    expect(function (): void {
        (new ReflectionClass(InvalidModifyArgsTarget::class))->inject(InvalidModifyArgsMixin::class);
    })->toThrow(InvalidArgumentException::class, 'exactly one Args parameter');

    expect((new InvalidModifyArgsTarget())->run(2, 'original'))->toBe('2original');
});

#[Mixin(EscapingModifyArgsTarget::class)]
final class EscapingModifyArgsMixin
{
    private function __construct() {}
    #[ModifyArgs('run', new At('INVOKE', 'modifyArgsJoin'))]
    public function escapeArgs(Args $args): mixed
    {
        return $args;
    }
}


final class EscapingModifyArgsTarget
{
    public function run(int $first, string $second): string
    {
        return modifyArgsJoin($first, $second);
    }
}

it('rejects a virtual Args value escaping through a handler return', function (): void {
    expect(function (): void {
        (new ReflectionClass(EscapingModifyArgsTarget::class))->inject(EscapingModifyArgsMixin::class);
    })->toThrow(InvalidArgumentException::class, 'Args value escaped');

    expect((new EscapingModifyArgsTarget())->run(2, 'original'))->toBe('2original');
});

#[Mixin(InvalidModifyArgsIndexTarget::class)]
final class InvalidModifyArgsIndexMixin
{
    private function __construct() {}
    #[ModifyArgs('run', new At('INVOKE', 'modifyArgsJoin'))]
    public function invalidIndex(Args $args): void
    {
        $args->set(2, '!');
    }
}

final class InvalidModifyArgsIndexTarget
{
    public function run(int $first, string $second): string
    {
        return modifyArgsJoin($first, $second);
    }
}


it('rejects ModifyArgs indexes outside the invocation', function (): void {
    expect(function (): void {
        (new ReflectionClass(InvalidModifyArgsIndexTarget::class))->inject(InvalidModifyArgsIndexMixin::class);
    })->toThrow(InvalidArgumentException::class, 'argument index and value');

    expect((new InvalidModifyArgsIndexTarget())->run(2, 'original'))->toBe('2original');
});

#[Mixin(UnknownModifyArgsIndexTarget::class)]
final class UnknownModifyArgsIndexMixin
{
    private function __construct() {}
    #[ModifyArgs('run', new At('INVOKE', 'modifyArgsJoin'))]
    public function unknownIndex(Args $args): void
    {
        $index = 0;
        $args->set($index, '!');
    }
}

final class UnknownModifyArgsIndexTarget
{
    public function run(int $first, string $second): string
    {
        return modifyArgsJoin($first, $second);
    }
}


it('rejects ModifyArgs replacements when the argument index is not constant', function (): void {
    expect(function (): void {
        (new ReflectionClass(UnknownModifyArgsIndexTarget::class))->inject(UnknownModifyArgsIndexMixin::class);
    })->toThrow(InvalidArgumentException::class, 'argument index and value');

    expect((new UnknownModifyArgsIndexTarget())->run(2, 'original'))->toBe('2original');
});

#[Mixin(InvalidModifyArgsMethodTarget::class)]
final class InvalidModifyArgsMethodMixin
{
    private function __construct() {}
    #[ModifyArgs('run', new At('INVOKE', 'modifyArgsJoin'))]
    public function unsupportedMethod(Args $args): void
    {
        $args->offsetUnset(0);
    }
}

final class InvalidModifyArgsMethodTarget
{
    public function run(int $first, string $second): string
    {
        return modifyArgsJoin($first, $second);
    }
}


it('rejects unsupported virtual Args methods before mutation', function (): void {
    expect(function (): void {
        (new ReflectionClass(InvalidModifyArgsMethodTarget::class))->inject(InvalidModifyArgsMethodMixin::class);
    })->toThrow(InvalidArgumentException::class, 'Unsupported virtual Args method');

    expect((new InvalidModifyArgsMethodTarget())->run(2, 'original'))->toBe('2original');
});

#[Mixin(DynamicModifyArgsSetAllTarget::class)]
final class DynamicModifyArgsSetAllMixin
{
    private function __construct() {}
    #[ModifyArgs('run', new At('INVOKE', 'modifyArgsJoin'))]
    public function dynamicSetAll(Args $args): void
    {
        $values = [10, '!'];
        $args->setAll($values);
    }
}


final class DynamicModifyArgsSetAllTarget
{
    public function run(int $first, string $second): string
    {
        return modifyArgsJoin($first, $second);
    }
}

it('lowers a statically known ModifyArgs setAll local without constructing Args', function (): void {
    (new ReflectionClass(DynamicModifyArgsSetAllTarget::class))->inject(DynamicModifyArgsSetAllMixin::class);

    expect((new DynamicModifyArgsSetAllTarget())->run(2, 'original'))->toBe('10!');
});

/** @return array<int, mixed> */
function runtimeModifyArgsValues(): array
{
    return [10, '!'];
}

#[Mixin(RuntimeModifyArgsSetAllTarget::class)]
final class RuntimeModifyArgsSetAllMixin
{
    private function __construct() {}

    #[ModifyArgs('run', new At('INVOKE', 'modifyArgsJoin'))]
    public function runtimeSetAll(Args $args): void
    {
        $values = runtimeModifyArgsValues();
        $args->setAll($values);
    }
}

final class RuntimeModifyArgsSetAllTarget
{
    public function run(int $first, string $second): string
    {
        return modifyArgsJoin($first, $second);
    }
}

it('rejects runtime-built ModifyArgs setAll lists before mutation', function (): void {
    expect(static function (): void {
        (new ReflectionClass(RuntimeModifyArgsSetAllTarget::class))->inject(RuntimeModifyArgsSetAllMixin::class);
    })->toThrow(InvalidArgumentException::class, 'statically known list of values');

    expect((new RuntimeModifyArgsSetAllTarget())->run(2, 'original'))->toBe('2original');
});

final class ModifyArgsEvaluationOrder
{
    /** @var list<string> */
    public static array $values = [];
}

function modifyArgsOrderedValue(string $value): int
{
    ModifyArgsEvaluationOrder::$values[] = $value;

    return count(ModifyArgsEvaluationOrder::$values);
}

function modifyArgsJoinOrdered(mixed ...$values): string
{
    return implode(',', array_map(static function (mixed $value): string {
        if (!is_scalar($value)) {
            throw new InvalidArgumentException('Expected scalar value');
        }
        return sprintf('%s', $value);
    }, $values));
}

#[Mixin(ModifyArgsEvaluationTarget::class)]
final class ModifyArgsEvaluationMixin
{
    private function __construct() {}

    #[ModifyArgs('run', new At('INVOKE', 'modifyArgsJoinOrdered'))]
    public function rewriteInSourceOrder(Args $args): void
    {
        $args->setAll([modifyArgsOrderedValue('first'), 20, modifyArgsOrderedValue('third')]);
    }
}

final class ModifyArgsEvaluationTarget
{
    public function run(int $first, int $second, int $third): string
    {
        return modifyArgsJoinOrdered($first, $second, $third);
    }
}

it('evaluates variable setAll values from left to right', function (): void {
    ModifyArgsEvaluationOrder::$values = [];
    (new ReflectionClass(ModifyArgsEvaluationTarget::class))->inject(ModifyArgsEvaluationMixin::class);

    expect((new ModifyArgsEvaluationTarget())->run(1, 2, 3))->toBe('1,20,2')
        ->and(ModifyArgsEvaluationOrder::$values)->toBe(['first', 'third']);
});

function modifyArgsFixedJoin(int $first, string $second): string
{
    return $first . $second;
}

#[Mixin(ModifyArgsFixedValuesTarget::class)]
final class ModifyArgsFixedValuesMixin
{
    private function __construct() {}

    #[ModifyArgs('run', new At('INVOKE', 'modifyArgsFixedJoin'))]
    public function rewriteFixedValues(Args $args): void
    {
        $args->setAll([modifyArgsOrderedValue('fixed'), '!']);
    }
}

final class ModifyArgsFixedValuesTarget
{
    public function run(int $first, string $second): string
    {
        return modifyArgsFixedJoin($first, $second);
    }
}

it('allows variable setAll values for fixed-arity invocations when length is known', function (): void {
    ModifyArgsEvaluationOrder::$values = [];
    (new ReflectionClass(ModifyArgsFixedValuesTarget::class))->inject(ModifyArgsFixedValuesMixin::class);

    expect((new ModifyArgsFixedValuesTarget())->run(1, 'original'))->toBe('1!')
        ->and(ModifyArgsEvaluationOrder::$values)->toBe(['fixed']);
});

#[Mixin(ModifyArgsWrongLengthTarget::class)]
final class ModifyArgsWrongLengthMixin
{
    private function __construct() {}

    #[ModifyArgs('run', new At('INVOKE', 'modifyArgsJoin'))]
    public function rewriteWrongLength(Args $args): void
    {
        $args->setAll([10]);
    }
}

final class ModifyArgsWrongLengthTarget
{
    public function run(int $first, string $second): string
    {
        return modifyArgsJoin($first, $second);
    }
}

it('rejects a known setAll length mismatch before mutation', function (): void {
    expect(static function (): void {
        (new ReflectionClass(ModifyArgsWrongLengthTarget::class))->inject(ModifyArgsWrongLengthMixin::class);
    })->toThrow(InvalidArgumentException::class, 'exactly one value per argument');

    expect((new ModifyArgsWrongLengthTarget())->run(2, 'original'))->toBe('2original');
});

#[Mixin(OperandModifyArgsSetAllTarget::class)]
final class OperandModifyArgsSetAllMixin
{
    private function __construct() {}

    #[ModifyArgs('run', new At('INVOKE', 'modifyArgsJoin'))]
    public function copyFirstArgument(Args $args): void
    {
        $values = [$args->get(0), '!'];
        $args->setAll($values);
    }
}

final class OperandModifyArgsSetAllTarget
{
    public function run(int $first, string $second): string
    {
        return modifyArgsJoin($first, $second);
    }
}

it('lowers setAll arrays containing lowered Args operands', function (): void {
    (new ReflectionClass(OperandModifyArgsSetAllTarget::class))->inject(OperandModifyArgsSetAllMixin::class);

    expect((new OperandModifyArgsSetAllTarget())->run(2, 'original'))->toBe('2!');
});
