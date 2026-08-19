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
 * File: NativeLinuxFfi.php                                                   *
 * Consumer: Internal                                                         *
 * Purpose: Native Linux dynamic-loader FFI implementation.                   *
 *============================================================================*/

declare(strict_types=1);

namespace Zendful\Internals;

class NativeLinuxFfi implements LinuxFfi
{
    private const int RTLD_LAZY = 1;
    private const int RTLD_NOLOAD = 4;

    private object $ffi;

    public function __construct()
    {
        $this->ffi = $this->cdef(<<<'CDEF'
typedef unsigned long uintptr_t;
typedef struct dl_phdr_info {
    uintptr_t dlpi_addr;
    const char *dlpi_name;
    void *dlpi_phdr;
    unsigned short dlpi_phnum;
} dl_phdr_info;
typedef int (*dl_iterate_callback)(dl_phdr_info *info, size_t size, void *data);
int dl_iterate_phdr(dl_iterate_callback callback, void *data);
CDEF);
    }

    public function phpLibraries(): array
    {
        $libraries = [];
        $callback = function (
            object $info,
            int $_size,
            ?object $_data,
        ) use (&$libraries): int {
            /** @var \Zendful_FFI\LinuxInfo $typedInfo */
            $typedInfo = $info;
            $namePointer = $typedInfo->dlpi_name;
            // Runtime safety
            // @phpstan-ignore function.alreadyNarrowedType, identical.alwaysFalse
            if ($namePointer === null || (is_object($namePointer) && $this->isNull($namePointer))) {
                return 0;
            }

            // Runtime safety
            // @phpstan-ignore function.alreadyNarrowedType, function.impossibleType
            $name = is_string($namePointer) ? $namePointer : $this->string($namePointer);
            if (1 !== preg_match('~(?:^|/)(?:lib)?php[^/]*\.so(?:\.[^/]*)?$~i', $name)) {
                return 0;
            }

            if (!in_array($name, $libraries, true)) {
                $libraries[] = $name;
            }

            return 0;
        };

        /** @var \Zendful_FFI\LinuxApi $ffi */
        $ffi = $this->ffi;
        $ffi->dl_iterate_phdr($callback, null);

        return $libraries;
    }

    public function isLoaded(string $library): bool
    {
        /** @var \Zendful_FFI\LinuxApi $ffi */
        $ffi = $this->cdef(<<<'CDEF'
void *dlopen(const char *filename, int flags);
int dlclose(void *handle);
CDEF);

        $handle = $ffi->dlopen($library, self::RTLD_LAZY | self::RTLD_NOLOAD);
        if ($handle === null || $this->isNull($handle)) {
            return false;
        }

        $ffi->dlclose($handle);

        return true;
    }
    protected function cdef(string $code, ?string $library = null): object
    {
        return \FFI::cdef($code, $library);
    }

    protected function isNull(object $value): bool
    {
        return \FFI::isNull($value);
    }

    protected function string(object $value): string
    {
        return \FFI::string($value);
    }
}
