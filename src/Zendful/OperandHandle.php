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
 * File: OperandHandle.php                                                    *
 * Consumer: Internal                                                         *
 * Purpose: Source file for OperandHandle.php.                                *
 *============================================================================*/

declare(strict_types=1);

namespace Zendful;

use InvalidArgumentException;

// Native alias is exercised by the separate native-backend probe.
// @codeCoverageIgnoreStart
if (class_exists('Zendful\\Native\\OperandHandle', false)) {
    class_alias('Zendful\\Native\\OperandHandle', OperandHandle::class);
    // @codeCoverageIgnoreEnd
} else {
    final class OperandHandle
    {
        public const TYPE_UNUSED = 0;
        public const TYPE_CONST = 1;
        public const TYPE_TMP_VAR = 2;
        public const TYPE_VAR = 4;
        public const TYPE_CV = 8;

        public function __construct(
            private readonly int $type,
            private readonly int $constant,
            private readonly int $var,
            private readonly int $num,
            private readonly string $constantDescription,
            private readonly mixed $value = null,
            private readonly ?int $literalIndex = null,
        ) {
            if ($type < 0
                || ($literalIndex !== null && $literalIndex < 0)
                || str_contains($constantDescription, "\0")
            ) {
                throw new InvalidArgumentException('Operand metadata contains an invalid index or NUL byte.');
            }
        }

        public function type(): int
        {
            return $this->type;
        }

        public function constant(): int
        {
            return $this->constant;
        }

        public function variable(): int
        {
            return $this->var;
        }

        public function number(): int
        {
            return $this->num;
        }

        public function constantDescription(): string
        {
            return $this->constantDescription;
        }

        public function value(): mixed
        {
            return $this->value;
        }

        public function literalIndex(): ?int
        {
            return $this->literalIndex;
        }
    }
}
