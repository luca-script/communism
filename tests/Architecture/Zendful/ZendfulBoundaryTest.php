<?php

declare(strict_types=1);

it('keeps FFI implementation details inside Zendful Internals', function (): void {
    $root = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Zendful';
    $forbidden = ['ffi', 'cdata', 'zendful_ffi', 'communism_ffi'];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $file) {
        /** @var SplFileInfo $file */
        if (!$file->isFile() || $file->getExtension() !== 'php' || str_contains($file->getPath(), 'Internals')) {
            continue;
        }

        $contents = file_get_contents($file->getPathname());
        if ($contents === false) {
            throw new RuntimeException(sprintf('Unable to read %s', $file->getPathname()));
        }

        $contents = strtolower($contents);
        foreach ($forbidden as $term) {
            expect($contents)->not->toContain($term, $file->getPathname());
        }
    }
});
