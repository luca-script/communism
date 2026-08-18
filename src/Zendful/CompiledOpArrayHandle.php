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
 * File: CompiledOpArrayHandle.php                                            *
 * Consumer: Internal                                                         *
 * Purpose: Safe handle for detached compiler op-array data.                  *
 *============================================================================*/

declare(strict_types=1);

namespace Zendful;

use InvalidArgumentException;

/** A detached, compiler-owned op array represented without native memory. */
// Native alias is exercised by the separate native-backend probe.
// @codeCoverageIgnoreStart
if (class_exists('Zendful\\Native\\CompiledOpArrayHandle', false)) {
    class_alias('Zendful\\Native\\CompiledOpArrayHandle', CompiledOpArrayHandle::class);
    // @codeCoverageIgnoreEnd
} else {
    final class CompiledOpArrayHandle
    {
        /** @param array{instructionCount: int, filename: string|null, lineStart: int, lineEnd: int, variableNames: array<int, string>, temporaryCount: int, cacheSize: int} $metadata
         * @param list<OpcodeHandle> $opcodes
         */
        public function __construct(
            private readonly array $metadata,
            private readonly array $opcodes,
        ) {
            self::validate($metadata, $opcodes);
        }

        /**
         * @param array<int|string, mixed> $metadata
         * @param array<int|string, mixed> $opcodes
         */
        private static function validate(array $metadata, array $opcodes): void
        {
            if (!array_key_exists('instructionCount', $metadata)
                || !array_key_exists('filename', $metadata)
                || !array_key_exists('lineStart', $metadata)
                || !array_key_exists('lineEnd', $metadata)
                || !array_key_exists('variableNames', $metadata)
                || !array_key_exists('temporaryCount', $metadata)
                || !array_key_exists('cacheSize', $metadata)
                || !is_int($metadata['instructionCount'])
                || $metadata['instructionCount'] < 0
                || ($metadata['filename'] !== null
                    && (!is_string($metadata['filename']) || str_contains($metadata['filename'], "\0")))
                || !is_int($metadata['lineStart'])
                || $metadata['lineStart'] < 0
                || !is_int($metadata['lineEnd'])
                || $metadata['lineEnd'] < 0
                || $metadata['lineEnd'] < $metadata['lineStart']
                || !is_array($metadata['variableNames'])
                || !is_int($metadata['temporaryCount'])
                || $metadata['temporaryCount'] < 0
                || !is_int($metadata['cacheSize'])
                || $metadata['cacheSize'] < 0
                || !array_is_list($opcodes)
                || count($opcodes) !== $metadata['instructionCount']) {
                throw new InvalidArgumentException('Compiled op-array metadata has an invalid shape.');
            }

            foreach ($metadata['variableNames'] as $index => $name) {
                if (!is_int($index) || $index < 0 || !is_string($name) || str_contains($name, "\0")) {
                    throw new InvalidArgumentException('Compiled variable metadata has an invalid shape or contains NUL bytes.');
                }
            }
            foreach ($opcodes as $opcode) {
                if (!$opcode instanceof OpcodeHandle) {
                    throw new InvalidArgumentException('Compiled op arrays must contain opcode handles.');
                }
            }
        }

        public function instructionCount(): int
        {
            return $this->metadata['instructionCount'];
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

        /** @return array<int, string> */
        public function variableNames(): array
        {
            return $this->metadata['variableNames'];
        }

        public function temporaryCount(): int
        {
            return $this->metadata['temporaryCount'];
        }

        public function cacheSize(): int
        {
            return $this->metadata['cacheSize'];
        }

        public function opcode(int $index): OpcodeHandle
        {
            if (!isset($this->opcodes[$index])) {
                throw new \OutOfRangeException(\sprintf('Opcode index is out of range: %d', $index));
            }

            return $this->opcodes[$index];
        }
    }
}
