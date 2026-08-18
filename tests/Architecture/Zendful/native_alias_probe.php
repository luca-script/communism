<?php

declare(strict_types=1);

namespace Zendful\Native {
    final class AssemblyPlanHandle {}
    final class ClassHandle {}
    final class CompiledClassHandle {}
    final class CompiledFileHandle {}
    final class CompiledMethodHandle {}
    final class CompiledOpArrayHandle {}
    final class CompilerHandle {}
    final class FunctionHandle {}
    final class MethodHandle {}
    final class OpArrayHandle {}
    final class OpcodeHandle {}
    final class OperandHandle {}
    final class PropertyHandle {}
}

namespace {
    require dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

    /** @var list<string> $types */
    $types = [
        'AssemblyPlanHandle',
        'ClassHandle',
        'CompiledClassHandle',
        'CompiledFileHandle',
        'CompiledMethodHandle',
        'CompiledOpArrayHandle',
        'CompilerHandle',
        'FunctionHandle',
        'MethodHandle',
        'OpArrayHandle',
        'OpcodeHandle',
        'OperandHandle',
        'PropertyHandle',
    ];

    foreach ($types as $type) {
        $alias = sprintf('Zendful\\%s', $type);
        $native = sprintf('Zendful\\Native\\%s', $type);
        if (!class_exists($alias) || (new \ReflectionClass($alias))->getName() !== $native) {
            exit(1);
        }
    }

    foreach (['Executor', 'Compiler', 'Natives', 'FindLoadedLibrary'] as $internal) {
        if (class_exists('Zendful\\Internals\\' . $internal, false)) {
            exit(2);
        }
    }
}
