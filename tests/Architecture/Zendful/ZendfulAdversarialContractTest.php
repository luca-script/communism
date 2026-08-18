<?php

declare(strict_types=1);

it('rejects malformed assembly plans before entering the runtime boundary', function (): void {
    $probe = __DIR__ . DIRECTORY_SEPARATOR . 'zendful_assembly_probe.php';
    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($probe);
    exec($command, $output, $exitCode);

    expect($exitCode)->toBe(0);

    $jitCommand = escapeshellarg(PHP_BINARY)
        . ' -d opcache.enable_cli=1 -d opcache.jit_buffer_size=64M -d opcache.jit=1255 '
        . escapeshellarg($probe);
    exec($jitCommand, $jitOutput, $jitExitCode);

    expect($jitExitCode)->toBe(0);
});

it('rejects forged detached compiler snapshots', function (): void {
    $probe = __DIR__ . DIRECTORY_SEPARATOR . 'zendful_snapshot_probe.php';
    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($probe);
    exec($command, $output, $exitCode);

    expect($exitCode)->toBe(0);
});

it('fails explicitly when the fallback backend has no FFI extension', function (): void {
    $probe = __DIR__ . DIRECTORY_SEPARATOR . 'zendful_no_ffi_probe.php';
    $command = escapeshellarg(PHP_BINARY) . ' -n ' . escapeshellarg($probe);
    exec($command, $output, $exitCode);

    expect($exitCode)->toBe(0);
});

it('restores temporary runtime mutations across exceptions and re-entry', function (): void {
    $probe = __DIR__ . DIRECTORY_SEPARATOR . 'zendful_mutation_probe.php';
    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($probe);
    exec($command, $output, $exitCode);

    expect($exitCode)->toBe(0);
});
