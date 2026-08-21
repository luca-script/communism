<?php

declare(strict_types=1);

$scope = $argv[1] ?? 'zendful';
if (!in_array($scope, ['zendful', 'communism'], true)) {
    fwrite(STDERR, "Coverage scope must be 'zendful' or 'communism'.\n");
    exit(1);
}

$scopeDirectories = [
    'zendful' => 'Zendful',
    'communism' => 'Communism',
];
$coverageDirectory = realpath(
    __DIR__ . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . $scopeDirectories[$scope],
);
if ($coverageDirectory === false) {
    fwrite(STDERR, sprintf("Coverage directory for %s could not be resolved.\n", $scope));
    exit(1);
}

$configuredExtensionDirectory = (string) ini_get('extension_dir');
$extensionDirectory = realpath($configuredExtensionDirectory);
if (!preg_match('/^(?:[A-Za-z]:[\\\\\/]|[\\\\\/])/', $configuredExtensionDirectory)) {
    $extensionDirectory = realpath(dirname(PHP_BINARY) . DIRECTORY_SEPARATOR . $configuredExtensionDirectory);
}

if ($extensionDirectory === false) {
    fwrite(STDERR, "PHP's extension directory could not be resolved.\n");
    exit(1);
}

$extensionNames = PHP_OS_FAMILY === 'Windows'
    ? ['ffi' => ['php_ffi.dll', 'ffi.dll'], 'mbstring' => ['php_mbstring.dll', 'mbstring.dll'], 'opcache' => ['php_opcache.dll', 'opcache.dll'], 'pcov' => ['php_pcov.dll', 'pcov.dll']]
    : ['ffi' => ['ffi.so'], 'mbstring' => ['mbstring.so'], 'opcache' => ['opcache.so'], 'pcov' => ['pcov.so']];

$extensions = [];
$zendExtensions = [];
$extensionDirectories = array_values(array_unique([$extensionDirectory, dirname(PHP_BINARY)]));
foreach ($extensionNames as $name => $filenames) {
    foreach ($extensionDirectories as $directory) {
        foreach ($filenames as $filename) {
            $path = realpath($directory . DIRECTORY_SEPARATOR . $filename);
            if ($path !== false) {
                if ($name === 'opcache') {
                    $zendExtensions[$name] = $path;
                } else {
                    $extensions[$name] = $path;
                }
                break 2;
            }
        }
    }

    // Linux distributions may compile OPcache into the CLI binary instead
    // of shipping an opcache.so file. It is optional for PCOV coverage.
    if ($name === 'opcache' && !isset($zendExtensions[$name])) {
        continue;
    }

    if (!isset($extensions[$name]) && !isset($zendExtensions[$name])) {
        fwrite(STDERR, sprintf("The %s PHP extension was not found in %s.\n", $name, $extensionDirectory));
        exit(1);
    }
}

$command = [
    PHP_BINARY,
    '-n',
    '-d',
    'extension_dir=' . $extensionDirectory,
];

foreach ($extensions as $path) {
    $command[] = '-d';
    $command[] = 'extension=' . $path;
}

foreach ($zendExtensions as $path) {
    $command[] = '-d';
    $command[] = 'zend_extension=' . $path;
}

$command = [
    ...$command,
    '-d',
    'ffi.enable=true',
    '-d',
    'pcov.enabled=1',
    '-d',
    'pcov.directory=' . $coverageDirectory,
    '-d',
    // PCOV and the CLI executor are incompatible with OPcache's CLI hooks.
    // The Zend extension remains loaded, so opcache_jit_blacklist() exists.
    'opcache.enable_cli=0',
    __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'pest',
    '--configuration',
    __DIR__ . DIRECTORY_SEPARATOR . 'phpunit-' . $scope . '.xml',
    '--coverage',
    '--coverage-filter=' . $coverageDirectory,
];

$process = proc_open($command, [STDIN, STDOUT, STDERR], $pipes, __DIR__);
if (!is_resource($process)) {
    fwrite(STDERR, "Could not start Pest with coverage enabled.\n");
    exit(1);
}

exit(proc_close($process));
