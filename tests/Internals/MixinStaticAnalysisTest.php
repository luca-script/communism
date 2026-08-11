<?php

declare(strict_types=1);

/**
 * @return array{code: int, output: string}
 */
function runMixinStaticAnalysis(string $file): array
{
    $output = [];
    $code = 0;
    $phpstan = dirname(__DIR__, 2) . '/vendor/bin/phpstan';
    $config = __DIR__ . '/../StaticAnalysis/phpstan.neon';

    exec(sprintf(
        '%s %s analyse -c %s %s --no-progress 2>&1',
        escapeshellarg(PHP_BINARY),
        escapeshellarg($phpstan),
        escapeshellarg($config),
        escapeshellarg(__DIR__ . '/../StaticAnalysis/' . $file),
    ), $output, $code);

    return ['code' => $code, 'output' => implode(PHP_EOL, $output)];
}

it('checks mixin targets through PHPStan without executing target files', function (): void {
    $result = runMixinStaticAnalysis('valid.php');

    expect($result['code'])->toBe(0)
        ->and($result['output'])->not->toContain('Static-analysis fixture was executed');
});

it('reports missing mixin targets and members statically', function (): void {
    $result = runMixinStaticAnalysis('invalid.php');

    expect($result['code'])->not->toBe(0)
        ->and($result['output'])->toContain('Mixin injection targets missing method')
        ->and($result['output'])->toContain('Mixin shadows missing property')
        ->and($result['output'])->toContain('Mixin accessor targets missing property')
        ->and($result['output'])->toContain('Mixin invoker targets missing method')
        ->and($result['output'])->toContain('Bytecode injection point INVOKE target strtolower was not found')
        ->and($result['output'])->not->toContain('Static-analysis fixture was executed');
});
