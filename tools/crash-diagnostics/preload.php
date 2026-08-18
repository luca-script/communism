<?php

declare(strict_types=1);

$library = getenv('ZENDFUL_CRASH_DIAGNOSTICS_LIBRARY');
if ($library === false) {
    return;
}

if (!class_exists(FFI::class)) {
    throw new RuntimeException('Crash diagnostics require the FFI extension.');
}

$ffi = FFI::cdef(
    <<<'C'
        int zendful_crash_install(void);
        void zendful_crash_now(int kind);
    C,
    $library,
);

$GLOBALS['zendfulCrashDiagnosticsFfi'] = $ffi;

if ($ffi->zendful_crash_install() !== 0) {
    throw new RuntimeException('Unable to install crash diagnostics.');
}
