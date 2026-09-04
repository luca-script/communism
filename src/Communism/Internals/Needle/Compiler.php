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

use function array_unique;
use function dirname;
use function file_get_contents;
use function in_array;
use function is_array;
use function is_file;
use function preg_match;
use function realpath;
use function str_ends_with;
use function str_replace;
use function strtolower;
use function substr;
use function token_get_all;

/** Internal compiler facade; unsafe compiler ownership lives in Zendful. */
final class Compiler
{
    public static function compileFile(string $filename): CompiledFile
    {
        $visited = [];

        return self::compileTransitive($filename, $visited);
    }

    /** @param array<string, bool> $visited */
    private static function compileTransitive(string $filename, array &$visited): CompiledFile
    {
        $canonical = realpath($filename);
        if ($canonical === false) {
            throw new \InvalidArgumentException(sprintf('Cannot compile missing file %s', $filename));
        }
        if (isset($visited[$canonical])) {
            return new CompiledFile(new MethodBody($canonical, $canonical, 0, 0, []), []);
        }
        $visited[$canonical] = true;

        $compiled = self::compileDirectFile($canonical);
        $classes = $compiled->classes;
        $functions = $compiled->functions;
        $declaredClasses = $compiled->declaredClasses;
        foreach (self::staticIncludes($canonical) as [$kind, $included]) {
            if (!is_file($included)) {
                if (str_ends_with($kind, 'require')) {
                    throw new \InvalidArgumentException(sprintf('Cannot compile missing required include %s from %s', $included, $canonical));
                }
                continue;
            }
            $child = self::compileTransitive($included, $visited);
            $declaredClasses = [...$declaredClasses, ...$child->declaredClasses];
            $classes = [...$classes, ...$child->classes];
            $functions = [...$functions, ...$child->functions];
        }

        return new CompiledFile(
            $compiled->body,
            array_values(array_unique($declaredClasses)),
            self::uniqueCompiledClasses($classes),
            self::uniqueCompiledMethods($functions),
        );
    }

    private static function compileDirectFile(string $filename): CompiledFile
    {
        $compiled = Zendful::compileFile($filename);
        /** @var list<CompiledClass> $classes */
        $classes = array_map(self::classSnapshot(...), $compiled->classes);
        /** @var list<CompiledMethod> $functions */
        $functions = array_map(self::methodSnapshot(...), $compiled->functions);

        return new CompiledFile(
            Decompiler::decompileCompiledOpArray($compiled->opArray, $filename),
            $compiled->declaredClasses,
            $classes,
            $functions,
        );
    }

    /** @return list<array{0: string, 1: string}> */
    private static function staticIncludes(string $filename): array
    {
        $source = file_get_contents($filename);
        if ($source === false) {
            return [];
        }

        $tokens = token_get_all($source);
        $includes = [];
        foreach ($tokens as $index => $token) {
            if (!is_array($token) || !in_array($token[0], [T_INCLUDE, T_INCLUDE_ONCE, T_REQUIRE, T_REQUIRE_ONCE], true)) {
                continue;
            }
            $expression = '';
            $depth = 0;
            for ($next = $index + 1; isset($tokens[$next]); $next++) {
                $part = $tokens[$next];
                $text = is_array($part) ? $part[1] : $part;
                if ($text === '(') {
                    $depth++;
                } elseif ($text === ')') {
                    if ($depth === 0) {
                        break;
                    }
                    $depth--;
                } elseif ($text === ';' && $depth === 0) {
                    break;
                }
                $expression .= $text;
            }
            $path = self::staticIncludePath($expression, dirname($filename));
            if ($path !== null) {
                $includes[] = [strtolower($token[1]), $path];
            }
        }

        return $includes;
    }

    private static function staticIncludePath(string $expression, string $directory): ?string
    {
        $path = '';
        $tokens = token_get_all('<?php ' . $expression);
        foreach (array_slice($tokens, 1) as $token) {
            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT], true)) {
                continue;
            }
            if ($token === '.' || $token === '(' || $token === ')') {
                continue;
            }
            if (is_array($token) && $token[0] === T_DIR) {
                $path .= $directory;
                continue;
            }
            if (!is_array($token) || $token[0] !== T_CONSTANT_ENCAPSED_STRING) {
                return null;
            }
            $literal = substr($token[1], 1, -1);
            $path .= $token[1][0] === "'"
                ? str_replace(["\\'", '\\\\'], ["'", '\\'], $literal)
                : str_replace(['\\"', '\\\\'], ['"', '\\'], $literal);
        }

        if ($path === '') {
            return null;
        }

        return preg_match('/^(?:[A-Za-z]:[\\\\\/]|[\\\\\/])/', $path) === 1
            ? $path
            : $directory . DIRECTORY_SEPARATOR . $path;
    }

    /**
     * @param list<CompiledClass> $classes
     * @return list<CompiledClass>
     */
    private static function uniqueCompiledClasses(array $classes): array
    {
        $unique = [];
        foreach ($classes as $class) {
            $unique[strtolower($class->name)] = $class;
        }

        return array_values($unique);
    }

    /**
     * @param list<CompiledMethod> $functions
     * @return list<CompiledMethod>
     */
    private static function uniqueCompiledMethods(array $functions): array
    {
        $unique = [];
        foreach ($functions as $function) {
            $unique[strtolower($function->name)] = $function;
        }

        return array_values($unique);
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
