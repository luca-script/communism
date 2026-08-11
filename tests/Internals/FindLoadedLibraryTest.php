<?php

declare(strict_types=1);

use Communism\Internals\FindLoadedLibrary;

it('finds loaded PHP libraries through the operating system', function (): void {
    $libraries = FindLoadedLibrary::php();

    expect($libraries)->toBeArray();

    foreach ($libraries as $library) {
        expect($library)->toBeString();
        expect(strtolower($library))->toContain('php');
    }
});
