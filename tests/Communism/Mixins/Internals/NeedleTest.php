<?php

declare(strict_types=1);

use Communism\Internals\Needle\Assembler;
use Communism\Internals\Needle\Decompiler;
use Communism\Internals\Needle\Instruction;
use Communism\Internals\Needle\Injector;
use Communism\Internals\Needle\Matcher;
use Communism\Internals\Needle\MethodBody;
use Communism\Internals\Needle\Operand;
use Communism\Mixin\CallbackInfo;
use Communism\Mixin\CallbackInfoReturnable;
use Communism\Mixin\At;
use Communism\Mixin\Group;
use Communism\Mixin\Inject;
use Zendful\Internals\Natives;
use Zendful\Zendful;

function needleCoverageFunction(string $value): string
{
    return $value . '!';
}

final class DecompilerCallableCoverageTarget
{
    public function __invoke(string $value): string
    {
        return $value . '?';
    }
}

final class NeedleFixture
{
    public string $member = '';

    public function greet(string $name): string
    {
        return $name . '.';
    }

    public function add(int $left, int $right): int
    {
        return $left + $right;
    }

    public function subtract(int $left, int $right): int
    {
        return $left - $right;
    }

    public function setMember(): void
    {
        $this->member = 'needle';
    }

    public function localAssign(string $value): string
    {
        $result = $value;

        return $result;
    }

    public function invoke(string $value): string
    {
        return strtoupper($value);
    }

    public static function staticInvoke(string $value): string
    {
        return strtoupper($value);
    }

    public function invokeTwice(string $value): string
    {
        return strtoupper($value) . strtoupper($value);
    }

    public function nullReturn(bool $returnNull): ?int
    {
        if ($returnNull) {
            return null;
        }

        return 1;
    }

    public function valueReturn(): int
    {
        return 1;
    }

    public function throwRuntime(): void
    {
        throw new RuntimeException('needle');
    }
}

it('keeps CallbackInfo virtual at runtime', function (): void {
    expect(fn(): CallbackInfo => new CallbackInfo())
        ->toThrow(LogicException::class, 'virtual');
    expect(fn(): CallbackInfoReturnable => new CallbackInfoReturnable())
        ->toThrow(LogicException::class, 'virtual');
});

it('preserves negative relative literal offsets from OPcache', function (): void {
    $node = Natives::ffi()->new('znode_op');
    $node->constant = -32;

    expect($node->constant)->toBe(-32);
});

final class NeedleCallTarget
{
    public static function staticTarget(): string
    {
        return 'static';
    }
}

final class NeedleCallFixture
{
    public function memberTarget(): string
    {
        return 'member';
    }

    public function callTargets(): string
    {
        return NeedleCallTarget::staticTarget() . $this->memberTarget();
    }
}

it('decompiles bytecode into a transformable method body', function (): void {
    $body = Decompiler::decompile([new NeedleFixture(), 'greet']);

    expect($body->name)->toBe('NeedleFixture::greet');
    expect($body->count())->toBeGreaterThan(0);
    expect(array_column($body->instructions(), 'name'))->toContain('CONCAT');
});

it('resolves every supported callable shape and rejects internal bytecode', function (): void {
    expect(fn(): MethodBody => Decompiler::decompile('strlen'))
        ->toThrow(InvalidArgumentException::class, 'has no userland bytecode');

    expect(Decompiler::decompile('needleCoverageFunction'))->toBeInstanceOf(MethodBody::class)
        ->and(Decompiler::decompile([NeedleFixture::class, 'staticInvoke']))->toBeInstanceOf(MethodBody::class)
        ->and(Decompiler::decompile(new DecompilerCallableCoverageTarget()))->toBeInstanceOf(MethodBody::class)
        ->and(fn(): MethodBody => Decompiler::decompile('NeedleFixture::missing'))
        ->toThrow(InvalidArgumentException::class);
});

it('rewrites constants into the replacement literal pool', function (): void {
    $body = Decompiler::decompile(NeedleFixture::class . '::greet');
    $opArray = Zendful::method(NeedleFixture::class, 'greet')->opArray();

    foreach ($body->instructions() as $index => $instruction) {
        if ($instruction->operand2->kind !== Operand::CONSTANT) {
            continue;
        }

        $modified = $body->replace(
            $index,
            $instruction->withOperands($instruction->operand1, $instruction->operand2->withValue('!')),
        );

        try {
            Assembler::write($modified, $opArray);
            expect((new NeedleFixture())->greet('needle'))->toBe('needle!');
        } finally {
            Assembler::write($body, $opArray);
        }

        return;
    }

    throw new RuntimeException('Could not find the greeting literal');
});

it('rewrites a body whose instruction count changed', function (): void {
    $body = Decompiler::decompile(NeedleFixture::class . '::greet');
    $opArray = Zendful::method(NeedleFixture::class, 'greet')->opArray();

    $nop = new Instruction(
        Zendful::opcodeId('NOP'),
        'NOP',
        Operand::unused(),
        Operand::unused(),
        Operand::unused(),
    );

    try {
        Assembler::write($body->insertBefore($body->count() - 1, $nop), $opArray);

        expect((new NeedleFixture())->greet('needle'))->toBe('needle.');
    } finally {
        Assembler::write($body, $opArray);
    }
});

it('replaces an ADD opcode with SUB', function (): void {
    $targetBody = Decompiler::decompile(NeedleFixture::class . '::add');
    $subtractBody = Decompiler::decompile(NeedleFixture::class . '::subtract');
    $targetOpArray = Zendful::method(NeedleFixture::class, 'add')->opArray();

    $addIndex = null;
    foreach ($targetBody->instructions() as $index => $instruction) {
        if ($instruction->name === 'ADD') {
            $addIndex = $index;
            break;
        }
    }

    $subInstruction = null;
    foreach ($subtractBody->instructions() as $instruction) {
        if ($instruction->name === 'SUB') {
            $subInstruction = $instruction;
            break;
        }
    }

    if ($addIndex === null || $subInstruction === null) {
        throw new RuntimeException('Could not find ADD and SUB fixture opcodes');
    }

    try {
        $modified = $targetBody->replace($addIndex, $targetBody->instruction($addIndex)->withOpcode(Zendful::opcodeId('SUB'), 'SUB', $subInstruction->handler));
        Assembler::write($modified, $targetOpArray);

        expect((new NeedleFixture())->add(7, 3))->toBe(4);
    } finally {
        Assembler::write($targetBody, $targetOpArray);
    }
});

it('locates declarative assignment, return, and invocation anchors', function (): void {
    $member = Matcher::find(Decompiler::decompile(NeedleFixture::class . '::setMember'), new At('FIELD', '::member'));
    $local = Matcher::find(Decompiler::decompile(NeedleFixture::class . '::localAssign'), new At('STORE', 'result'));
    $returns = Matcher::find(Decompiler::decompile(NeedleFixture::class . '::invoke'), new At('RETURN'));
    $calls = Matcher::find(Decompiler::decompile(NeedleFixture::class . '::invoke'), new At('INVOKE', ['strtoupper', ['numargs' => 1]]));

    expect($member)->toHaveCount(1);
    expect($member[0]->length())->toBe(2);
    expect($local)->toHaveCount(1);
    expect($returns)->toHaveCount(2);
    expect($calls)->toHaveCount(1);
    expect($calls[0]->action)->toBe('before');
});

it('matches wildcard global, static, and member invocation selectors', function (): void {
    $body = Decompiler::decompile(NeedleCallFixture::class . '::callTargets');

    expect(Matcher::find($body, new At('INVOKE', '*')))->toHaveCount(0)
        ->and(Matcher::find($body, new At('INVOKE', 'NeedleCallTarget::*')))->toHaveCount(1)
        ->and(Matcher::find($body, new At('INVOKE', '::*')))->toHaveCount(1)
        ->and(Matcher::find($body, new At('INVOKE', '->*')))->toHaveCount(1);
});

it('resolves every supported injection point type', function (): void {
    $member = Matcher::find(Decompiler::decompile(NeedleFixture::class . '::setMember'), new At('FIELD', '::member'));
    $variable = Matcher::find(Decompiler::decompile(NeedleFixture::class . '::localAssign'), new At('STORE', 'result'));
    $anyReturn = Matcher::find(Decompiler::decompile(NeedleFixture::class . '::nullReturn'), new At('RETURN'));
    $valueReturn = Matcher::find(Decompiler::decompile(NeedleFixture::class . '::valueReturn'), new At('RETURN'));
    $beforeReturn = Matcher::find(Decompiler::decompile(NeedleFixture::class . '::valueReturn'), new At('RETURN', '', -1, 'BEFORE', 0, 'before'));
    $modifyReturn = Matcher::find(Decompiler::decompile(NeedleFixture::class . '::valueReturn'), new At('RETURN', '', -1, 'BEFORE', 0, 'modify'));
    $begin = Matcher::find(Decompiler::decompile(NeedleFixture::class . '::valueReturn'), new At('HEAD'));
    $function = Matcher::find(Decompiler::decompile(NeedleFixture::class . '::invoke'), new At('INVOKE', 'strtoupper'));
    $replace = Matcher::find(Decompiler::decompile(NeedleFixture::class . '::invoke'), new At('INVOKE', ['strtoupper', ['numargs' => [1, 3, 2]]], -1, 'BEFORE', 0, 'replace'));
    $after = Matcher::find(Decompiler::decompile(NeedleFixture::class . '::invoke'), new At('INVOKE', 'strtoupper', -1, 'AFTER'));
    $static = Matcher::find(Decompiler::decompile(NeedleCallFixture::class . '::callTargets'), new At('INVOKE', NeedleCallTarget::class . '::staticTarget'));
    $memberCall = Matcher::find(Decompiler::decompile(NeedleCallFixture::class . '::callTargets'), new At('INVOKE', '->memberTarget'));
    $throw = Matcher::find(Decompiler::decompile(NeedleFixture::class . '::throwRuntime'), new At('THROW'));
    $typedThrow = Matcher::find(Decompiler::decompile(NeedleFixture::class . '::throwRuntime'), new At('THROW', RuntimeException::class));

    expect($member)->toHaveCount(1);
    expect($variable)->toHaveCount(1);
    expect($anyReturn)->toHaveCount(3);
    expect($valueReturn)->toHaveCount(2);
    expect($beforeReturn)->toHaveCount(2);
    expect($beforeReturn[0]->action)->toBe('before');
    expect($modifyReturn)->toHaveCount(2);
    expect($modifyReturn[0]->action)->toBe('modify');
    expect($begin)->toHaveCount(1);
    expect($begin[0]->start)->toBe(0);
    expect($begin[0]->end)->toBe(0);
    expect($function)->toHaveCount(1);
    expect($replace[0]->action)->toBe('replace');
    expect($after[0]->action)->toBe('after');
    expect($static)->toHaveCount(1);
    expect($memberCall)->toHaveCount(1);
    expect($throw)->toHaveCount(1);
    expect($typedThrow)->toHaveCount(1);
});

it('resolves all injections and all matching spots before emission', function (): void {
    $body = Decompiler::decompile(NeedleFixture::class . '::invokeTwice');
    $resolved = Injector::resolve($body, [
        new Inject('invokeTwice', new At('INVOKE', 'strtoupper')),
        new Inject('invokeTwice', new At('RETURN')),
    ]);

    expect($resolved)->toHaveCount(2);
    expect($resolved[0]->matches)->toHaveCount(2);
    expect($resolved[0]->matches[0]->start)->toBeLessThan($resolved[0]->matches[1]->start);
    expect($resolved[1]->matches)->toHaveCount(2);
});

it('rejects malformed injection and rewrite callbacks', function (): void {
    $body = Decompiler::decompile(NeedleFixture::class . '::greet');
    $inject = new Inject('greet', new At('RETURN'));

    expect(fn(): MethodBody => Injector::inject($body, [$inject], static fn(): string => 'invalid'))
        ->toThrow(InvalidArgumentException::class, 'must return a MethodBody');

    $opArray = Zendful::method(NeedleFixture::class, 'greet')->opArray();
    expect(fn() => Injector::rewrite($body, $opArray, [$inject], static fn(): string => 'invalid'))
        ->toThrow(InvalidArgumentException::class, 'must return a MethodBody');
});

it('rejects conflicting replacement placements', function (): void {
    $body = Decompiler::decompile(NeedleFixture::class . '::greet');
    $handler = Decompiler::decompile(NeedleFixture::class . '::valueReturn');
    $inject = new Inject('greet', new At('RETURN', action: 'replace'));

    expect(fn(): MethodBody => Injector::inject(
        $body,
        [$inject, $inject],
        static fn(): MethodBody => $handler,
    ))->toThrow(InvalidArgumentException::class, 'same spot');
});

it('merges before and replacement placements at one anchor', function (): void {
    $body = Decompiler::decompile(NeedleFixture::class . '::greet');
    $handler = Decompiler::decompile(NeedleFixture::class . '::valueReturn');

    $rewritten = Injector::inject(
        $body,
        [
            new Inject('greet', new At('RETURN', action: 'before')),
            new Inject('greet', new At('RETURN', action: 'replace')),
        ],
        static fn(): MethodBody => $handler,
    );

    expect($rewritten)->toBeInstanceOf(MethodBody::class);
});

it('resolves the complete injection set before reassigning opcode storage', function (): void {
    $body = Decompiler::decompile(NeedleFixture::class . '::greet');
    $opArray = Zendful::method(NeedleFixture::class, 'greet')->opArray();

    $resolvedCount = 0;
    try {
        Injector::rewrite(
            $body,
            $opArray,
            [new Inject('greet', new At('RETURN'))],
            static function (MethodBody $original, array $resolved) use (&$resolvedCount): MethodBody {
                $resolvedCount = count($resolved);

                return $original;
            },
        );

        expect($resolvedCount)->toBe(1);
        expect((new NeedleFixture())->greet('needle'))->toBe('needle.');
    } finally {
        Assembler::write($body, $opArray);
    }
});

it('does not invoke the rewriter when any injection fails to resolve', function (): void {
    $body = Decompiler::decompile(NeedleFixture::class . '::greet');
    $opArray = Zendful::method(NeedleFixture::class, 'greet')->opArray();

    $rewriterCalled = false;
    expect(function () use ($body, $opArray, &$rewriterCalled): void {
        Injector::rewrite(
            $body,
            $opArray,
            [
                new Inject('greet', new At('RETURN')),
                new Inject('greet', new At('INVOKE', 'missing_function')),
            ],
            static function (MethodBody $original, array $resolved) use (&$rewriterCalled): MethodBody {
                $rewriterCalled = true;

                return $original;
            },
        );
    })->toThrow(InvalidArgumentException::class, 'Injection point did not match');

    expect($rewriterCalled)->toBeFalse();
    expect((new NeedleFixture())->greet('needle'))->toBe('needle.');
});

it('requires Mixin-style At injection points', function (): void {
    $inject = new Inject('my_method', new At('FIELD', '::my_member'));

    expect($inject->method)->toBe('my_method');
    expect($inject->at->type())->toBe('FIELD');
    expect($inject->at->target)->toBe('::my_member');
    expect(static function (): void {
        (new \ReflectionClass(Inject::class))->newInstanceArgs(['my_method', ['assign', '::my_member']]);
    })
        ->toThrow(TypeError::class);
});

it('supports Mixin-style injection metadata and match-count guarantees', function (): void {
    $inject = new Inject('greet', new At('RETURN'), true, 1);

    expect($inject->at->type())->toBe('RETURN');
    expect($inject->at)->toBeInstanceOf(At::class);

    $body = Decompiler::decompile(NeedleFixture::class . '::greet');
    expect(Injector::resolve($body, [$inject]))->toHaveCount(1);
    expect(fn(): array => Injector::resolve($body, [new Inject('greet', new At('RETURN'), true, 3)]))
        ->toThrow(InvalidArgumentException::class, 'requires at least 3');
    expect(fn(): array => Injector::resolve($body, [new Inject('greet', new At('RETURN'), true, null, 1)]))
        ->toThrow(InvalidArgumentException::class, 'expects 1');
    expect(fn(): array => Injector::resolve($body, [new Inject('greet', new At('RETURN'), true, null, null, 0)]))
        ->toThrow(InvalidArgumentException::class, 'allows at most 0');
    expect(Injector::resolve($body, [new Inject('greet', new At('INVOKE', 'missing'), true, null, 0)]))
        ->toHaveCount(1);
});

it('selects injection points by ordinal', function (): void {
    $body = Decompiler::decompile(NeedleFixture::class . '::invokeTwice');

    $matches = Matcher::find($body, new At('INVOKE', 'strtoupper', 1));

    expect($matches)->toHaveCount(1);
    expect($matches[0]->start)->toBeGreaterThan(
        Matcher::find($body, new At('INVOKE', 'strtoupper'))[0]->start,
    );
});

it('restricts injection resolution to a Mixin-style slice', function (): void {
    $body = Decompiler::decompile(NeedleFixture::class . '::invokeTwice');
    $slice = new \Communism\Mixin\Slice(
        new At('INVOKE', 'strtoupper', 0),
        new At('INVOKE', 'strtoupper', 1),
    );

    $matches = Matcher::find($body, new At('INVOKE', 'strtoupper'), null, $slice);

    expect($matches)->toHaveCount(1);
    expect($matches[0]->start)->toBe(
        Matcher::find($body, new At('INVOKE', 'strtoupper', 0))[0]->start,
    );
});

it('shifts callback injection points by a bounded instruction distance', function (): void {
    $body = Decompiler::decompile(NeedleFixture::class . '::invoke');
    $base = Matcher::find($body, new At('INVOKE', 'strtoupper'))[0];
    $shifted = Matcher::find($body, new At('INVOKE', 'strtoupper', -1, 'BY', 1))[0];

    expect($shifted->start)->toBe($base->start + 1);
    expect($shifted->end)->toBe($shifted->start);
});

it('validates slices, groups, and safe shift distances before rewriting', function (): void {
    $body = Decompiler::decompile(NeedleFixture::class . '::invokeTwice');

    expect(fn(): At => new At('INVOKE', 'strtoupper', -1, 'BY', 0))
        ->toThrow(InvalidArgumentException::class, 'positive distance');
    expect(fn(): At => new At('INVOKE', 'strtoupper', -1, 'BY', 33))
        ->toThrow(InvalidArgumentException::class, 'must not exceed 32');
    expect(fn(): array => Matcher::find(
        $body,
        new At('INVOKE', 'strtoupper'),
        null,
        new \Communism\Mixin\Slice(new At('INVOKE', 'strtoupper', 1), new At('INVOKE', 'strtoupper', 0)),
    ))->toThrow(InvalidArgumentException::class, 'reversed');

    expect(fn(): Group => new Group('invalid', 2, 1))
        ->toThrow(InvalidArgumentException::class, '0 <= min <= max');
    expect(fn(): array => Injector::resolve($body, [
        new Inject('invokeTwice', new At('INVOKE', 'strtoupper'), true, null, null, null, null, null, new Group('calls', 3)),
    ]))->toThrow(InvalidArgumentException::class, 'Injection group calls');
});

it('rejects malformed injection-point declarations at the matcher boundary', function (): void {
    $invalid = [
        new At('INVOKE', 'strtoupper', -1, 'BY', 1, 'replace'),
        new At('INVOKE', 'strtoupper', -1, 'BEFORE', 0, 'modify', 'ADD'),
        new At('JUMP', 'conditional', opcode: 'JUMP_FORWARD'),
        new At('HEAD', 'unexpected'),
        new At('INVOKE', 12),
        new At('INVOKE', 'strtoupper', action: 'modify'),
        new At('INVOKE_ASSIGN', 'strtoupper', action: 'before'),
        new At('NEW', 12),
        new At('NEW', 'Target', action: 'after'),
        new At('JUMP', 12),
        new At('JUMP', 'conditional', action: 'replace'),
        new At('FIELD', 12),
        new At('FIELD', '::value', action: 'before'),
        new At('STORE', 12),
        new At('STORE', 'value', action: 'before'),
        new At('THROW', 12),
        new At('THROW', RuntimeException::class, action: 'before'),
        new At('UNKNOWN'),
    ];

    foreach ($invalid as $index => $at) {
        try {
            Matcher::validateAt($at);
        } catch (InvalidArgumentException) {
            continue;
        }

        throw new RuntimeException(sprintf('Invalid At case %d was accepted', $index));
    }

    expect($invalid)->toHaveCount(18);
});
