<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

try {
    Zendful\Internals\Natives::ffi();
    exit(1);
} catch (RuntimeException $exception) {
    if (!str_contains($exception->getMessage(), 'FFI extension')) {
        exit(2);
    }
}
