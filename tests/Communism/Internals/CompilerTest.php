<?php

declare(strict_types=1);

use Communism\Internals\Needle\Compiler;

it('rejects a missing compiler source file before entering Zend', function (): void {
    expect(fn(): mixed => Compiler::compileFile(__DIR__ . '/missing-compiler-source.php'))
        ->toThrow(InvalidArgumentException::class);
});

it('compiles a file into bytecode without executing it', function (): void {
    $compiled = Compiler::compileFile(__DIR__ . '/../../StaticAnalysis/compile-only.php');

    expect($compiled->body->count())->toBeGreaterThan(0)
        ->and($compiled->declaredClasses)->toContain('compileonlytarget')
        ->and($compiled->class('CompileOnlyTarget')?->method('run')?->body->count())->toBeGreaterThan(0);
});

it('snapshots namespaced classes and functions before destroying compiler storage', function (): void {
    $compiled = Compiler::compileFile(__DIR__ . '/../../StaticAnalysis/compile-targets.php');

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
    Compiler::compileFile(__DIR__ . '/../../StaticAnalysis/compile-only.php');
    $compiled = Compiler::compileFile(__DIR__ . '/../../StaticAnalysis/compile-only.php');

    expect(class_exists('CompileOnlyTarget', false))->toBeFalse()
        ->and($compiled->class('CompileOnlyTarget'))->not->toBeNull();
});

it('restores compiler state after a failed compilation', function (): void {
    $filename = tempnam(sys_get_temp_dir(), 'communism-invalid-');
    if ($filename === false) {
        throw new RuntimeException('Could not create an invalid compiler fixture');
    }

    if (file_put_contents($filename, "<?php\nclass Broken {\n") === false) {
        unlink($filename);
        throw new RuntimeException('Could not write an invalid compiler fixture');
    }

    try {
        expect(fn(): mixed => Compiler::compileFile($filename))->toThrow(ParseError::class);

        $compiled = Compiler::compileFile(__DIR__ . '/../../StaticAnalysis/compile-only.php');

        expect(class_exists('Broken', false))->toBeFalse()
            ->and($compiled->class('CompileOnlyTarget'))->not->toBeNull();
    } finally {
        unlink($filename);
    }
});

it('releases compiler hash entries safely across repeated compilations', function (): void {
    $filename = __DIR__ . '/../../StaticAnalysis/compile-targets.php';

    for ($iteration = 0; $iteration < 100; $iteration++) {
        $compiled = Compiler::compileFile($filename);

        expect($compiled->class('CompileFixture\\SecondTarget'))->not->toBeNull()
            ->and($compiled->functions)->not->toBeEmpty();
    }
});

it('snapshots declaration pointers before decompiling a growing hash table', function (): void {
    $filename = tempnam(sys_get_temp_dir(), 'communism-compiler-');
    if ($filename === false) {
        throw new RuntimeException('Could not create a compiler stress fixture');
    }

    $source = "<?php\nnamespace CompilerStress;\n";
    for ($index = 0; $index < 128; $index++) {
        $source .= sprintf(
            "function function%d(): int { return %d; }\nfinal class Class%d { public function value(): int { return %d; } }\n",
            $index,
            $index,
            $index,
            $index,
        );
    }

    if (file_put_contents($filename, $source) === false) {
        unlink($filename);
        throw new RuntimeException('Could not write the compiler stress fixture');
    }

    try {
        for ($iteration = 0; $iteration < 10; $iteration++) {
            $compiled = Compiler::compileFile($filename);

            expect($compiled->declaredClasses)->toHaveCount(128)
                ->and($compiled->functions)->toHaveCount(128)
                ->and($compiled->class('CompilerStress\\Class127')?->method('value'))->not->toBeNull()
                ->and(class_exists('CompilerStress\\Class127', false))->toBeFalse()
                ->and(function_exists('CompilerStress\\function127'))->toBeFalse();
        }
    } finally {
        unlink($filename);
    }
});

it('reads a class from a source file whose class is already loaded', function (): void {
    $compiled = Compiler::compileFile(__DIR__ . '/../../../src/Communism/Internals/Needle/Compiler.php');

    expect($compiled->class(Compiler::class)?->method('compileFile'))->not->toBeNull();
});

it('compiles statically resolvable transitive include targets without executing them', function (): void {
    $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'communism-transitive-' . bin2hex(random_bytes(4));
    if (!mkdir($directory) && !is_dir($directory)) {
        throw new RuntimeException('Could not create a transitive compiler fixture directory');
    }
    $root = $directory . DIRECTORY_SEPARATOR . 'root.php';
    $child = $directory . DIRECTORY_SEPARATOR . 'child.php';

    try {
        file_put_contents($child, "<?php\nfinal class CompilerTransitiveChild {}\n");
        file_put_contents($root, "<?php\nrequire __DIR__ . '/child.php';\nfinal class CompilerTransitiveRoot {}\n");

        $compiled = Compiler::compileFile($root);

        expect($compiled->class('CompilerTransitiveRoot'))->not->toBeNull()
            ->and($compiled->class('CompilerTransitiveChild'))->not->toBeNull()
            ->and(class_exists('CompilerTransitiveRoot', false))->toBeFalse()
            ->and(class_exists('CompilerTransitiveChild', false))->toBeFalse();
    } finally {
        if (is_file($root)) {
            unlink($root);
        }
        if (is_file($child)) {
            unlink($child);
        }
        rmdir($directory);
    }
});

it('rejects a statically resolvable missing require target', function (): void {
    $filename = tempnam(sys_get_temp_dir(), 'communism-required-');
    if ($filename === false) {
        throw new RuntimeException('Could not create a required compiler fixture');
    }

    try {
        file_put_contents($filename, "<?php\nrequire __DIR__ . '/missing-required.php';\n");

        expect(static fn(): mixed => Compiler::compileFile($filename))
            ->toThrow(InvalidArgumentException::class, 'missing required include');
    } finally {
        unlink($filename);
    }
});
