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
 * File: WindowsFfi.php                                                       *
 * Consumer: Internal                                                         *
 * Purpose: FFI-shaped Windows module enumeration boundary.                   *
 *============================================================================*/

declare(strict_types=1);

namespace Zendful\Internals;

interface WindowsFfi
{
    public function createSnapshot(int $flags, int $processId): object;

    public function isNull(object $value): bool;

    /** @phpstan-return \Zendful_FFI\MODULEENTRY32A */
    public function newModule(): object;

    /** @phpstan-param \Zendful_FFI\MODULEENTRY32A $module */
    public function setModuleSize(object $module): void;

    /** @phpstan-param \Zendful_FFI\MODULEENTRY32A $module */
    public function moduleFirst(object $snapshot, object $module): bool;

    /** @phpstan-param \Zendful_FFI\MODULEENTRY32A $module */
    public function moduleNext(object $snapshot, object $module): bool;

    /** @phpstan-param \Zendful_FFI\MODULEENTRY32A $module */
    public function moduleName(object $module): string;

    /** @phpstan-param \Zendful_FFI\MODULEENTRY32A $module */
    public function modulePath(object $module): string;

    public function closeHandle(object $snapshot): void;
}
