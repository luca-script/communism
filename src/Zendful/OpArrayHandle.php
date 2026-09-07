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
 * File: OpArrayHandle.php                                                    *
 * Consumer: Internal                                                         *
 * Purpose: Source file for OpArrayHandle.php.                                *
 *============================================================================*/

declare(strict_types=1);

namespace Zendful;

/** Opaque handle for a user-defined function's Zend op array. */
// Native alias is exercised by the separate native-backend probe.
// @codeCoverageIgnoreStart
if (class_exists('Zendful\\Native\\OpArrayHandle', false)) {
    class_alias('Zendful\\Native\\OpArrayHandle', OpArrayHandle::class);
    // @codeCoverageIgnoreEnd
} else {
    final class OpArrayHandle
    {
        private readonly FunctionHandle|MethodHandle $source;

        /** @var array{instructionCount: int, argumentCount: int, variableCount: int, temporaryCount: int, cacheSize: int, literalCount: int, immutable: bool, filename: string|null, lineStart: int, lineEnd: int, variableNames: array<int, string>} */
        private array $metadata;

        public function __construct(FunctionHandle|MethodHandle $source)
        {
            $this->source = $source;
            $this->metadata = Internals\Executor::opArrayMetadata($source);
        }

        public function source(): FunctionHandle|MethodHandle
        {
            return $this->source;
        }

        public function instructionCount(): int
        {
            return $this->metadata['instructionCount'];
        }

        public function argumentCount(): int
        {
            return $this->metadata['argumentCount'];
        }

        public function variableCount(): int
        {
            return $this->metadata['variableCount'];
        }

        public function temporaryCount(): int
        {
            return $this->metadata['temporaryCount'];
        }

        public function cacheSize(): int
        {
            return $this->metadata['cacheSize'];
        }

        public function literalCount(): int
        {
            return $this->metadata['literalCount'];
        }

        public function isImmutable(): bool
        {
            return $this->metadata['immutable'];
        }

        /** @return array<int, string> */
        public function variableNames(): array
        {
            return $this->metadata['variableNames'];
        }

        public function filename(): ?string
        {
            return $this->metadata['filename'];
        }

        public function lineStart(): int
        {
            return $this->metadata['lineStart'];
        }

        public function lineEnd(): int
        {
            return $this->metadata['lineEnd'];
        }

        public function assemble(AssemblyPlanHandle $plan): void
        {
            Internals\Executor::assemble($this, $plan);
            // The native backend owns the post-assembly metadata refresh.
            // @codeCoverageIgnoreStart
            $this->metadata = Internals\Executor::opArrayMetadata($this->source);
            // @codeCoverageIgnoreEnd
        }

        public function opcode(int $index): OpcodeHandle
        {
            if ($index < 0 || $index >= $this->instructionCount()) {
                throw new \OutOfRangeException(\sprintf('Opcode index is out of range: %d', $index));
            }

            return Internals\Executor::opcode($this, $index);
        }
    }
}
