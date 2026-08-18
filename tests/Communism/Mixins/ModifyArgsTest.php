<?php

declare(strict_types=1);

use Communism\Mixin\Args;
use Communism\Mixin\At;
use Communism\Mixin\Mixin;
use Communism\Mixin\ModifyArgs;
use Communism\Internals\Needle\Decompiler;
use Communism\Internals\Needle\Instruction;
use Communism\Reflect\ReflectionClass;

function modifyArgsJoin(int $first, string $second): string
{
    return $first . $second;
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

it('rejects dynamic ModifyArgs setAll values before mutation', function (): void {
    expect(function (): void {
        (new ReflectionClass(DynamicModifyArgsSetAllTarget::class))->inject(DynamicModifyArgsSetAllMixin::class);
    })->toThrow(InvalidArgumentException::class, 'statically known list of values');

    expect((new DynamicModifyArgsSetAllTarget())->run(2, 'original'))->toBe('2original');
});
