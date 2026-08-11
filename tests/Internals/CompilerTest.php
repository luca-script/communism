<?php

declare(strict_types=1);

use Communism\Internals\Needle\Compiler;

it('compiles a file into bytecode without executing it', function (): void {
    $compiled = Compiler::compileFile(__DIR__ . '/../StaticAnalysis/compile-only.php');

    expect($compiled->body->count())->toBeGreaterThan(0)
        ->and($compiled->declaredClasses)->toContain('compileonlytarget')
        ->and($compiled->class('CompileOnlyTarget')?->method('run')?->body->count())->toBeGreaterThan(0);
});

it('snapshots namespaced classes and functions before destroying compiler storage', function (): void {
    $compiled = Compiler::compileFile(__DIR__ . '/../StaticAnalysis/compile-targets.php');

    expect($compiled->declaredClasses)
        ->toContain('compilefixture\\firsttarget')
        ->toContain('compilefixture\\secondtarget')
        ->and(array_map(
            static fn($instruction): string => $instruction->name,
            $compiled->class('CompileFixture\\SecondTarget')?->method('subtract')?->body->instructions() ?? [],
        ))
        ->toContain('SUB')
        ->and(array_map(static fn($function): string => $function->name, $compiled->functions))
        ->toContain('CompileFixture\\combine');
});

it('does not install declarations and can compile the same file repeatedly', function (): void {
    Compiler::compileFile(__DIR__ . '/../StaticAnalysis/compile-only.php');
    $compiled = Compiler::compileFile(__DIR__ . '/../StaticAnalysis/compile-only.php');

    expect(class_exists('CompileOnlyTarget', false))->toBeFalse()
        ->and($compiled->class('CompileOnlyTarget'))->not->toBeNull();
});

it('reads a class from a source file whose class is already loaded', function (): void {
    $compiled = Compiler::compileFile(__DIR__ . '/../../src/Communism/Internals/Needle/Compiler.php');

    expect($compiled->class(Compiler::class)?->method('compileFile'))->not->toBeNull();
});
