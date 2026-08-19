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
 * File: FindLoadedLibrary.php                                                *
 * Consumer: Internal                                                         *
 * Purpose: Source file for FindLoadedLibrary.php.                            *
 *============================================================================*/

declare(strict_types=1);

namespace Zendful\Internals;

/**
 * Finds shared libraries already loaded into the current PHP process.
 *
 * @internal This class is an unsupported implementation detail.
 */
final class FindLoadedLibrary
{
    private const int TH32CS_SNAPMODULE = 8;

    public static function isLoaded(string $library): bool
    {
        return self::isLoadedForPlatform(PHP_OS_FAMILY, $library);
    }

    /** @return list<string> */
    public static function php(): array
    {
        return self::phpForPlatform(PHP_OS_FAMILY);
    }

    private static function isLoadedForPlatform(
        string $osFamily,
        string $library,
        ?LinuxFfi $linuxFfi = null,
        ?WindowsFfi $windowsFfi = null,
    ): bool {
        if ('Linux' === $osFamily) {
            return self::isLoadedOnLinux($library, $linuxFfi);
        }

        if ('Windows' === $osFamily) {
            return in_array($library, self::phpOnWindows($windowsFfi), true);
        }

        return false;
    }

    /** @return list<string> */
    private static function phpForPlatform(
        string $osFamily,
        ?LinuxFfi $linuxFfi = null,
        ?WindowsFfi $windowsFfi = null,
    ): array {
        if ('Linux' === $osFamily) {
            return self::phpOnLinux($linuxFfi);
        }

        if ('Windows' === $osFamily) {
            return self::phpOnWindows($windowsFfi);
        }

        return [];
    }

    /** @return list<string> */
    private static function phpOnLinux(?LinuxFfi $ffi = null): array
    {
        $libraries = self::phpFromDlIteratePhdr($ffi);
        if (!self::filesystemIsFile('/proc/self/maps')) {
            return $libraries;
        }

        $maps = self::filesystemFile('/proc/self/maps', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (false === $maps) {
            return $libraries;
        }

        return array_values(array_unique([
            ...$libraries,
            ...self::phpFromLinuxMaps($maps),
        ]));
    }

    private static function filesystemIsFile(string $filename): bool
    {
        $function = __NAMESPACE__ . '\\is_file';
        if (function_exists($function)) {
            return $function($filename);
        }

        return is_file($filename);
    }

    /**
     * @param 0|1|2|3|4|5|6|7|16|17|18|19|20|21|22|23 $flags
     * @return list<string>|false
     */
    private static function filesystemFile(string $filename, int $flags): array|false
    {
        $function = __NAMESPACE__ . '\\file';
        if (function_exists($function)) {
            /** @var list<string>|false $lines */
            $lines = $function($filename, $flags);

            return $lines;
        }

        return file($filename, $flags);
    }

    /** @return list<string> */
    private static function phpFromDlIteratePhdr(?LinuxFfi $ffi = null): array
    {
        try {
            return ($ffi ?? new NativeLinuxFfi())->phpLibraries();
        } catch (\Throwable) {
            return [];
        }
    }

    private static function isLoadedOnLinux(string $library, ?LinuxFfi $ffi = null): bool
    {
        try {
            return ($ffi ?? new NativeLinuxFfi())->isLoaded($library);
        } catch (\Throwable) {
            return false;
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

    /**
     * @param (\Closure(): (int|false))|null $getProcessId
     *
     * @return list<string>
     */
    private static function phpOnWindows(?WindowsFfi $ffi = null, ?\Closure $getProcessId = null): array
    {
        try {
            $ffi ??= new NativeWindowsFfi();

            $processId = ($getProcessId ?? 'getmypid')();
            if (false === $processId) {
                return [];
            }

            $snapshot = $ffi->createSnapshot(self::TH32CS_SNAPMODULE, $processId);
            if ($ffi->isNull($snapshot)) {
                return [];
            }

            try {
                $module = $ffi->newModule();
                $ffi->setModuleSize($module);
                $libraries = [];
                $hasModule = $ffi->moduleFirst($snapshot, $module);

                while ($hasModule) {
                    $name = $ffi->moduleName($module);
                    if (1 === preg_match('/^php.*\.dll$/i', $name)) {
                        $path = $ffi->modulePath($module);
                        $libraries[] = '' === $path ? $name : $path;
                    }

                    $hasModule = $ffi->moduleNext($snapshot, $module);
                }

                return array_values(array_unique($libraries));
            } finally {
                $ffi->closeHandle($snapshot);
            }
        } catch (\Throwable) {
            return [];
        }
    }
}
