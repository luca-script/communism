<?php

declare(strict_types=1);

namespace Zendful\Internals {
    function is_file(string $filename): bool
    {
        return \is_file($filename);
    }

    function is_file_false(string $filename): bool
    {
        return false;
    }

    function is_file_true(string $filename): bool
    {
        return true;
    }

    function is_file_dispatch(string $filename): bool
    {
        return match ($GLOBALS['findLoadedLibraryFilesystemMode'] ?? 'real') {
            'missing' => false,
            'maps', 'unreadable' => true,
            default => \is_file($filename),
        };
    }

    /**
     * @param 0|1|2|3|4|5|6|7|16|17|18|19|20|21|22|23 $flags
     * @return list<string>|false
     */
    function file(string $filename, int $flags = 0): array|false
    {
        return \file($filename, $flags);
    }

    function file_false(string $filename, int $flags = 0): false
    {
        return false;
    }

    /** @return list<string> */
    function file_with_php_maps(string $filename, int $flags = 0): array
    {
        return [
            '7f0000000000-7f0000100000 r-xp 00000000 08:01 123 /tmp/libphp8.5.so',
            '7f0000100000-7f0000200000 r-xp 00000000 08:01 124 /tmp/libphp8.5.so.1 (deleted)',
        ];
    }

    /**
     * @param 0|1|2|3|4|5|6|7|16|17|18|19|20|21|22|23 $flags
     * @return array<int, string>|false
     */
    function file_dispatch(string $filename, int $flags = 0): array|false
    {
        return match ($GLOBALS['findLoadedLibraryFilesystemMode'] ?? 'real') {
            'unreadable' => false,
            'maps' => file_with_php_maps($filename, $flags),
            default => \file($filename, $flags),
        };
    }
}

namespace {
    if (function_exists('opcache_reset')) {
        opcache_reset();
    }

    require_once __DIR__ . '/../vendor/autoload.php';
    require_once __DIR__ . '/../tools/crash-diagnostics/preload.php';
}
