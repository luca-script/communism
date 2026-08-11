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
 * File: FindLoadedLibrary.php                                                *
 * Consumer: Internal                                                         *
 * Purpose: Source file for FindLoadedLibrary.php.                            *
 *============================================================================*/

declare(strict_types=1);

namespace Communism\Internals;

use FFI;

/**
 * Finds shared libraries already loaded into the current PHP process.
 *
 * @internal This class is an unsupported implementation detail.
 */
final class FindLoadedLibrary
{
    /** @return list<string> */
    public static function php(): array
    {
        if (PHP_OS_FAMILY === 'Linux') {
            return self::phpOnLinux();
        }

        if (PHP_OS_FAMILY === 'Windows') {
            return self::phpOnWindows();
        }

        return [];
    }

    /** @return list<string> */
    private static function phpOnLinux(): array
    {
        $libraries = self::phpFromDlIteratePhdr();
        $maps = @file('/proc/self/maps', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (false === $maps) {
            return $libraries;
        }

        return array_values(array_unique([
            ...$libraries,
            ...self::phpFromLinuxMaps($maps),
        ]));
    }

    /** @return list<string> */
    private static function phpFromDlIteratePhdr(): array
    {
        try {
            $ffi = FFI::cdef(<<<'CDEF'
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

            $libraries = [];
            $callback = static function (
                \FFI\CData $info,
                int $_size,
                ?\FFI\CData $_data,
            ) use (&$libraries): int {
                if (FFI::isNull($info->dlpi_name)) {
                    return 0;
                }

                $name = FFI::string($info->dlpi_name);
                if (1 !== preg_match('~(?:^|/)(?:lib)?php[^/]*\.so(?:\.[^/]*)?$~i', $name)) {
                    return 0;
                }

                if (!in_array($name, $libraries, true)) {
                    $libraries[] = $name;
                }

                return 0;
            };

            $ffi->dl_iterate_phdr($callback, null);

            return $libraries;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param list<string> $maps
     *
     * @return list<string>
     */
    private static function phpFromLinuxMaps(array $maps): array
    {
        $libraries = [];
        foreach ($maps as $line) {
            $match = [];
            // A mapped library can have a " (deleted)" marker when the file
            // was replaced after PHP loaded it. The mapped path is still the
            // handle that FFI can use, so remove the marker after matching it.
            if (1 !== preg_match('~\s(?<path>/.*?\.so(?:\.[^\s]*)?)(?:\s+\(deleted\))?$~', $line, $match)) {
                continue;
            }

            $path = $match['path'];
            if (1 !== preg_match('~(?:^|/)(?:lib)?php[^/]*\.so(?:\.[^/]*)?$~i', $path)) {
                continue;
            }

            if (!in_array($path, $libraries, true)) {
                $libraries[] = $path;
            }
        }

        return $libraries;
    }

    /** @return list<string> */
    private static function phpOnWindows(): array
    {
        try {
            $ffi = FFI::cdef(<<<'CDEF'
typedef unsigned long DWORD;
typedef int BOOL;
typedef void *HANDLE;
typedef struct {
    DWORD dwSize;
    DWORD th32ModuleID;
    DWORD th32ProcessID;
    DWORD GlblcntUsage;
    DWORD ProccntUsage;
    void *modBaseAddr;
    DWORD modBaseSize;
    void *hModule;
    char szModule[256];
    char szExePath[260];
} MODULEENTRY32A;
HANDLE CreateToolhelp32Snapshot(DWORD dwFlags, DWORD th32ProcessID);
BOOL Module32First(HANDLE hSnapshot, MODULEENTRY32A *lpme);
BOOL Module32Next(HANDLE hSnapshot, MODULEENTRY32A *lpme);
BOOL CloseHandle(HANDLE hObject);
CDEF, 'kernel32.dll');

            $processId = getmypid();
            if (false === $processId) {
                return [];
            }

            $snapshot = $ffi->CreateToolhelp32Snapshot(0x00000008, $processId);
            if (FFI::isNull($snapshot)) {
                return [];
            }

            try {
                $module = $ffi->new('MODULEENTRY32A');
                $module->dwSize = FFI::sizeof($module);
                $libraries = [];
                $hasModule = $ffi->Module32First($snapshot, FFI::addr($module));

                while ($hasModule) {
                    $name = FFI::string($module->szModule);
                    if (1 === preg_match('/^php.*\.dll$/i', $name)) {
                        $path = FFI::string($module->szExePath);
                        $libraries[] = '' === $path ? $name : $path;
                    }

                    $hasModule = $ffi->Module32Next($snapshot, FFI::addr($module));
                }

                return array_values(array_unique($libraries));
            } finally {
                $ffi->CloseHandle($snapshot);
            }
        } catch (\Throwable) {
            return [];
        }
    }
}
