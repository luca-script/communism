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
 * File: Injector.php                                                         *
 * Consumer: Internal                                                         *
 * Purpose: Source file for Injector.php.                                     *
 *============================================================================*/

declare(strict_types=1);

namespace Communism\Internals\Needle;

use Communism\Mixin\CallbackInfo;
use Communism\Mixin\CallbackInfoReturnable;
use Communism\Mixin\Coerce;
use Communism\Mixin\Inject;
use Communism\Mixin\LocalCapture;
use InvalidArgumentException;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use Zendful\Zendful;
use Zendful\OpArrayHandle;

use function in_array;
use function is_array;
use function is_int;
use function is_string;
use function str_starts_with;
use function strtolower;

/**
 * Coordinates declarative Mixin injection points with executable handlers.
 *
 * Handler bodies are detached from the source literal pool, temporary slots
 * are relocated into the target frame, and the rewritten body is passed to
 * Assembler. CallbackInfo parameters are virtual: their calls are lowered to
 * ordinary target opcodes before the handler is copied.
 */
final class Injector
{
    /** @return list<MatchResult> */
    public static function locate(MethodBody $body, Inject $inject): array
    {
        return Matcher::find(
            $body,
            $inject->at,
            $inject->argumentIndex,
            $inject->slice,
            $inject->constantType,
            $inject->variableIndex,
        );
    }

    /**
     * Resolve every injection for a body before an emitter is allowed to
     * replace its opcode storage.
     *
     * @param list<Inject> $injections
     * @return list<ResolvedInjection>
     */
    public static function resolve(MethodBody $body, array $injections): array
    {
        $resolved = [];
        $groups = [];
        foreach ($injections as $inject) {
            $matches = self::locate($body, $inject);
            $matchCount = count($matches);
            if ($inject->require !== null && $matchCount < $inject->require) {
                throw new InvalidArgumentException(sprintf(
                    'Injection %s in %s requires at least %d match(es), found %d',
                    $inject->at->description(),
                    $body->name,
                    $inject->require,
                    $matchCount,
                ));
            }
            if ($inject->allow !== null && $matchCount > $inject->allow) {
                throw new InvalidArgumentException(sprintf(
                    'Injection %s in %s allows at most %d match(es), found %d',
                    $inject->at->description(),
                    $body->name,
                    $inject->allow,
                    $matchCount,
                ));
            }
            if ($inject->expect !== null && $matchCount !== $inject->expect) {
                throw new InvalidArgumentException(sprintf(
                    'Injection %s in %s expects %d match(es), found %d',
                    $inject->at->description(),
                    $body->name,
                    $inject->expect,
                    $matchCount,
                ));
            }
            if ($matches === [] && $inject->require !== 0 && $inject->expect !== 0) {
                throw new InvalidArgumentException(sprintf(
                    'Injection point did not match %s in %s',
                    $inject->at->description(),
                    $body->name,
                ));
            }

            $resolved[] = new ResolvedInjection($inject, $matches);
            if ($inject->group !== null) {
                $name = $inject->group->name;
                if (!isset($groups[$name])) {
                    $groups[$name] = ['group' => $inject->group, 'count' => 0];
                }
                $groups[$name]['count'] += $matchCount;
            }
        }

        foreach ($groups as $groupData) {
            $group = $groupData['group'];
            $count = $groupData['count'];
            if ($count < $group->min || $count > $group->max) {
                throw new InvalidArgumentException(sprintf(
                    'Injection group %s in %s expects between %d and %d match(es), found %d',
                    $group->name,
                    $body->name,
                    $group->min,
                    $group->max,
                    $count,
                ));
            }
        }

        return $resolved;
    }

    /**
     * Inline handler methods at every resolved injection spot.
     *
     * Constants are detached from the handler literal pool so Assembler can
     * allocate them in the target pool. A CallbackInfo parameter is removed
     * during lowering and target arguments are mapped by parameter position.
     *
     * @param list<Inject> $injections
     * @param callable(Inject): mixed $handler
     * @param (callable(Inject, MethodBody, CaptureException): (MethodBody|null))|null $surrogate
     */
    public static function inject(MethodBody $body, array $injections, callable $handler, ?callable $surrogate = null): MethodBody
    {
        $resolved = self::resolve($body, $injections);
        $originalInstructionCount = $body->count();
        $placements = [];
        $temporaryCount = $body->temporaryCount;
        $cacheSize = $body->cacheSize;

        foreach ($resolved as $resolution) {
            $handlerBody = $handler($resolution->inject);
            if (!$handlerBody instanceof MethodBody) {
                throw new InvalidArgumentException('An injection handler resolver must return a MethodBody');
            }

            $temporaryOffset = $body->temporaryCount;
            $temporaryCount = max($temporaryCount, $body->temporaryCount + $handlerBody->temporaryCount);
            $cacheOffset = $cacheSize;
            $sourceCacheBase = self::cacheBase($handlerBody);
            $cacheSize += max(0, $handlerBody->cacheSize - $sourceCacheBase);
            foreach ($resolution->matches as $match) {
                $prepared = false;
                try {
                    [$handlerInstructions, $handlerReturn, $argumentReplacements, $extraTemporaryCount] = self::prepareHandler(
                        $body,
                        $handlerBody,
                        $resolution->inject,
                        $match,
                        $temporaryOffset,
                        $cacheOffset,
                        $sourceCacheBase,
                    );
                    $prepared = true;
                } catch (CaptureException $exception) {
                    $fallback = $surrogate === null ? null : $surrogate($resolution->inject, $handlerBody, $exception);
                    if ($fallback instanceof MethodBody) {
                        $handlerBody = $fallback;
                        try {
                            [$handlerInstructions, $handlerReturn, $argumentReplacements, $extraTemporaryCount] = self::prepareHandler(
                                $body,
                                $handlerBody,
                                $resolution->inject,
                                $match,
                                $temporaryOffset,
                                $cacheOffset,
                                $sourceCacheBase,
                            );
                            $prepared = true;
                        } catch (CaptureException $fallbackException) {
                            $exception = $fallbackException;
                        }
                    }
                    if ($prepared) {
                        // The primary or surrogate handler was prepared.
                    } else {
                        if ($resolution->inject->locals === LocalCapture::FAILSOFT) {
                            continue;
                        }
                        if ($resolution->inject->locals === LocalCapture::PRINT) {
                            fwrite(STDERR, sprintf("Needle local capture failed: %s\n", $exception->getMessage()));
                        }

                        throw $exception;
                    }
                }
                $temporaryCount = max($temporaryCount, $temporaryOffset + $handlerBody->temporaryCount + $extraTemporaryCount);
                $original = array_slice($body->instructions(), $match->start, $match->length());
                $replacement = self::replacement($body, $match, $original, $handlerInstructions, $handlerReturn, $argumentReplacements);
                $placements[] = [
                    'start' => $match->start,
                    'end' => $match->end,
                    'before' => $match->action === 'before' ? $handlerInstructions : [],
                    'replacement' => $replacement,
                    'after' => $match->action === 'after' ? $handlerInstructions : [],
                    'isReplace' => in_array($match->action, ['modify', 'replace'], true),
                ];
            }
        }

        $grouped = [];
        foreach ($placements as $placement) {
            $key = $placement['start'] . ':' . $placement['end'];
            if (!isset($grouped[$key])) {
                $grouped[$key] = $placement;
                continue;
            }

            if ($placement['isReplace'] && $grouped[$key]['isReplace']) {
                throw new InvalidArgumentException('Multiple replacement injections target the same spot');
            }
            if ($placement['isReplace']) {
                $grouped[$key]['replacement'] = $placement['replacement'];
                $grouped[$key]['isReplace'] = true;
            }
            if ($placement['before'] !== []) {
                $grouped[$key]['before'] = [...$grouped[$key]['before'], ...$placement['before']];
            }
            if ($placement['after'] !== []) {
                $grouped[$key]['after'] = [...$grouped[$key]['after'], ...$placement['after']];
            }
        }

        $placements = array_values($grouped);
        usort($placements, static fn(array $left, array $right): int => $left['start'] <=> $right['start']);
        $previousEnd = -1;
        foreach ($placements as $placement) {
            if ($placement['start'] < $previousEnd) {
                throw new InvalidArgumentException('Injection spots overlap and cannot be rewritten safely');
            }
            $previousEnd = $placement['end'];
        }

        for ($index = count($placements) - 1; $index >= 0; $index--) {
            $placement = $placements[$index];
            $body = $body->replaceRange(
                $placement['start'],
                $placement['end'] - $placement['start'],
                [...$placement['before'], ...$placement['replacement'], ...$placement['after']],
            );
        }

        $body = $body->withInstructions(self::relocateJumps($body->instructions(), $originalInstructionCount));

        return $body->withTemporaryCount($temporaryCount)->withCacheSize($cacheSize);
    }

    /**
     * Resolve all anchors before allowing a rewritten body to be assembled.
     *
     * @param list<Inject> $injections
     * @param callable(MethodBody, list<ResolvedInjection>): mixed $rewrite
     */
    public static function rewrite(MethodBody $body, OpArrayHandle $opArray, array $injections, callable $rewrite): void
    {
        $resolved = self::resolve($body, $injections);
        $rewritten = $rewrite($body, $resolved);
        if (!$rewritten instanceof MethodBody) {
            throw new InvalidArgumentException('An injection rewrite callback must return a MethodBody');
        }

        Assembler::write($rewritten, $opArray);
    }

    /** @return list<MatchResult> */
    public static function requireMatch(MethodBody $body, Inject $inject): array
    {
        $resolved = self::resolve($body, [$inject]);

        return $resolved[0]->matches;
    }

    /**
     * @return array{0: list<Instruction>, 1: ?Instruction, 2: ?array<int, Operand>, 3: int}
     */
    private static function prepareHandler(
        MethodBody $target,
        MethodBody $handler,
        Inject $inject,
        MatchResult $match,
        int $temporaryOffset,
        int $cacheOffset,
        int $sourceCacheBase,
    ): array {
        if ($inject->mode === 'args') {
            [$instructions, $return, $replacements] = self::prepareArgsHandler($target, $handler, $match, $temporaryOffset, $cacheOffset, $sourceCacheBase);

            return [$instructions, $return, $replacements, 0];
        }

        $context = self::callbackContext($target, $handler, $inject, $match);
        if ($context === null) {
            $cvMap = self::modifierInputMap($target, $handler, $match);
            [$cvMap, $extraTemporaryCount] = self::mapHandlerLocals($target, $handler, $cvMap, [], $temporaryOffset);

            return [
                self::executableInstructions($handler, $temporaryOffset, $cacheOffset, $cvMap, [], [], false, $sourceCacheBase),
                self::returnInstruction($handler, $temporaryOffset, $cacheOffset, $cvMap),
                null,
                $extraTemporaryCount,
            ];
        }

        $lowered = self::lowerCallback($target, $handler, $context);
        [$cvMap, $extraTemporaryCount] = self::mapHandlerLocals(
            $target,
            $handler,
            $context['cvMap'],
            $context['callbackCvs'],
            $temporaryOffset,
        );
        $instructions = self::executableInstructions(
            $lowered['body'],
            $temporaryOffset,
            $cacheOffset,
            $cvMap,
            $context['callbackCvs'],
            $lowered['preserve'],
            true,
            $sourceCacheBase,
        );

        return [$instructions, null, null, $extraTemporaryCount];
    }

    /**
     * @return array{0: list<Instruction>, 1: ?Instruction, 2: ?array<int, Operand>}
     */
    private static function prepareArgsHandler(
        MethodBody $target,
        MethodBody $handler,
        MatchResult $match,
        int $temporaryOffset,
        int $cacheOffset,
        int $sourceCacheBase,
    ): array {
        if ($match->type !== 'invoke') {
            throw new InvalidArgumentException('ModifyArgs requires an INVOKE injection point');
        }

        $parameters = self::reflection($handler->name)->getParameters();
        if (count($parameters) !== 1 || !self::isArgsParameter($parameters[0])) {
            throw new InvalidArgumentException('A ModifyArgs handler must accept exactly one Args parameter');
        }

        $receives = array_values(array_filter(
            $handler->instructions(),
            static fn(Instruction $instruction): bool => $instruction->name === 'RECV',
        ));
        $receive = $receives[0] ?? null;
        if ($receive === null || $receive->result->kind !== Operand::CV || !is_int($receive->result->value)) {
            throw new InvalidArgumentException('A ModifyArgs handler has no argument CV');
        }

        $arguments = self::invocationArgumentOperands($target, $match);
        [$instructions, $replacements] = self::lowerArgs($target, $handler, $receive->result->value, $arguments);
        $detachedReplacements = self::emptyOperandMap();
        foreach ($replacements as $index => $operand) {
            $detachedReplacements[$index] = self::detachOperand(
                $operand,
                $temporaryOffset,
                0,
                false,
                [],
            );
        }

        return [
            self::executableInstructions(
                $handler->withInstructions($instructions),
                $temporaryOffset,
                $cacheOffset,
                [],
                [],
                [],
                false,
                $sourceCacheBase,
            ),
            null,
            $detachedReplacements,
        ];
    }

    /**
     * Remap handler CVs into the target frame. Locals with the same name use
     * the target CV, while handler-only locals are assigned fresh temporaries.
     *
     * @param array<int, Operand> $cvMap
     * @param list<int> $callbackCvs
     * @return array{0: array<int, Operand>, 1: int}
     */
    private static function mapHandlerLocals(
        MethodBody $target,
        MethodBody $handler,
        array $cvMap,
        array $callbackCvs,
        int $temporaryOffset,
    ): array {
        $cvValues = [];
        $requiredTemporaryCount = $handler->temporaryCount;
        foreach ($handler->instructions() as $instruction) {
            foreach ([$instruction->result, $instruction->operand1, $instruction->operand2] as $operand) {
                if ($operand->kind === Operand::CV && is_int($operand->value)) {
                    $cvValues[$operand->value] = true;
                }
                if (in_array($operand->kind, [Operand::TEMPORARY, Operand::VARIABLE], true) && is_int($operand->value)) {
                    $requiredTemporaryCount = max($requiredTemporaryCount, intdiv($operand->value, 16) - 4);
                }
            }
        }

        $nextTemporary = $temporaryOffset + $handler->temporaryCount;
        $extraTemporaryCount = max(0, $requiredTemporaryCount - $handler->temporaryCount);
        foreach (array_keys($cvValues) as $cvValue) {
            if (isset($cvMap[$cvValue]) || in_array($cvValue, $callbackCvs, true)) {
                continue;
            }

            $name = $handler->variableName(Operand::cv($cvValue));
            $targetOperand = $name === null ? null : $target->variableOperand($name);
            if ($targetOperand !== null) {
                $cvMap[$cvValue] = $targetOperand;
                continue;
            }

            $cvMap[$cvValue] = Operand::temporary(($nextTemporary + 5) * 16);
            $nextTemporary++;
            $extraTemporaryCount++;
        }

        return [$cvMap, $extraTemporaryCount];
    }

    private static function isArgsParameter(ReflectionParameter $parameter): bool
    {
        $type = $parameter->getType();

        return $type instanceof ReflectionNamedType && $type->getName() === \Communism\Mixin\Args::class;
    }

    /** @return list<Operand> */
    private static function invocationArgumentOperands(MethodBody $target, MatchResult $match): array
    {
        $arguments = [];
        for ($index = $match->start + 1; $index < $match->end; $index++) {
            $instruction = $target->instruction($index);
            if (str_starts_with($instruction->name, 'SEND')) {
                $arguments[] = $instruction->operand1;
            }
        }

        return $arguments;
    }

    /**
     * @param list<Operand> $arguments
     * @return array{0: list<Instruction>, 1: array<int, Operand>}
     */
    private static function lowerArgs(
        MethodBody $target,
        MethodBody $handler,
        int $argsCv,
        array $arguments,
    ): array {
        $source = $handler->instructions();
        $rewritten = [];
        $replacements = self::emptyOperandMap();
        $values = self::emptyValueMap();
        for ($index = 0; $index < count($source); $index++) {
            $instruction = $source[$index];
            if ($instruction->name === 'RETURN') {
                if ($instruction->operand1->kind === Operand::CV && $instruction->operand1->value === $argsCv) {
                    throw new InvalidArgumentException('A virtual Args value escaped from its lowered calls');
                }
                continue;
            }
            if (in_array($instruction->name, ['RECV', 'VERIFY_RETURN_TYPE'], true)) {
                continue;
            }
            if ($instruction->name !== 'INIT_METHOD_CALL' || !self::isArgsCall($instruction, $argsCv)) {
                $rewritten[] = self::replaceArgsOperands($instruction, $values);
                continue;
            }

            $callEnd = self::callbackCallEnd($source, $index);
            if ($callEnd === null) {
                throw new InvalidArgumentException('A virtual Args call has no DO_METHOD_CALL');
            }
            $inner = $callEnd === $index + 1
                ? []
                : self::lowerArgsRange($target, $source, $index + 1, $callEnd - 1, $argsCv, $arguments, $replacements);
            if ($instruction->operand2->kind !== Operand::CONSTANT || !is_string($instruction->operand2->value)) {
                // @codeCoverageIgnoreStart
                throw new InvalidArgumentException('A virtual Args method name must be a string');
                // @codeCoverageIgnoreEnd
            }
            $method = strtolower($instruction->operand2->value);
            $do = $source[$callEnd];
            $directSendIndices = self::directSendIndices($source, $index + 1, $callEnd - 1);
            $sends = self::sendOperands($inner, $directSendIndices);
            if (in_array($method, ['get', 'offsetget'], true)) {
                $argumentIndex = self::argumentIndex($sends[0] ?? null, $arguments);
                if ($argumentIndex === null) {
                    throw new InvalidArgumentException('Args::get requires a valid argument index');
                }
                $values[self::operandKey($do->result)] = $arguments[$argumentIndex];
            } elseif (in_array($method, ['set', 'offsetset'], true)) {
                $argumentIndex = self::argumentIndex($sends[0] ?? null, $arguments);
                if ($argumentIndex === null || !isset($sends[1])) {
                    throw new InvalidArgumentException('Args::set requires an argument index and value');
                }
                $replacements[$argumentIndex] = $sends[1];
            } elseif ($method === 'setall') {
                if (count($sends) !== 1 || $sends[0]->kind !== Operand::CONSTANT || !is_array($sends[0]->value)) {
                    throw new InvalidArgumentException('Args::setAll requires a statically known list of values');
                }
                $setAllValues = $sends[0]->value;
                if (!array_is_list($setAllValues)) {
                    throw new InvalidArgumentException('Args::setAll requires a list of values');
                }
                if (count($setAllValues) !== count($arguments)) {
                    throw new InvalidArgumentException('Args::setAll must provide exactly one value per argument');
                }

                foreach ($setAllValues as $argumentIndex => $value) {
                    if (!is_null($value) && !is_bool($value) && !is_int($value) && !is_float($value) && !is_string($value)) {
                        throw new InvalidArgumentException('Args::setAll only supports scalar values');
                    }
                    $replacements[$argumentIndex] = Operand::constant($value, 0);
                }
            } elseif (in_array($method, ['getcount', 'count'], true)) {
                if ($sends !== []) {
                    throw new InvalidArgumentException('Args::getCount does not accept arguments');
                }
                $values[self::operandKey($do->result)] = Operand::constant(count($arguments), 0);
            } else {
                throw new InvalidArgumentException(sprintf('Unsupported virtual Args method %s', $method));
            }
            $rewritten = [...$rewritten, ...self::withoutSends($inner, $directSendIndices)];
            $index = $callEnd;
        }

        return [$rewritten, $replacements];
    }

    /**
     * @param list<Instruction> $source
     * @param array<int, Operand> $replacements
     * @param list<Operand> $arguments
     * @return list<Instruction>
     */
    private static function lowerArgsRange(
        MethodBody $target,
        array $source,
        int $start,
        int $end,
        int $argsCv,
        array $arguments,
        array &$replacements,
    ): array {
        $body = new MethodBody('', null, 0, 0, $source);
        $subset = array_slice($source, $start, $end - $start + 1);
        $body = $body->withInstructions($subset);
        [$lowered, $nestedReplacements] = self::lowerArgs($target, $body, $argsCv, $arguments);
        foreach ($nestedReplacements as $index => $operand) {
            $replacements[$index] = $operand;
        }
        return $lowered;
    }

    private static function isArgsCall(Instruction $instruction, int $argsCv): bool
    {
        return $instruction->operand1->kind === Operand::CV
            && $instruction->operand1->value === $argsCv
            && $instruction->operand2->kind === Operand::CONSTANT
            && is_string($instruction->operand2->value);
    }

    /**
     * @param list<Instruction> $instructions
     * @param list<int> $directSendIndices
     * @return list<Operand>
     */
    private static function sendOperands(array $instructions, array $directSendIndices): array
    {
        $operands = [];
        foreach ($instructions as $instruction) {
            if (str_starts_with($instruction->name, 'SEND')
                && $instruction->originalIndex !== null
                && in_array($instruction->originalIndex, $directSendIndices, true)
            ) {
                $operands[] = $instruction->operand1;
            }
        }
        return $operands;
    }

    /**
     * @param list<Instruction> $instructions
     * @return list<int>
     */
    private static function directSendIndices(array $instructions, int $start, int $end): array
    {
        $indices = [];
        $depth = 0;
        for ($index = $start; $index <= $end; $index++) {
            $instruction = $instructions[$index];
            if (self::isCallStart($instruction->name)) {
                $depth++;
                continue;
            }
            if (str_starts_with($instruction->name, 'DO_')) {
                $depth = max(0, $depth - 1);
                continue;
            }
            if ($depth === 0 && str_starts_with($instruction->name, 'SEND') && $instruction->originalIndex !== null) {
                $indices[] = $instruction->originalIndex;
            }
        }

        return $indices;
    }

    /** @param list<Operand> $arguments */
    private static function argumentIndex(?Operand $operand, array $arguments): ?int
    {
        if ($operand === null || $operand->kind !== Operand::CONSTANT || !is_int($operand->value)) {
            return null;
        }
        return $operand->value >= 0 && $operand->value < count($arguments) ? $operand->value : null;
    }

    private static function operandKey(Operand $operand): string
    {
        return $operand->kind . ':' . (is_int($operand->value) || is_string($operand->value) ? (string) $operand->value : serialize($operand->value));
    }

    /** @param array<string, Operand> $values */
    private static function replaceArgsOperands(Instruction $instruction, array $values): Instruction
    {
        $replace = static function (Operand $operand) use ($values): Operand {
            return $values[self::operandKey($operand)] ?? $operand;
        };

        $operand1 = $replace($instruction->operand1);
        if (str_starts_with($instruction->name, 'SEND')) {
            [$opcode, $name] = self::sendOpcode($instruction, $operand1);

            return new Instruction(
                $opcode,
                $name,
                $instruction->result,
                $operand1,
                $instruction->operand2,
                $instruction->extendedValue,
                $instruction->line,
                $instruction->handler,
                $instruction->originalIndex,
            );
        }

        return $instruction->withOperands($operand1, $replace($instruction->operand2), $replace($instruction->result));
    }

    /** @return array{0: int, 1: string} */
    private static function sendOpcode(Instruction $original, Operand $operand): array
    {
        if ($operand->kind === Operand::CONSTANT) {
            return [Zendful::opcodeId('SEND_VAL'), 'SEND_VAL'];
        }

        if ($operand->kind === Operand::TEMPORARY) {
            return [Zendful::opcodeId('SEND_VAL_EX'), 'SEND_VAL_EX'];
        }

        // SEND_VAR_EX accepts both CV and VAR operands. SEND_VAR is still
        // emitted by some compilers, but it is not a stable lookup name for
        // zend_get_opcode_id() on every supported PHP development build.
        return [Zendful::opcodeId('SEND_VAR_EX'), 'SEND_VAR_EX'];
    }

    /**
     * @param list<Instruction> $instructions
     * @param list<int> $directSendIndices
     * @return list<Instruction>
     */
    private static function withoutSends(array $instructions, array $directSendIndices): array
    {
        return array_values(array_filter(
            $instructions,
            static fn(Instruction $instruction): bool => !str_starts_with($instruction->name, 'SEND')
                || $instruction->originalIndex === null
                || !in_array($instruction->originalIndex, $directSendIndices, true),
        ));
    }

    /** @return array<int, Operand> */
    private static function emptyOperandMap(): array
    {
        return [];
    }

    /** @return array<string, Operand> */
    private static function emptyValueMap(): array
    {
        return [];
    }

    /** @return list<Instruction> */
    /**
     * @param array<int, Operand> $cvMap
     * @param list<int> $callbackCvs
     * @param array<int, bool> $preserve
     * @return list<Instruction>
     */
    private static function executableInstructions(
        MethodBody $body,
        int $temporaryOffset,
        int $cacheOffset,
        array $cvMap = [],
        array $callbackCvs = [],
        array $preserve = [],
        bool $keepReturns = false,
        int $sourceCacheBase = 0,
    ): array {
        $instructions = [];
        foreach ($body->instructions() as $instruction) {
            if ($instruction->name === 'RECV') {
                continue;
            }
            if ($instruction->name === 'VERIFY_RETURN_TYPE' || ($instruction->name === 'RETURN' && !$keepReturns)) {
                continue;
            }

            $instructions[] = self::detach($instruction, $temporaryOffset, $cacheOffset, $cvMap, $callbackCvs, $preserve, $sourceCacheBase);
        }

        return $instructions;
    }

    /** @param array<int, Operand> $cvMap */
    private static function returnInstruction(MethodBody $body, int $temporaryOffset, int $cacheOffset, array $cvMap = []): ?Instruction
    {
        $returnType = self::reflection($body->name)->getReturnType();
        if ($returnType instanceof ReflectionNamedType && $returnType->getName() === 'void') {
            return null;
        }

        foreach ($body->instructions() as $instruction) {
            if ($instruction->name === 'RETURN') {
                return self::detach($instruction, $temporaryOffset, $cacheOffset, $cvMap);
            }
        }

        return null;
    }

    /**
     * @return array{
     *     callbackCvs: list<int>,
     *     callbackReturnable: bool,
     *     cancellable: bool,
     *     cvMap: array<int, Operand>,
     *     returnOperand: Operand,
     *     preserveReturnOperand: bool,
     * }|null
     */
    private static function callbackContext(
        MethodBody $target,
        MethodBody $handler,
        Inject $inject,
        MatchResult $match,
    ): ?array {
        $handlerReflection = self::reflection($handler->name);
        $handlerParameters = $handlerReflection->getParameters();
        $hasCallbackParameter = false;
        foreach ($handlerParameters as $parameter) {
            if (self::callbackParameterType($parameter) !== null) {
                $hasCallbackParameter = true;
                break;
            }
        }
        if (!$hasCallbackParameter) {
            return null;
        }

        $handlerReceives = array_values(array_filter(
            $handler->instructions(),
            static fn(Instruction $instruction): bool => $instruction->name === 'RECV',
        ));
        $callbackCvs = [];
        $callbackReturnable = false;
        $hasCallback = false;
        $cvMap = [];
        $targetReflection = self::reflection($target->name);
        $targetParameters = $targetReflection->getParameters();
        $targetReceives = array_values(array_filter(
            $target->instructions(),
            static fn(Instruction $instruction): bool => $instruction->name === 'RECV',
        ));
        $targetArgument = 0;

        foreach ($handlerParameters as $parameterIndex => $parameter) {
            $sourceReceive = $handlerReceives[$parameterIndex] ?? null;
            if ($sourceReceive === null || $sourceReceive->result->kind !== Operand::CV || !is_int($sourceReceive->result->value)) {
                continue;
            }

            $callbackType = self::callbackParameterType($parameter);
            if ($callbackType !== null) {
                $hasCallback = true;
                $callbackCvs[] = $sourceReceive->result->value;
                $callbackReturnable = $callbackReturnable || $callbackType === CallbackInfoReturnable::class;
                continue;
            }

            $namedTarget = $target->variableOperand($parameter->getName());
            if ($namedTarget !== null) {
                if ($inject->locals === LocalCapture::NO_CAPTURE) {
                    $targetParameter = $targetParameters[$targetArgument] ?? null;
                    if (!$targetParameter instanceof ReflectionParameter || $targetParameter->getName() !== $parameter->getName()) {
                        throw new CaptureException(sprintf(
                            'Callback parameter $%s in %s is a local and local capture is disabled',
                            $parameter->getName(),
                            $handler->name,
                        ));
                    }
                    self::validateCallbackParameterType($parameter, $targetParameter, $handler->name, $handlerReflection->getAttributes(Coerce::class) !== []);
                }
                $cvMap[$sourceReceive->result->value] = $namedTarget;
                $positionalTarget = $targetParameters[$targetArgument] ?? null;
                if ($positionalTarget instanceof ReflectionParameter && $positionalTarget->getName() === $parameter->getName()) {
                    self::validateCallbackParameterType($parameter, $positionalTarget, $handler->name, $handlerReflection->getAttributes(Coerce::class) !== []);
                    $targetArgument++;
                }
                continue;
            }

            $targetParameter = $targetParameters[$targetArgument] ?? null;
            $targetReceive = $targetReceives[$targetArgument] ?? null;
            $mapped = false;
            if ($targetParameter instanceof ReflectionParameter) {
                $targetOperand = $target->variableOperand($targetParameter->getName());
                if ($targetOperand !== null) {
                    self::validateCallbackParameterType($parameter, $targetParameter, $handler->name, $handlerReflection->getAttributes(Coerce::class) !== []);
                    $cvMap[$sourceReceive->result->value] = $targetOperand;
                    $mapped = true;
                }
            } elseif ($targetReceive instanceof Instruction && $targetReceive->result->kind === Operand::CV) {
                $cvMap[$sourceReceive->result->value] = $targetReceive->result;
                $mapped = true;
            }
            if (!$mapped) {
                throw new CaptureException(sprintf(
                    'Callback parameter $%s in %s cannot be captured from %s',
                    $parameter->getName(),
                    $handler->name,
                    $target->name,
                ));
            }
            $targetArgument++;
        }

        if (!$hasCallback) {
            return null;
        }

        if ($match->type === 'return') {
            $returnOperand = $target->instruction($match->start)->operand1;
            $preserveReturnOperand = true;
        } else {
            $returnOperand = Operand::constant(self::defaultReturnValue($targetReflection), 0);
            $preserveReturnOperand = false;
        }

        return [
            'callbackCvs' => $callbackCvs,
            'callbackReturnable' => $callbackReturnable,
            'cancellable' => $inject->cancellable,
            'cvMap' => $cvMap,
            'returnOperand' => $returnOperand,
            'preserveReturnOperand' => $preserveReturnOperand,
        ];
    }

    private static function validateCallbackParameterType(
        ReflectionParameter $handler,
        ReflectionParameter $target,
        string $handlerName,
        bool $methodCoerce,
    ): void {
        $handlerType = $handler->getType();
        $targetType = $target->getType();
        if (!$handlerType instanceof ReflectionNamedType || !$targetType instanceof ReflectionNamedType) {
            return;
        }
        if ($targetType->allowsNull() && !$handlerType->allowsNull()) {
            throw new CaptureException(sprintf(
                'Callback parameter $%s in %s cannot accept nullable target parameter $%s; use #[Coerce] only for compatible non-null types',
                $handler->getName(),
                $handlerName,
                $target->getName(),
            ));
        }

        $expected = $handlerType->getName();
        $actual = $targetType->getName();
        if (self::typeAccepts($expected, $actual)) {
            return;
        }
        if (($methodCoerce || $handler->getAttributes(Coerce::class) !== []) && self::canCoerce($actual, $expected)) {
            return;
        }

        throw new CaptureException(sprintf(
            'Callback parameter $%s in %s expects %s, but target parameter $%s provides %s; add #[Coerce] for a compatible coercion',
            $handler->getName(),
            $handlerName,
            $expected,
            $target->getName(),
            $actual,
        ));
    }

    private static function typeAccepts(string $expected, string $actual): bool
    {
        if ($expected === $actual || $expected === 'mixed') {
            return true;
        }
        if ($expected === 'object') {
            return !in_array($actual, ['array', 'bool', 'callable', 'float', 'int', 'iterable', 'null', 'resource', 'string'], true);
        }
        if ($expected === 'iterable') {
            return $actual === 'array' || (!in_array($actual, ['bool', 'callable', 'float', 'int', 'null', 'resource', 'string'], true) && is_a($actual, \Traversable::class, true));
        }
        if (in_array($expected, ['array', 'bool', 'callable', 'float', 'int', 'null', 'resource', 'string'], true)
            || in_array($actual, ['array', 'bool', 'callable', 'float', 'int', 'null', 'resource', 'string'], true)
        ) {
            return false;
        }

        return is_a($actual, $expected, true);
    }

    private static function canCoerce(string $actual, string $expected): bool
    {
        if (in_array($actual, ['int', 'float'], true) && in_array($expected, ['int', 'float'], true)) {
            return true;
        }

        return !in_array($actual, ['array', 'bool', 'callable', 'float', 'int', 'iterable', 'null', 'resource', 'string'], true)
            && !in_array($expected, ['array', 'bool', 'callable', 'float', 'int', 'iterable', 'null', 'resource', 'string'], true);
    }

    private static function reflection(string $name): ReflectionFunctionAbstract
    {
        if (str_contains($name, '::')) {
            [$class, $method] = explode('::', $name, 2);

            return new ReflectionMethod($class, $method);
        }

        return new ReflectionFunction($name);
    }

    private static function callbackParameterType(ReflectionParameter $parameter): ?string
    {
        $type = $parameter->getType();
        if (!$type instanceof ReflectionNamedType || !is_a($type->getName(), CallbackInfo::class, true)) {
            return null;
        }

        return is_a($type->getName(), CallbackInfoReturnable::class, true)
            ? CallbackInfoReturnable::class
            : CallbackInfo::class;
    }

    private static function defaultReturnValue(ReflectionFunctionAbstract $reflection): mixed
    {
        $type = $reflection->getReturnType();
        if (!$type instanceof ReflectionNamedType || $type->allowsNull()) {
            return null;
        }

        return match ($type->getName()) {
            'bool' => false,
            'float' => 0.0,
            'int' => 0,
            'string' => '',
            default => null,
        };
    }

    /**
     * @param array{
     *     callbackCvs: list<int>,
     *     callbackReturnable: bool,
     *     cancellable: bool,
     *     cvMap: array<int, Operand>,
     *     returnOperand: Operand,
     *     preserveReturnOperand: bool,
     * } $context
     * @return array{body: MethodBody, preserve: array<int, bool>}
     */
    private static function lowerCallback(MethodBody $target, MethodBody $handler, array $context): array
    {
        $source = $handler->instructions();
        $preserve = [];
        $lowered = self::lowerCallbackRange($target, $source, 0, count($source) - 1, $context, $preserve);

        return [
            'body' => $handler->withInstructions(self::relocateJumps($lowered, $handler->count())),
            'preserve' => $preserve,
        ];
    }

    /**
     * @param list<Instruction> $source
     * @param array{
     *     callbackCvs: list<int>,
     *     callbackReturnable: bool,
     *     cancellable: bool,
     *     cvMap: array<int, Operand>,
     *     returnOperand: Operand,
     *     preserveReturnOperand: bool,
     * } $context
     * @param array<int, bool> $preserve
     * @return list<Instruction>
     */
    private static function lowerCallbackRange(
        MethodBody $target,
        array $source,
        int $start,
        int $end,
        array $context,
        array &$preserve,
    ): array {
        $lowered = [];
        for ($index = $start; $index <= $end; $index++) {
            $instruction = $source[$index];
            if ($instruction->name === 'RETURN') {
                if ($instruction->operand1->kind === Operand::CV && in_array($instruction->operand1->value, $context['callbackCvs'], true)) {
                    throw new InvalidArgumentException('A virtual CallbackInfo value escaped from its lowered calls');
                }
                continue;
            }
            if (in_array($instruction->name, ['RECV', 'VERIFY_RETURN_TYPE'], true)) {
                continue;
            }

            if (!self::isVirtualCallbackCall($instruction, $context['callbackCvs'])) {
                $lowered[] = $instruction;
                continue;
            }

            $callEnd = self::callbackCallEnd($source, $index);
            if ($callEnd === null || $callEnd > $end) {
                throw new InvalidArgumentException('A virtual CallbackInfo call has no DO_FCALL');
            }

            $inner = $callEnd === $index + 1
                ? []
                : self::lowerCallbackRange($target, $source, $index + 1, $callEnd - 1, $context, $preserve);
            $methodName = $instruction->operand2->value;
            if (!is_string($methodName)) {
                // @codeCoverageIgnoreStart
                throw new InvalidArgumentException('A virtual CallbackInfo method name must be a string');
                // @codeCoverageIgnoreEnd
            }
            $method = strtolower($methodName);
            $do = $source[$callEnd];
            $replacement = null;
            if ($method === 'cancel') {
                if (!$context['cancellable']) {
                    throw new InvalidArgumentException('A non-cancellable injection cannot call CallbackInfo::cancel');
                }
                $lowered = [...$lowered, ...self::withoutLastSend($inner)];
                $replacement = self::callbackReturn($target, $context, $instruction->line, $index);
                $preserve[spl_object_id($context['returnOperand'])] = true;
            } elseif ($method === 'setreturnvalue' || $method === 'setreturnvaluee') {
                if (!$context['callbackReturnable']) {
                    throw new InvalidArgumentException('setReturnValue requires CallbackInfoReturnable');
                }
                if (!$context['cancellable']) {
                    throw new InvalidArgumentException('A non-cancellable injection cannot call CallbackInfoReturnable::setReturnValue');
                }
                $value = self::lastSendOperand($inner);
                if ($value === null) {
                    throw new InvalidArgumentException('setReturnValue requires one argument');
                }
                $lowered = [...$lowered, ...self::withoutLastSend($inner)];
                $replacement = self::callbackReturnWithValue($target, $value, $instruction->line, $index);
            } elseif (in_array($method, ['iscancelled', 'iscancellable', 'getid', 'getreturnvalue'], true)) {
                if ($method === 'getreturnvalue' && !$context['callbackReturnable']) {
                    throw new InvalidArgumentException('getReturnValue requires CallbackInfoReturnable');
                }
                $value = match ($method) {
                    'iscancelled' => Operand::constant(false, 0),
                    'iscancellable' => Operand::constant($context['cancellable'], 0),
                    'getid' => Operand::constant($target->name, 0),
                    default => $context['returnOperand'],
                };
                if ($value === $context['returnOperand']) {
                    $preserve[spl_object_id($value)] = true;
                }
                $lowered = [...$lowered, ...$inner];
                if (!$do->result->isUnused()) {
                    $replacement = new Instruction(
                        Zendful::opcodeId('QM_ASSIGN'),
                        'QM_ASSIGN',
                        $do->result,
                        $value,
                        Operand::unused(),
                        0,
                        $instruction->line,
                        null,
                        $callEnd,
                    );
                }
            } else {
                throw new InvalidArgumentException(sprintf('Unsupported virtual CallbackInfo method %s', $methodName));
            }

            if ($replacement !== null) {
                $lowered[] = $replacement;
            }
            $index = $callEnd;
        }

        return $lowered;
    }

    /** @param list<int> $callbackCvs */
    private static function isVirtualCallbackCall(Instruction $instruction, array $callbackCvs): bool
    {
        return $instruction->name === 'INIT_METHOD_CALL'
            && $instruction->operand1->kind === Operand::CV
            && is_int($instruction->operand1->value)
            && in_array($instruction->operand1->value, $callbackCvs, true)
            && $instruction->operand2->kind === Operand::CONSTANT
            && is_string($instruction->operand2->value);
    }

    /** @param list<Instruction> $instructions */
    private static function lastSendOperand(array $instructions): ?Operand
    {
        for ($index = count($instructions) - 1; $index >= 0; $index--) {
            if (str_starts_with($instructions[$index]->name, 'SEND')) {
                return $instructions[$index]->operand1;
            }
        }

        return null;
    }

    /**
     * @param list<Instruction> $instructions
     * @return list<Instruction>
     */
    private static function withoutLastSend(array $instructions): array
    {
        for ($index = count($instructions) - 1; $index >= 0; $index--) {
            if (str_starts_with($instructions[$index]->name, 'SEND')) {
                array_splice($instructions, $index, 1);
                break;
            }
        }

        return $instructions;
    }

    /** @param list<Instruction> $instructions */
    private static function callbackCallEnd(array $instructions, int $start): ?int
    {
        $depth = 1;
        for ($index = $start + 1; $index < count($instructions); $index++) {
            if (self::isCallStart($instructions[$index]->name)) {
                $depth++;
                continue;
            }
            if (str_starts_with($instructions[$index]->name, 'DO_')) {
                $depth--;
                if ($depth !== 0) {
                    continue;
                }

                return $index;
            }
        }

        return null;
    }

    private static function isCallStart(string $name): bool
    {
        return in_array($name, [
            'INIT_FCALL',
            'INIT_FCALL_BY_NAME',
            'INIT_NS_FCALL_BY_NAME',
            'INIT_STATIC_METHOD_CALL',
            'INIT_METHOD_CALL',
            'INIT_DYNAMIC_CALL',
        ], true);
    }

    private static function cacheBase(MethodBody $body): int
    {
        $base = null;
        foreach ($body->instructions() as $instruction) {
            if (!in_array($instruction->name, [
                'INIT_FCALL',
                'INIT_FCALL_BY_NAME',
                'INIT_NS_FCALL_BY_NAME',
                'INIT_STATIC_METHOD_CALL',
                'INIT_METHOD_CALL',
                'INIT_DYNAMIC_CALL',
            ], true)) {
                continue;
            }
            if (!in_array($instruction->result->kind, [Operand::UNUSED, Operand::RAW], true) || !is_int($instruction->result->value)) {
                continue;
            }
            $base = $base === null ? $instruction->result->value : min($base, $instruction->result->value);
        }

        return $base ?? 0;
    }

    /** @param array{returnOperand: Operand} $context */
    private static function callbackReturn(MethodBody $target, array $context, int $line, int $originalIndex): Instruction
    {
        return new Instruction(
            Zendful::opcodeId('RETURN'),
            'RETURN',
            Operand::unused(),
            $context['returnOperand'],
            Operand::unused(),
            0,
            $line,
            null,
            $originalIndex,
        );
    }

    private static function callbackReturnWithValue(MethodBody $target, Operand $value, int $line, int $originalIndex): Instruction
    {
        return new Instruction(
            Zendful::opcodeId('RETURN'),
            'RETURN',
            Operand::unused(),
            $value,
            Operand::unused(),
            0,
            $line,
            null,
            $originalIndex,
        );
    }

    /**
     * @param list<Instruction> $instructions
     * @return list<Instruction>
     */
    private static function relocateJumps(array $instructions, int $sourceCount): array
    {
        $sourceToNew = [];
        foreach ($instructions as $newIndex => $instruction) {
            if ($instruction->originalIndex !== null) {
                $sourceToNew[$instruction->originalIndex] = $newIndex;
            }
        }
        for ($sourceIndex = $sourceCount - 1; $sourceIndex >= 0; $sourceIndex--) {
            if (!isset($sourceToNew[$sourceIndex])) {
                $sourceToNew[$sourceIndex] = $sourceToNew[$sourceIndex + 1] ?? count($instructions);
            }
        }

        $opcodeSize = 32;
        foreach ($instructions as $newIndex => $instruction) {
            if ($instruction->originalIndex === null || !str_starts_with($instruction->name, 'JMP')) {
                continue;
            }

            $operands = [$instruction->result, $instruction->operand1, $instruction->operand2];
            foreach ($operands as $operandIndex => $operand) {
                if (!in_array($operand->kind, [Operand::UNUSED, Operand::RAW], true) || !is_int($operand->value) || $operand->value === 0xffffffff) {
                    continue;
                }
                $offset = $operand->value > 0x7fffffff ? $operand->value - 0x100000000 : $operand->value;
                if ($offset % $opcodeSize !== 0) {
                    continue;
                }
                $targetSource = $instruction->originalIndex + intdiv($offset, $opcodeSize);
                if ($targetSource < 0 || $targetSource > $sourceCount) {
                    continue;
                }
                $targetIndex = $sourceToNew[$targetSource] ?? count($instructions);
                $newOffset = ($targetIndex - $newIndex) * $opcodeSize;
                $encodedOffset = $newOffset < 0 ? $newOffset + 0x100000000 : $newOffset;
                $operands[$operandIndex] = $operand->kind === Operand::UNUSED
                    ? Operand::unused($encodedOffset)
                    : Operand::raw($operand->type, $encodedOffset);
            }
            $instructions[$newIndex] = $instruction->withOperands($operands[1], $operands[2], $operands[0]);
        }

        return $instructions;
    }

    /**
     * @param list<Instruction> $original
     * @param list<Instruction> $handlerInstructions
     * @param array<int, Operand>|null $argumentReplacements
     * @return list<Instruction>
     */
    private static function replacement(
        MethodBody $body,
        MatchResult $match,
        array $original,
        array $handlerInstructions,
        ?Instruction $handlerReturn,
        ?array $argumentReplacements = null,
    ): array {
        if ($match->action === 'before') {
            return $original;
        }
        if ($match->action === 'after') {
            return $original;
        }

        if ($match->type === 'return') {
            if ($handlerReturn === null) {
                return [...$handlerInstructions, ...$original];
            }

            $target = $body->instruction($match->start);

            return [...$handlerInstructions, new Instruction(
                $handlerReturn->opcode,
                $handlerReturn->name,
                $target->result,
                $handlerReturn->operand1,
                $target->operand2,
                $target->extendedValue,
                $target->line,
                $handlerReturn->handler,
            )];
        }

        if ($match->type === 'invoke') {
            if ($argumentReplacements !== null) {
                $argument = 0;
                $rewritten = $original;
                foreach ($rewritten as $index => $instruction) {
                    if (!str_starts_with($instruction->name, 'SEND')) {
                        continue;
                    }
                    if (isset($argumentReplacements[$argument])) {
                        [$opcode, $name] = self::sendOpcode($instruction, $argumentReplacements[$argument]);
                        $rewritten[$index] = new Instruction(
                            $opcode,
                            $name,
                            Operand::unused($instruction->result->rawValue ?? (is_int($instruction->result->value) ? $instruction->result->value : 0)),
                            $argumentReplacements[$argument],
                            $instruction->operand2,
                            $instruction->extendedValue,
                            $instruction->line,
                        );
                    }
                    $argument++;
                }

                return [...$handlerInstructions, ...$rewritten];
            }
            if ($match->action === 'arg') {
                if ($handlerReturn === null || $match->argumentIndex === null) {
                    throw new InvalidArgumentException('A ModifyArg handler must return a value');
                }

                $argument = 0;
                $rewritten = $original;
                foreach ($rewritten as $index => $instruction) {
                    if (!str_starts_with($instruction->name, 'SEND')) {
                        continue;
                    }
                    if ($argument === $match->argumentIndex) {
                        [$opcode, $name] = self::sendOpcode($instruction, $handlerReturn->operand1);
                        $rewritten[$index] = new Instruction(
                            $opcode,
                            $name,
                            Operand::unused($instruction->result->rawValue ?? (is_int($instruction->result->value) ? $instruction->result->value : 0)),
                            $handlerReturn->operand1,
                            $instruction->operand2,
                            $instruction->extendedValue,
                            $instruction->line,
                        );
                        break;
                    }
                    $argument++;
                }

                return [...$handlerInstructions, ...$rewritten];
            }

            if ($handlerReturn === null) {
                throw new InvalidArgumentException('An invocation replacement handler must return a value');
            }

            $last = $body->instruction($match->end - 1);

            return [...$handlerInstructions, new Instruction(
                Zendful::opcodeId('QM_ASSIGN'),
                'QM_ASSIGN',
                $last->result,
                $handlerReturn->operand1,
                Operand::unused(),
                0,
                $last->line,
            )];
        }

        if ($match->type === 'new') {
            if ($handlerReturn === null) {
                throw new InvalidArgumentException('A constructor replacement handler must return a value');
            }

            $target = $body->instruction($match->start);

            return [...$handlerInstructions, new Instruction(
                Zendful::opcodeId('QM_ASSIGN'),
                'QM_ASSIGN',
                $target->result,
                $handlerReturn->operand1,
                Operand::unused(),
                0,
                $target->line,
            )];
        }

        if ($match->type === 'field') {
            if (in_array($match->fieldMode, ['write', 'array-write'], true)) {
                return $handlerInstructions;
            }
            if ($handlerReturn === null) {
                throw new InvalidArgumentException('A field read redirect handler must return a value');
            }

            $target = $original[0] ?? null;
            if ($target === null) {
                throw new InvalidArgumentException('A field read redirect has no target instruction');
            }

            return [...$handlerInstructions, new Instruction(
                Zendful::opcodeId('QM_ASSIGN'),
                'QM_ASSIGN',
                $target->result,
                $handlerReturn->operand1,
                Operand::unused(),
                0,
                $target->line,
            )];
        }

        if ($match->type === 'constant') {
            if ($handlerReturn === null) {
                throw new InvalidArgumentException('A constant modifier must return a value');
            }

            $target = $original[0] ?? null;
            if ($target === null) {
                throw new InvalidArgumentException('A constant modifier has no target instruction');
            }

            $operands = [$target->result, $target->operand1, $target->operand2];
            foreach ($operands as $index => $operand) {
                if ($operand->kind !== Operand::CONSTANT) {
                    continue;
                }
                $operands[$index] = $handlerReturn->operand1;
                break;
            }

            return [...$handlerInstructions, $target->withOperands($operands[1], $operands[2], $operands[0])];
        }

        if ($match->type === 'variable') {
            if ($handlerReturn === null) {
                throw new InvalidArgumentException('A variable modifier must return a value');
            }

            $target = $original[0] ?? null;
            if ($target === null) {
                throw new InvalidArgumentException('A variable modifier has no target instruction');
            }

            if ($match->variableMode === 'load') {
                $operands = [$target->operand1, $target->operand2];
                foreach ($operands as $index => $operand) {
                    if (!in_array($operand->kind, [Operand::CV, Operand::VARIABLE], true)) {
                        continue;
                    }
                    $operands[$index] = $handlerReturn->operand1;

                    return [...$handlerInstructions, $target->withOperands($operands[0], $operands[1])];
                }

                throw new InvalidArgumentException('A variable modifier has no load operand');
            }
            if ($target->name !== 'ASSIGN') {
                throw new InvalidArgumentException('A variable modifier has no assignment target');
            }

            return [...$handlerInstructions, $target->withOperands(
                $target->operand1,
                $handlerReturn->operand1,
                $target->result,
            )];
        }

        return $handlerInstructions;
    }

    /** @return array<int, Operand> */
    private static function modifierInputMap(MethodBody $target, MethodBody $handler, MatchResult $match): array
    {
        if ($match->type === 'field' && $match->fieldMode === 'read') {
            return [];
        }
        if (!in_array($match->type, ['constant', 'variable', 'invoke', 'field'], true)
            || ($match->type === 'invoke' && !in_array($match->action, ['arg', 'replace'], true))
            || ($match->type === 'field' && !in_array($match->fieldMode, ['write', 'array-read', 'array-write'], true))
            || ($match->type !== 'invoke' && $match->type !== 'field' && $match->action === 'arg')
        ) {
            return [];
        }

        if ($match->type === 'invoke' && $match->action === 'replace') {
            self::validateRedirectReturnType($target, $handler, $match);
            return self::invocationReplacementInputMap($target, $handler, $match);
        }

        if ($match->type === 'invoke' && $match->action === 'arg') {
            self::validateModifyArgTypes($target, $handler, $match);
        }

        $sourceReceives = [];
        foreach ($handler->instructions() as $instruction) {
            if ($instruction->name === 'RECV') {
                $sourceReceives[] = $instruction;
            }
        }
        $sourceReceive = $sourceReceives[0] ?? null;
        if ($sourceReceive === null && $match->type === 'field') {
            return [];
        }
        if ($sourceReceive === null || $sourceReceive->result->kind !== Operand::CV || !is_int($sourceReceive->result->value)) {
            throw new InvalidArgumentException(sprintf(
                'A %s modifier must accept the matched value as its first argument',
                $match->type,
            ));
        }

        $instruction = $target->instruction($match->start);
        if ($match->type === 'invoke') {
            $argument = 0;
            for ($index = $match->start + 1; $index < $match->end; $index++) {
                $candidate = $target->instruction($index);
                if (!str_starts_with($candidate->name, 'SEND')) {
                    continue;
                }
                if ($argument === $match->argumentIndex) {
                    return [$sourceReceive->result->value => $candidate->operand1];
                }
                $argument++;
            }

            throw new InvalidArgumentException('A ModifyArg target argument was not found');
        }

        if ($match->type === 'field') {
            if (in_array($match->fieldMode, ['array-read', 'array-write'], true)) {
                $operands = [$instruction->operand1, $instruction->operand2];
                if ($match->fieldMode === 'array-write') {
                    $opData = $target->instruction($match->start + 1);
                    if ($opData->name !== 'OP_DATA') {
                        throw new InvalidArgumentException('An array write target has no OP_DATA value');
                    }
                    $operands[] = $opData->operand1;
                }
                if (count($sourceReceives) > count($operands)) {
                    throw new InvalidArgumentException(sprintf(
                        'An array redirect handler expects at most %d argument(s)',
                        count($operands),
                    ));
                }
                $map = [];
                foreach ($sourceReceives as $index => $receive) {
                    if ($receive->result->kind !== Operand::CV || !is_int($receive->result->value)) {
                        // @codeCoverageIgnoreStart
                        throw new InvalidArgumentException('An array redirect handler receive has no CV operand');
                        // @codeCoverageIgnoreEnd
                    }
                    $map[$receive->result->value] = $operands[$index];
                }

                return $map;
            }
            $opData = $target->instruction($match->start + 1);
            if ($opData->name !== 'OP_DATA') {
                throw new InvalidArgumentException('A field write target has no OP_DATA value');
            }

            return [$sourceReceive->result->value => $opData->operand1];
        }

        if ($match->type === 'variable') {
            if ($instruction->name !== 'ASSIGN') {
                if ($match->variableMode !== 'load') {
                    throw new InvalidArgumentException('A variable modifier target is not an assignment');
                }

                foreach ([$instruction->operand1, $instruction->operand2] as $operand) {
                    if (in_array($operand->kind, [Operand::CV, Operand::VARIABLE], true)) {
                        return [$sourceReceive->result->value => $operand];
                    }
                }

                throw new InvalidArgumentException('A variable modifier target has no load operand');
            }

            if ($match->variableMode === 'load') {
                foreach ([$instruction->operand1, $instruction->operand2] as $operand) {
                    if (in_array($operand->kind, [Operand::CV, Operand::VARIABLE], true)) {
                        return [$sourceReceive->result->value => $operand];
                    }
                }

                throw new InvalidArgumentException('A variable modifier target has no load operand');
            }

            return [$sourceReceive->result->value => $instruction->operand2];
        }

        foreach ([$instruction->result, $instruction->operand1, $instruction->operand2] as $operand) {
            if ($operand->kind === Operand::CONSTANT) {
                return [$sourceReceive->result->value => $operand];
            }
        }

        throw new InvalidArgumentException('A constant modifier target has no literal operand');
    }

    /** @return array<int, Operand> */
    private static function invocationReplacementInputMap(MethodBody $target, MethodBody $handler, MatchResult $match): array
    {
        $receives = [];
        foreach ($handler->instructions() as $instruction) {
            if ($instruction->name !== 'RECV') {
                continue;
            }
            if ($instruction->result->kind !== Operand::CV || !is_int($instruction->result->value)) {
                throw new InvalidArgumentException('A Redirect handler receive has no CV operand');
            }
            $receives[] = $instruction->result->value;
        }
        if ($receives === []) {
            return [];
        }

        $init = $target->instruction($match->start);
        $arguments = [];
        if ($init->name === 'INIT_METHOD_CALL') {
            $arguments[] = $init->operand1;
        }
        for ($index = $match->start + 1; $index < $match->end; $index++) {
            $instruction = $target->instruction($index);
            if (str_starts_with($instruction->name, 'SEND')) {
                $arguments[] = $instruction->operand1;
            }
        }
        if (count($receives) > count($arguments)) {
            throw new InvalidArgumentException(sprintf(
                'A Redirect handler expects %d argument(s), but the target invocation provides %d; the extra argument cannot be captured',
                count($receives),
                count($arguments),
            ));
        }

        self::validateRedirectParameterTypes($target, $handler, $match, count($receives));

        $map = [];
        foreach ($receives as $index => $receive) {
            $map[$receive] = $arguments[$index];
        }

        return $map;
    }

    private static function validateRedirectParameterTypes(MethodBody $target, MethodBody $handler, MatchResult $match, int $count): void
    {
        $invocation = $match->invocation;
        if (!$invocation instanceof InvocationSpec) {
            return;
        }

        $reflection = self::invocationReflection($target, $invocation);
        if ($reflection === null) {
            return;
        }

        $handlerParameters = self::reflection($handler->name)->getParameters();
        $targetParameters = $reflection->getParameters();
        $receiverOffset = $invocation->kind === InvocationSpec::MEMBER ? 1 : 0;
        $methodCoerce = self::reflection($handler->name)->getAttributes(Coerce::class) !== [];
        for ($index = 0; $index < $count; $index++) {
            $targetParameter = $targetParameters[$index - $receiverOffset] ?? null;
            $handlerParameter = $handlerParameters[$index] ?? null;
            if ($targetParameter instanceof ReflectionParameter && $handlerParameter instanceof ReflectionParameter) {
                self::validateCallbackParameterType($handlerParameter, $targetParameter, $handler->name, $methodCoerce);
            }
        }
    }

    private static function validateModifyArgTypes(MethodBody $target, MethodBody $handler, MatchResult $match): void
    {
        $invocation = $match->invocation;
        $argumentIndex = $match->argumentIndex;
        if (!$invocation instanceof InvocationSpec || $argumentIndex === null) {
            return;
        }

        $reflection = self::invocationReflection($target, $invocation);
        if ($reflection === null) {
            return;
        }

        $targetParameter = $reflection->getParameters()[$argumentIndex] ?? null;
        $handlerReflection = self::reflection($handler->name);
        $handlerParameter = $handlerReflection->getParameters()[0] ?? null;
        if ($targetParameter instanceof ReflectionParameter && $handlerParameter instanceof ReflectionParameter) {
            self::validateCallbackParameterType(
                $handlerParameter,
                $targetParameter,
                $handler->name,
                $handlerReflection->getAttributes(Coerce::class) !== [],
            );
        }

        $handlerType = $handlerReflection->getReturnType();
        $targetType = $targetParameter instanceof ReflectionParameter ? $targetParameter->getType() : null;
        $targetParameterName = $targetParameter instanceof ReflectionParameter
            ? $targetParameter->getName()
            : (string) $argumentIndex;
        if (!$handlerType instanceof ReflectionNamedType || !$targetType instanceof ReflectionNamedType) {
            return;
        }
        if (!$targetType->allowsNull() && $handlerType->allowsNull()) {
            throw new InvalidArgumentException(sprintf(
                'ModifyArg handler %s may return null for non-null target argument $%s',
                $handler->name,
                $targetParameterName,
            ));
        }

        $expected = $targetType->getName();
        $actual = $handlerType->getName();
        if (self::typeAccepts($expected, $actual)) {
            return;
        }
        if (($handlerReflection->getAttributes(Coerce::class) !== []
                || ($handlerParameter instanceof ReflectionParameter && $handlerParameter->getAttributes(Coerce::class) !== []))
            && self::canCoerce($actual, $expected)
        ) {
            return;
        }

        throw new InvalidArgumentException(sprintf(
            'ModifyArg handler %s returns %s, but target argument $%s requires %s; add #[Coerce] for a compatible coercion',
            $handler->name,
            $actual,
            $targetParameterName,
            $expected,
        ));
    }

    private static function validateRedirectReturnType(MethodBody $target, MethodBody $handler, MatchResult $match): void
    {
        $invocation = $match->invocation;
        if (!$invocation instanceof InvocationSpec) {
            return;
        }

        $reflection = self::invocationReflection($target, $invocation);
        if ($reflection === null) {
            return;
        }

        $handlerReflection = self::reflection($handler->name);
        $handlerType = $handlerReflection->getReturnType();
        $targetType = $reflection->getReturnType();
        if (!$handlerType instanceof ReflectionNamedType || !$targetType instanceof ReflectionNamedType) {
            return;
        }
        if ($targetType->allowsNull() && !$handlerType->allowsNull()) {
            throw new InvalidArgumentException(sprintf(
                'Redirect handler %s returns a non-nullable value for nullable invocation %s',
                $handler->name,
                $invocation->name,
            ));
        }

        $expected = $targetType->getName();
        $actual = $handlerType->getName();
        if (self::typeAccepts($expected, $actual)) {
            return;
        }
        if ($handlerReflection->getAttributes(Coerce::class) !== [] && self::canCoerce($actual, $expected)) {
            return;
        }

        throw new InvalidArgumentException(sprintf(
            'Redirect handler %s returns %s, but invocation %s provides %s; add #[Coerce] for a compatible coercion',
            $handler->name,
            $actual,
            $invocation->name,
            $expected,
        ));
    }

    private static function invocationReflection(MethodBody $target, InvocationSpec $invocation): ?ReflectionFunctionAbstract
    {
        try {
            if ($invocation->kind === InvocationSpec::FUNCTION) {
                return new ReflectionFunction($invocation->name);
            }

            $class = $invocation->class;
            if ($class === null && str_contains($target->name, '::')) {
                [$class] = explode('::', $target->name, 2);
            }
            if ($class === null || $invocation->kind === InvocationSpec::MEMBER) {
                return null;
            }

            return new ReflectionMethod($class, $invocation->name);
        } catch (\ReflectionException) {
            return null;
        }
    }

    /**
     * @param array<int, Operand> $cvMap
     * @param list<int> $callbackCvs
     * @param array<int, bool> $preserve
     */
    private static function detach(
        Instruction $instruction,
        int $temporaryOffset,
        int $cacheOffset,
        array $cvMap = [],
        array $callbackCvs = [],
        array $preserve = [],
        int $sourceCacheBase = 0,
    ): Instruction {
        $hasCacheResult = in_array($instruction->name, [
            'INIT_FCALL',
            'INIT_FCALL_BY_NAME',
            'INIT_NS_FCALL_BY_NAME',
            'INIT_STATIC_METHOD_CALL',
            'INIT_METHOD_CALL',
            'INIT_DYNAMIC_CALL',
        ], true);

        return new Instruction(
            $instruction->opcode,
            $instruction->name,
            self::detachOperand($instruction->result, $temporaryOffset, $hasCacheResult ? $cacheOffset : 0, $hasCacheResult, $cvMap, $callbackCvs, $preserve, $sourceCacheBase),
            self::detachOperand($instruction->operand1, $temporaryOffset, 0, false, $cvMap, $callbackCvs, $preserve),
            self::detachOperand($instruction->operand2, $temporaryOffset, 0, false, $cvMap, $callbackCvs, $preserve),
            $instruction->extendedValue,
            $instruction->line,
            null,
        );
    }

    /**
     * @param array<int, Operand> $cvMap
     * @param list<int> $callbackCvs
     * @param array<int, bool> $preserve
     */
    private static function detachOperand(
        Operand $operand,
        int $temporaryOffset,
        int $cacheOffset,
        bool $cacheOperand,
        array $cvMap = [],
        array $callbackCvs = [],
        array $preserve = [],
        int $sourceCacheBase = 0,
    ): Operand {
        if (isset($preserve[spl_object_id($operand)])) {
            return $operand;
        }
        if ($cacheOperand && in_array($operand->kind, [Operand::UNUSED, Operand::RAW], true) && is_int($operand->value)) {
            return $operand->kind === Operand::UNUSED
                ? Operand::unused($operand->value - $sourceCacheBase + $cacheOffset)
                : Operand::raw($operand->type, $operand->value - $sourceCacheBase + $cacheOffset);
        }
        if ($operand->kind === Operand::CONSTANT) {
            return $operand->withValue($operand->value);
        }
        if ($operand->kind === Operand::CV && is_int($operand->value)) {
            if (in_array($operand->value, $callbackCvs, true)) {
                throw new InvalidArgumentException('A virtual CallbackInfo value escaped from its lowered calls');
            }

            return $cvMap[$operand->value] ?? $operand;
        }
        if (in_array($operand->kind, [Operand::TEMPORARY, Operand::VARIABLE], true) && is_int($operand->value)) {
            $slot = intdiv($operand->value, 16) - 5 + $temporaryOffset;
            $value = ($slot + 5) * 16;

            return $operand->kind === Operand::TEMPORARY ? Operand::temporary($value) : Operand::variable($value);
        }

        return $operand;
    }
}
