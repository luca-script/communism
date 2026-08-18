<?php

declare(strict_types=1);

use Communism\Internals\Needle\CompiledClass;
use Communism\Internals\Needle\CompiledFile;
use Communism\Internals\Needle\CompiledMethod;
use Communism\Internals\Needle\Instruction;
use Communism\Internals\Needle\MethodBody;
use Communism\Internals\Needle\Operand;

it('finds compiled classes case-insensitively and returns null for absent classes', function (): void {
    $body = new MethodBody('run', null, 0, 0, [
        new Instruction(0, 'NOP', Operand::unused(), Operand::unused(), Operand::unused()),
    ]);
    $method = new CompiledMethod('run', $body);
    $class = new CompiledClass('Example', ['run' => $method]);
    $file = new CompiledFile($body, ['Example'], [$class]);

    expect($file->class('example'))->toBe($class)
        ->and($file->class('Missing'))->toBeNull()
        ->and($class->method('RUN'))->toBe($method)
        ->and($class->method('missing'))->toBeNull();
});
