<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

if ($argc !== 4) {
    fwrite(STDERR, "Usage: php decompile.php <file> <class> <method>\n");
    exit(1);
}

[, $file, $class, $method] = $argv;
$file = realpath($file);

if ($file === false || !is_file($file)) {
    fwrite(STDERR, "File not found: {$argv[1]}\n");
    exit(1);
}

require_once $file;

if (!class_exists($class)) {
    fwrite(STDERR, "Class not found: {$class}\n");
    exit(1);
}

if (!method_exists($class, $method)) {
    fwrite(STDERR, "Method not found: {$class}::{$method}\n");
    exit(1);
}

echo Communism\Bytecode\Bytecode::disassemble($class . '::' . $method);
