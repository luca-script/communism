<?php

declare(strict_types=1);

$readyFile = getenv('COMMUNISM_PEST_READY_FILE');
if (is_string($readyFile) && $readyFile !== '') {
    if (!touch($readyFile)) {
        throw new RuntimeException('Could not signal that the Pest runner is ready.');
    }

    while (true) {
        clearstatcache(true, $readyFile);
        if (!is_file($readyFile)) {
            break;
        }

        usleep(10_000);
    }
}

$argv = array_merge([__DIR__ . '/../vendor/bin/pest'], array_slice($argv, 1));
$_SERVER['argv'] = $argv;

require $argv[0];
