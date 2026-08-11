<?php

declare(strict_types=1);

use Communism\Internals\FindLoadedLibrary;

it('finds loaded PHP libraries through the operating system', function (): void {
    $libraries = FindLoadedLibrary::php();

    expect($libraries)->toBeArray();

    // A statically linked Linux CLI has no libphp*.so mapping. In that case
    // Zend falls through to RTLD_DEFAULT, which is covered by the bytecode
    // tests. Windows PHP always exposes php*.dll through ToolHelp.
    if (PHP_OS_FAMILY === 'Windows') {
        expect(count($libraries))->toBeGreaterThan(0);
    }

    foreach ($libraries as $library) {
        expect($library)->toBeString();
        expect(strtolower($library))->toContain('php');
    }
});

it('parses normal and deleted Linux library mappings', function (): void {
    $method = new ReflectionMethod(FindLoadedLibrary::class, 'phpFromLinuxMaps');

    /** @var list<string> $libraries */
    $libraries = $method->invoke(null, [
        '7f0000000000-7f0000100000 r-xp 00000000 08:01 123 /usr/lib/libc.so.6',
        '7f0000100000-7f0000200000 r-xp 00000000 08:01 124 /tmp/libphp8.5.so',
        '7f0000200000-7f0000300000 r-xp 00000000 08:01 125 /tmp/libphp8.5.so.1 (deleted)',
        '7f0000300000-7f0000400000 r-xp 00000000 08:01 126 /tmp/libother.so (deleted)',
    ]);

    expect($libraries)->toBe([
        '/tmp/libphp8.5.so',
        '/tmp/libphp8.5.so.1',
    ]);
});
