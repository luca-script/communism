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
 * File: NativeWindowsFfi.php                                                 *
 * Consumer: Internal                                                         *
 * Purpose: Native Windows ToolHelp FFI implementation.                       *
 *============================================================================*/

declare(strict_types=1);

namespace Zendful\Internals;

class NativeWindowsFfi implements WindowsFfi
{
    private object $ffi;

    public function __construct()
    {
        $this->ffi = $this->cdef(<<<'CDEF'
typedef unsigned long DWORD;
typedef int BOOL;
typedef void *HANDLE;
typedef void *HMODULE;
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
HMODULE GetModuleHandleA(const char *lpModuleName);
DWORD GetModuleFileNameA(HMODULE hModule, char *lpFilename, DWORD nSize);
CDEF, 'kernel32.dll');
    }

    public function createSnapshot(int $flags, int $processId): object
    {
        /** @var \Zendful_FFI\WindowsApi $ffi */
        $ffi = $this->ffi;

        return $ffi->CreateToolhelp32Snapshot($flags, $processId);
    }

    public function isNull(object $value): bool
    {
        return $this->ffiIsNull($value);
    }

    /** @phpstan-return \Zendful_FFI\MODULEENTRY32A */
    public function newModule(): object
    {
        /** @var \Zendful_FFI\WindowsApi $ffi */
        $ffi = $this->ffi;

        /** @var \Zendful_FFI\MODULEENTRY32A $module */
        $module = $this->newModuleValue($ffi, 'MODULEENTRY32A');

        return $module;
    }

    /** @phpstan-param \Zendful_FFI\MODULEENTRY32A $module */
    public function setModuleSize(object $module): void
    {
        $module->dwSize = $this->sizeOf($module);
    }

    public function moduleFirst(object $snapshot, object $module): bool
    {
        /** @var \Zendful_FFI\WindowsApi $ffi */
        $ffi = $this->ffi;

        return (bool) $ffi->Module32First($snapshot, $this->addressOf($module));
    }

    public function moduleNext(object $snapshot, object $module): bool
    {
        /** @var \Zendful_FFI\WindowsApi $ffi */
        $ffi = $this->ffi;

        return (bool) $ffi->Module32Next($snapshot, $this->addressOf($module));
    }

    /** @phpstan-param \Zendful_FFI\MODULEENTRY32A $module */
    public function moduleName(object $module): string
    {
        return $this->string($module->szModule);
    }

    /** @phpstan-param \Zendful_FFI\MODULEENTRY32A $module */
    public function modulePath(object $module): string
    {
        return $this->string($module->szExePath);
    }

    public function closeHandle(object $snapshot): void
    {
        /** @var \Zendful_FFI\WindowsApi $ffi */
        $ffi = $this->ffi;
        $ffi->CloseHandle($snapshot);
    }

    /** @return list<string> */
    public function phpModulePaths(): array
    {
        /** @var \Zendful_FFI\WindowsApi $ffi */
        $ffi = $this->ffi;
        $paths = [];

        foreach (['php8ts.dll', 'php8.dll', 'php.dll'] as $moduleName) {
            $module = $ffi->GetModuleHandleA($moduleName);
            if ($this->ffiIsNull($module)) {
                continue;
            }

            /** @var object $buffer */
            $buffer = $ffi->new('char[32768]');
            $length = $ffi->GetModuleFileNameA($module, $buffer, 32768);
            if (0 === $length) {
                continue;
            }

            $path = $this->string($buffer);
            if ('' !== $path) {
                $paths[] = $path;
            }
        }

        return array_values(array_unique($paths));
    }

    protected function cdef(string $code, ?string $library = null): object
    {
        return \FFI::cdef($code, $library);
    }

    protected function newModuleValue(object $ffi, string $type): object
    {
        /** @var \Zendful_FFI\WindowsApi $ffi */
        return $ffi->new($type);
    }

    protected function ffiIsNull(object $value): bool
    {
        return \FFI::isNull($value);
    }

    protected function sizeOf(object $value): int
    {
        return \FFI::sizeof($value);
    }

    protected function addressOf(object $value): object
    {
        return \FFI::addr($value);
    }

    protected function string(object $value): string
    {
        return \FFI::string($value);
    }
}
