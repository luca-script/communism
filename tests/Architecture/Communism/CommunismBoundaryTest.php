<?php

declare(strict_types=1);

describe('Communism', function (): void {
    covers([]);

    it('keeps FFI and Zendful implementation details out of Communism', function (): void {
        $sourceRoot = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'src';
        $roots = [
            $sourceRoot . DIRECTORY_SEPARATOR . 'Communism',
            $sourceRoot . DIRECTORY_SEPARATOR . 'Communism_PHPStan',
        ];
        $forbidden = [
            'ffi',
            'cdata',
            'zendful_ffi',
            'communism_ffi',
            'zendful\\internals',
            'findloadedlibrary',
        ];

        foreach ($roots as $root) {
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $file) {
                /** @var SplFileInfo $file */
                if (!$file->isFile() || $file->getExtension() !== 'php') {
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
        }
    });
});
