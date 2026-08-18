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
 * :: Zendful :: "When PHP doesn't provide it, we do!" ::                     *
 *----------------------------------------------------------------------------*
 * File: CompiledFileHandle.php                                               *
 * Consumer: Internal                                                         *
 * Purpose: Safe handle for a detached compiled PHP file.                     *
 *============================================================================*/

declare(strict_types=1);

namespace Zendful;

use InvalidArgumentException;

// Native alias is exercised by the separate native-backend probe.
// @codeCoverageIgnoreStart
if (class_exists('Zendful\\Native\\CompiledFileHandle', false)) {
    class_alias('Zendful\\Native\\CompiledFileHandle', CompiledFileHandle::class);
    // @codeCoverageIgnoreEnd
} else {
    final readonly class CompiledFileHandle
    {
        /**
         * @param list<string> $declaredClasses
         * @param list<CompiledClassHandle> $classes
         * @param list<CompiledMethodHandle> $functions
         */
        public function __construct(
            public CompiledOpArrayHandle $opArray,
            public array $declaredClasses,
            public array $classes = [],
            public array $functions = [],
        ) {
            self::validateSnapshots($declaredClasses, $classes, $functions);
        }

        /**
         * @param array<int|string, mixed> $declaredClasses
         * @param array<int|string, mixed> $classes
         * @param array<int|string, mixed> $functions
         */
        private static function validateSnapshots(array $declaredClasses, array $classes, array $functions): void
        {
            if (!array_is_list($declaredClasses)
                || !array_is_list($classes)
                || !array_is_list($functions)) {
                throw new InvalidArgumentException('Compiled file snapshots must use lists.');
            }

            foreach ($declaredClasses as $class) {
                if (!is_string($class) || $class === '' || str_contains($class, "\0")) {
                    throw new InvalidArgumentException('Compiled class names must not be empty or contain NUL bytes.');
                }
            }
            foreach ($classes as $class) {
                if (!$class instanceof CompiledClassHandle) {
                    throw new InvalidArgumentException('Compiled files must contain compiled class handles.');
                }
            }
            foreach ($functions as $function) {
                if (!$function instanceof CompiledMethodHandle) {
                    throw new InvalidArgumentException('Compiled files must contain compiled method handles.');
                }
            }
        }
    }
}
