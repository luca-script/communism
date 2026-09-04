<?php

declare(strict_types=1);

namespace {
    /** @var list<class-string> Classes intentionally attributed by mixin tests. */
    const COMMUNISM_INJECTOR_COVERAGE_CLASSES = [
        \Communism\Internals\Zend::class,
        \Communism\Internals\Needle\Injector::class,
    ];

    if (function_exists('opcache_reset')) {
        opcache_reset();
    }

    require_once __DIR__ . '/../vendor/autoload.php';
    require_once __DIR__ . '/../tools/crash-diagnostics/preload.php';

    class_exists(\Communism\Internals\Zend::class);
    \Zendful\Zendful::class(\Communism\Internals\Zend::class)->setFinal(false);
}
