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
 * File: Compiler.php                                                         *
 * Consumer: Internal                                                         *
 * Purpose: Map detached Zendful compiler handles to Communism snapshots.     *
 *============================================================================*/

declare(strict_types=1);

namespace Communism\Internals\Needle;

use Zendful\CompiledClassHandle;
use Zendful\CompiledFileHandle;
use Zendful\CompiledMethodHandle;
use Zendful\Zendful;

/** Internal compiler facade; unsafe compiler ownership lives in Zendful. */
final class Compiler
{
    public static function compileFile(string $filename): CompiledFile
    {
        $compiled = Zendful::compileFile($filename);
        $classes = array_map(self::classSnapshot(...), $compiled->classes);
        $functions = array_map(self::methodSnapshot(...), $compiled->functions);

        return new CompiledFile(
            Decompiler::decompileCompiledOpArray($compiled->opArray, $filename),
            $compiled->declaredClasses,
            $classes,
            $functions,
        );
    }

    private static function classSnapshot(CompiledClassHandle $class): CompiledClass
    {
        $methods = [];
        foreach ($class->methods() as $method) {
            $methods[strtolower($method->name)] = self::methodSnapshot($method, $class->name);
        }

        return new CompiledClass($class->name, $methods);
    }

    private static function methodSnapshot(CompiledMethodHandle $method, ?string $className = null): CompiledMethod
    {
        $name = $className === null ? $method->name : $className . '::' . $method->name;

        return new CompiledMethod(
            $method->name,
            Decompiler::decompileCompiledOpArray($method->opArray, $name),
        );
    }
}
