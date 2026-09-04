<?php

declare(strict_types=1);

describe('Zendful', function (): void {
    covers([]);

    it('aliases every public handle to an available native implementation', function (): void {
        $probe = __DIR__ . DIRECTORY_SEPARATOR . 'native_alias_probe.php';
        $command = escapeshellarg(PHP_BINARY) . ' -n ' . escapeshellarg($probe);
        exec($command, $output, $exitCode);

        expect($exitCode)->toBe(0);
    });

    it('keeps fallback semantic mutations isolated from the test process', function (): void {
        $probe = __DIR__ . DIRECTORY_SEPARATOR . 'zendful_semantic_probe.php';
        $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($probe);
        exec($command, $output, $exitCode);

        expect($exitCode)->toBe(0);
    });
});
