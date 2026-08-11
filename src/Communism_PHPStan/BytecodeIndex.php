<?php

/*============================================================================*
 * SPDX-License-Identifier: 0BSD                                              *
 * SPDX-FileCopyrightText: 2026 Luca Mollema                                  *
 * Copyright (C) 2026 Luca Mollema                                            *
 *                                                                            *
 * Permission to use, copy, modify, and/or distribute this software for any   *
 * purpose with or without fee is hereby granted.                             *
 *                                                                            *
 * THE SOFTWARE IS PROVIDED “AS IS” AND THE AUTHOR DISCLAIMS ALL WARRANTIES   *
 * WITH REGARD TO THIS SOFTWARE INCLUDING ALL IMPLIED WARRANTIES OF           *
 * MERCHANTABILITY AND FITNESS. IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR    *
 * ANY SPECIAL, DIRECT, INDIRECT, OR CONSEQUENTIAL DAMAGES OR ANY DAMAGES     *
 * WHATSOEVER RESULTING FROM LOSS OF USE, DATA OR PROFITS, WHETHER IN AN      *
 * ACTION OF CONTRACT, NEGLIGENCE OR OTHER TORTIOUS ACTION, ARISING OUT OF OR *
 * IN CONNECTION WITH THE USE OR PERFORMANCE OF THIS SOFTWARE.                *
 *============================================================================*
 * :: Communism :: "In comrade PHP, all are public" ::                        *
 *----------------------------------------------------------------------------*
 * File: BytecodeIndex.php                                                    *
 * Purpose: Source file for BytecodeIndex.php.                                *
 *============================================================================*/

declare(strict_types=1);

namespace Communism_PHPStan;

use Communism\Internals\Needle\CompiledClass;
use Communism\Internals\Needle\CompiledFile;
use Communism\Internals\Needle\CompiledMethod;
use Communism\Internals\Needle\Compiler;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Analyser\Scope;

use function is_string;
use function strtolower;

/**
 * Caches detached Zend compiler snapshots for PHPStan target classes.
 *
 * The source file is obtained from PHPStan's static reflection. Compiler
 * snapshots are never loaded as PHP code and are discarded after indexing.
 */
final class BytecodeIndex
{
    /** @var array<string, CompiledFile> */
    private array $files = [];

    /** @var array<string, CompiledClass|null> */
    private array $classes = [];

    public function class(ClassReflection $reflection): ?CompiledClass
    {
        $name = strtolower($reflection->getName());
        if (array_key_exists($name, $this->classes)) {
            return $this->classes[$name];
        }

        $filename = $reflection->getFileName();
        if (!is_string($filename)) {
            return $this->classes[$name] = null;
        }

        $file = $this->files[$filename] ??= Compiler::compileFile($filename);

        return $this->classes[$name] = $file->class($reflection->getName());
    }

    public function method(ClassReflection $reflection, string $name, Scope $scope): ?CompiledMethod
    {
        $declaringClass = $reflection->getMethod($name, $scope)->getDeclaringClass();

        return $this->class($declaringClass)?->method($name);
    }
}
