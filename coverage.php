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

$runPhp = static function (array $arguments): array {
    $process = proc_open(
        [PHP_BINARY, ...$arguments],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
    );
    if (!is_resource($process)) {
        return ['', 1];
    }
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return [$stdout === false ? '' : $stdout, proc_close($process)];
};

$moduleOutput = $runPhp(['-n', '-d', 'extension_dir=' . $extensionDirectory, '-m']);
$modules = array_filter(array_map('trim', preg_split('/\R/', $moduleOutput[0]) ?: []));
$hasModule = static fn(string $name): bool => in_array($name, $modules, true);

$smokeExtension = static function (string $name, string $directive, string $directory) use ($runPhp): bool {
    [, $status] = $runPhp([
        '-n',
        '-d',
        'extension_dir=' . $directory,
        '-d',
        $directive . '=' . $name,
        '-r',
        'exit(0);',
    ]);

    return $status === 0;
};

$extensions = [];
foreach (['ffi', 'xml', 'dom', 'mbstring', 'tokenizer', 'pcov'] as $name) {
    if ($hasModule($name)) {
        continue;
    }
    if ($smokeExtension($name, 'extension', $extensionDirectory)) {
        $extensions[] = $name;
        continue;
    }
    fwrite(STDERR, sprintf("The %s PHP extension is unavailable.\n", $name));
    exit(1);
}

$command = [
    PHP_BINARY,
    '-n',
    '-d',
    'extension_dir=' . $extensionDirectory,
];

foreach ($extensions as $name) {
    $command[] = '-d';
    $command[] = 'extension=' . $name;
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
