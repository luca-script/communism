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
 * File: Matcher.php                                                          *
 * Consumer: Internal                                                         *
 * Purpose: Source file for Matcher.php.                                      *
 *============================================================================*/

declare(strict_types=1);

namespace Communism\Internals\Needle;

use Communism\Mixin\At;
use Communism\Mixin\Desc;
use Communism\Mixin\Slice;
use InvalidArgumentException;
use ReflectionFunction;
use ReflectionMethod;

use function in_array;
use function is_array;
use function is_int;
use function is_string;
use function sprintf;
use function str_ends_with;
use function str_starts_with;
use function strtolower;
use function strtoupper;

/** Resolves Mixin-style injection points against a decompiled method body. */
final class Matcher
{
    /** @var array<string, callable(MethodBody, At): array<int, mixed>> */
    private static array $injectionPoints = [];

    /**
     * Register a Mixin-style custom injection point.
     *
     * The resolver receives the decompiled body and its At declaration and
     * must return concrete MatchResult objects. Registration is intentionally
     * kept in Needle: custom points are a bytecode-level extension mechanism.
     *
     * @param callable(MethodBody, At): array<int, mixed> $resolver
     */
    public static function registerInjectionPoint(string $name, callable $resolver): void
    {
        $normalized = strtoupper($name);
        if ($normalized === '' || !str_starts_with($normalized, '_')) {
            throw new InvalidArgumentException(sprintf('Custom injection point %s must start with _', $name));
        }
        if (self::isBuiltIn($normalized)) {
            // @codeCoverageIgnoreStart
            throw new InvalidArgumentException(sprintf('Cannot register built-in injection point %s', $name));
            // @codeCoverageIgnoreEnd
        }

        self::$injectionPoints[$normalized] = $resolver;
    }

    public static function unregisterInjectionPoint(string $name): void
    {
        unset(self::$injectionPoints[strtoupper($name)]);
    }

    public static function validateAt(At $at): void
    {
        $type = $at->type();
        $action = $at->action();
        if (strtoupper($at->shift) === 'BY' && !in_array($action, ['before', 'after'], true)) {
            throw new InvalidArgumentException('At BY shifting only supports before and after callbacks');
        }
        if ($at->opcode !== '' && !in_array($type, ['FIELD', 'JUMP'], true)) {
            throw new InvalidArgumentException(sprintf('%s does not support an opcode filter', $type));
        }
        if ($type === 'JUMP' && $at->target !== '' && $at->opcode !== '') {
            throw new InvalidArgumentException('JUMP accepts either target or opcode, not both');
        }

        switch ($type) {
            case 'HEAD':
            case 'CONSTRUCTOR_HEAD':
            case 'TAIL':
            case 'RETURN':
                if ($at->target !== '') {
                    throw new InvalidArgumentException(sprintf('%s does not accept a target', $type));
                }
                if (in_array($type, ['HEAD', 'CONSTRUCTOR_HEAD'], true) && $action !== 'before') {
                    throw new InvalidArgumentException(sprintf('%s only supports the before action', $type));
                }
                if ($type !== 'HEAD' && !in_array($action, ['before', 'modify', 'replace'], true)) {
                    throw new InvalidArgumentException(sprintf('%s does not support the %s action', $type, $action));
                }
                return;

            case 'INVOKE':
                $target = $at->target;
                if (!is_string($target) && !is_array($target) && !$target instanceof Desc) {
                    throw new InvalidArgumentException('INVOKE expects an invocation target specification');
                }
                if (!in_array($action, ['before', 'after', 'replace'], true)) {
                    throw new InvalidArgumentException(sprintf('INVOKE does not support the %s action', $action));
                }
                InvocationSpec::parse($target);
                return;

            case 'INVOKE_ASSIGN':
                $target = $at->target;
                if (!is_string($target) && !is_array($target) && !$target instanceof Desc) {
                    throw new InvalidArgumentException('INVOKE_ASSIGN expects an invocation target specification');
                }
                self::validateAction($type, $action, ['after']);
                InvocationSpec::parse($target);
                return;

            case 'NEW':
                if ($at->target !== '' && !is_string($at->target)) {
                    throw new InvalidArgumentException('NEW expects an optional class name target');
                }
                self::validateAction($type, $action, ['before', 'replace']);
                return;

            case 'JUMP':
                if ($at->target !== '' && !is_string($at->target)) {
                    throw new InvalidArgumentException('JUMP expects an optional opcode or branch-kind target');
                }
                self::validateAction($type, $action, ['before']);
                return;

            case 'FIELD':
                if (!is_string($at->target) && !is_array($at->target)) {
                    throw new InvalidArgumentException('FIELD expects a member name target');
                }
                self::fieldTargets($at->target);
                self::validateAction($type, $action, ['replace']);
                return;

            case 'STORE':
            case 'LOAD':
                if ($at->target !== '' && !is_string($at->target)) {
                    throw new InvalidArgumentException('STORE expects an optional local variable name');
                }
                self::validateAction($type, $action, ['replace']);
                return;

            case 'CONSTANT':
                self::validateAction($type, $action, ['replace']);
                return;

            case 'THROW':
                if ($at->target !== '' && !is_string($at->target)) {
                    throw new InvalidArgumentException('THROW expects an optional exception class name');
                }
                self::validateAction($type, $action, ['replace']);
                return;

            default:
                if (isset(self::$injectionPoints[$type])) {
                    return;
                }
                throw new InvalidArgumentException(sprintf('Unknown Mixin injection point %s', $type));
        }
    }

    /** @param list<string> $allowed */
    private static function validateAction(string $type, string $action, array $allowed): void
    {
        if (!in_array($action, $allowed, true)) {
            throw new InvalidArgumentException(sprintf('%s does not support the %s action', $type, $action));
        }
    }

    /** @return list<MatchResult> */
    public static function find(
        MethodBody $body,
        At $at,
        ?int $argumentIndex = null,
        ?Slice $slice = null,
        ?string $constantType = null,
        ?int $variableIndex = null,
        ?string $variableType = null,
        bool $constantNull = false,
        bool $variableArgsOnly = false,
    ): array {
        self::validateAt($at);
        if ($argumentIndex !== null && ($argumentIndex < 0 || $at->type() !== 'INVOKE')) {
            throw new InvalidArgumentException('An argument index requires an INVOKE injection point');
        }
        if ($argumentIndex !== null && strtoupper($at->shift) === 'BY') {
            throw new InvalidArgumentException('ModifyArg does not support At BY shifting');
        }
        if ($constantType !== null && $at->type() !== 'CONSTANT') {
            throw new InvalidArgumentException('A constant type discriminator requires a CONSTANT point');
        }

        $matches = self::findUnbounded($body, $at, $argumentIndex, $constantType, $variableIndex, $variableType, $constantNull, $variableArgsOnly);
        $matches = self::selectOrdinal($matches, $at->ordinal);
        $matches = self::shift($body, $at, $matches);
        if ($slice !== null) {
            [$from, $to] = self::sliceBounds($body, $slice);
            $filtered = [];
            foreach ($matches as $match) {
                if ($match->start >= $from && $match->end <= $to) {
                    $filtered[] = $match;
                }
            }
            $matches = $filtered;
        }

        return $matches;
    }

    /** @return list<MatchResult> */
    private static function findUnbounded(
        MethodBody $body,
        At $at,
        ?int $argumentIndex,
        ?string $constantType,
        ?int $variableIndex = null,
        ?string $variableType = null,
        bool $constantNull = false,
        bool $variableArgsOnly = false,
    ): array {
        self::validateAt($at);
        $type = match ($at->type()) {
            'HEAD' => 'begin',
            'CONSTRUCTOR_HEAD' => 'constructor_head',
            'TAIL', 'RETURN' => 'return',
            'FIELD' => 'field',
            'STORE', 'LOAD' => 'variable',
            default => strtolower($at->type()),
        };
        $action = $argumentIndex === null ? $at->action() : 'arg';
        $variableMode = $at->type() === 'LOAD' ? 'load' : 'store';

        if ($type === 'begin') {
            $start = 0;
            while ($start < $body->count() && $body->instruction($start)->name === 'RECV') {
                $start++;
            }

            return [new MatchResult($start, $start, $type, $action)];
        }

        if ($type === 'constructor_head') {
            if (!str_ends_with($body->name, '::__construct')) {
                return [];
            }

            $start = 0;
            while ($start < $body->count() && $body->instruction($start)->name === 'RECV') {
                $start++;
            }

            // PHP emits parent::__construct() as the first static call in a
            // derived constructor. The class and method are cached operands,
            // so their source names are not available as literal operands.
            $parentCall = $start < $body->count() ? $body->instruction($start) : null;
            if ($parentCall instanceof Instruction
                && $parentCall->name === 'INIT_STATIC_METHOD_CALL'
                && $parentCall->operand1->kind === Operand::UNUSED
                && is_int($parentCall->operand1->value)
                && ($parentCall->operand1->value & 0x0f) === 2
            ) {
                $end = self::invocationEnd($body, $start);
                if ($end !== null) {
                    $start = $end + 1;
                }
            }

            return [new MatchResult($start, $start, $type, $action)];
        }

        $customResolver = self::$injectionPoints[$at->type()] ?? null;
        if ($customResolver !== null) {
            $matches = $customResolver($body, $at);
            $validMatches = [];
            foreach ($matches as $match) {
                if (!$match instanceof MatchResult || $match->start < 0 || $match->end < $match->start || $match->end > $body->count()) {
                    throw new InvalidArgumentException(sprintf(
                        'Custom injection point %s returned an invalid match in %s',
                        $at->type(),
                        $body->name,
                    ));
                }
                $validMatches[] = $match;
            }

            return $validMatches;
        }


        if (in_array($type, ['invoke', 'invoke_assign'], true)) {
            $target = $at->target;
            if ($at->referenceMap !== null && (is_string($target) || is_array($target) || $target instanceof Desc)) {
                $target = $at->referenceMap->invocation($target);
            }
            if (!is_string($target) && !is_array($target) && !$target instanceof Desc) {
                // @codeCoverageIgnoreStart
                throw new InvalidArgumentException('INVOKE expects an invocation target specification');
                // @codeCoverageIgnoreEnd
            }
            $spec = InvocationSpec::parse($target);
            $matches = [];
            $coveredUntil = -1;
            foreach ($body->instructions() as $index => $instruction) {
                if ($index <= $coveredUntil) {
                    continue;
                }
                if (!self::isInvocationStart($instruction->name)) {
                    continue;
                }
                if (self::isFramelessFallback($body, $index)) {
                    continue;
                }

                $end = self::invocationEnd($body, $index);
                if ($end === null || !self::matchesInvocation($body, $index, $end, $spec)) {
                    continue;
                }

                $matchEnd = $end + 1;
                $coveredUntil = $matchEnd - 1;
                if ($type === 'invoke_assign') {
                    $assignment = $body->instructions()[$matchEnd] ?? null;
                    $call = $body->instruction($end);
                    if (!$assignment instanceof Instruction || !self::matchesInvocationAssignment($assignment, $call)) {
                        continue;
                    }
                    $matchEnd++;
                }

                if ($argumentIndex !== null && self::invocationArguments($body, $index, $end) <= $argumentIndex) {
                    continue;
                }
                $matches[] = new MatchResult($index, $matchEnd, $type, $action, $spec, $argumentIndex);
            }

            if (!$spec->hasMinimumMatches(count($matches))) {
                throw new InvalidArgumentException(sprintf(
                    'Invocation selector %s matched %d target(s), outside its quantifier bounds',
                    $at->description(),
                    count($matches),
                ));
            }
            if ($spec->maxMatches !== null && count($matches) > $spec->maxMatches) {
                $matches = array_slice($matches, 0, $spec->maxMatches);
            }

            return $matches;
        }

        if ($type === 'new') {
            $matches = [];
            foreach ($body->instructions() as $index => $instruction) {
                if ($instruction->name !== 'NEW') {
                    continue;
                }

                if ($at->target !== ''
                    && ($instruction->operand1->kind !== Operand::CONSTANT || $instruction->operand1->value !== $at->target)
                ) {
                    continue;
                }

                $end = self::constructorEnd($body, $index);
                if ($end === null) {
                    continue;
                }

                $matches[] = new MatchResult($index, $end + 1, $type, $action);
            }

            return $matches;
        }

        if ($type === 'jump') {
            $matches = [];
            foreach ($body->instructions() as $index => $instruction) {
                if (!self::matchesJump($instruction, $at->opcode !== '' ? $at->opcode : $at->target)) {
                    continue;
                }

                $matches[] = new MatchResult($index, $index + 1, $type, $action);
            }

            return $matches;
        }

        $matches = [];
        foreach ($body->instructions() as $index => $instruction) {
            if ($type === 'return' && $instruction->name === 'RETURN') {
                $matches[] = new MatchResult($index, $index + 1, $type, $action);
            } elseif ($type === 'field') {
                $fieldMode = self::fieldMode($instruction);
                $target = $at->target;
                if ($at->referenceMap !== null && (is_string($target) || is_array($target))) {
                    $target = $at->referenceMap->fieldTarget($target);
                }
                if (self::matchesField($body, $instruction, $target, $fieldMode, $at->opcode)) {
                    $matches[] = new MatchResult(
                        $index,
                        $index + (in_array($fieldMode, ['write', 'array-write'], true) ? self::assignmentLength($body, $index) : 1),
                        $type,
                        $action,
                        null,
                        null,
                        'store',
                        $fieldMode,
                    );
                }
            } elseif ($type === 'throw' && self::matchesThrow($body, $index, $at->target)) {
                $matches[] = new MatchResult($index, $index + 1, $type, $action);
            } elseif ($type === 'constant' && self::matchesConstant($instruction, $at->target, $constantType, $constantNull)) {
                $matches[] = new MatchResult($index, $index + 1, $type, $action);
            } elseif ($type === 'variable' && self::matchesVariable($body, $index, $instruction, $at->target, $variableMode, $variableIndex, $variableType, $variableArgsOnly)) {
                $matches[] = new MatchResult($index, $index + 1, $type, $action, null, null, $variableMode);
            }
        }

        return $matches;
    }

    private static function matchesInvocationAssignment(Instruction $assignment, Instruction $call): bool
    {
        return $assignment->name === 'ASSIGN'
            && in_array($call->result->kind, [Operand::VARIABLE, Operand::TEMPORARY], true)
            && $assignment->operand2->kind === $call->result->kind
            && $assignment->operand2->value === $call->result->value;
    }

    private static function matchesJump(Instruction $instruction, mixed $target): bool
    {
        $jumps = ['JMP', 'JMPZ', 'JMPNZ', 'JMPZ_EX', 'JMPNZ_EX', 'JMPZNZ'];
        if (!in_array($instruction->name, $jumps, true)) {
            return false;
        }
        if ($target === '') {
            return true;
        }

        if (!is_string($target)) {
            return false;
        }

        $target = strtoupper($target);
        if ($target === 'CONDITIONAL') {
            return $instruction->name !== 'JMP';
        }
        if ($target === 'UNCONDITIONAL') {
            return $instruction->name === 'JMP';
        }

        return $instruction->name === $target;
    }

    /**
     * @param list<MatchResult> $matches
     * @return list<MatchResult>
     */
    private static function selectOrdinal(array $matches, int $ordinal): array
    {
        if ($ordinal < 0) {
            return $matches;
        }

        return isset($matches[$ordinal]) ? [$matches[$ordinal]] : [];
    }

    /**
     * @param list<MatchResult> $matches
     * @return list<MatchResult>
     */
    private static function shift(MethodBody $body, At $at, array $matches): array
    {
        if (strtoupper($at->shift) !== 'BY') {
            return $matches;
        }

        $shifted = [];
        foreach ($matches as $match) {
            $position = $match->start + $at->by;
            if ($position > $body->count()) {
                throw new InvalidArgumentException(sprintf(
                    'At BY shift moves %s outside %s',
                    $at->description(),
                    $body->name,
                ));
            }
            $shifted[] = new MatchResult($position, $position, $match->type, $match->action, $match->invocation, $match->argumentIndex, $match->variableMode, $match->fieldMode);
        }

        return $shifted;
    }

    /** @return array{0: int, 1: int} */
    private static function sliceBounds(MethodBody $body, Slice $slice): array
    {
        $from = 0;
        if ($slice->from !== null) {
            $fromMatches = self::find($body, $slice->from);
            if ($fromMatches === []) {
                throw new InvalidArgumentException(sprintf('Slice from point did not match in %s', $body->name));
            }
            $from = $fromMatches[0]->start;
        }

        $to = $body->count();
        if ($slice->to !== null) {
            $toMatches = self::find($body, $slice->to);
            if ($toMatches === []) {
                throw new InvalidArgumentException(sprintf('Slice to point did not match in %s', $body->name));
            }
            $to = $toMatches[0]->start;
        }
        if ($from > $to) {
            throw new InvalidArgumentException(sprintf('Slice bounds are reversed in %s', $body->name));
        }

        return [$from, $to];
    }

    private static function validateAssignmentTarget(string $target): void
    {
        if ($target === '[]') {
            return;
        }
        if ($target === '' || str_ends_with($target, '::') || str_starts_with($target, '$')) {
            throw new InvalidArgumentException('A field target must be "::MEMBERNAME" or a local variable name');
        }
        if (str_starts_with($target, '::') && strlen($target) === 2) {
            // @codeCoverageIgnoreStart
            throw new InvalidArgumentException('A field member name must not be empty');
            // @codeCoverageIgnoreEnd
        }
        if (str_starts_with($target, '::')) {
            [, $descriptor] = self::fieldTarget($target);
            if ($descriptor === '') {
                throw new InvalidArgumentException('A field descriptor must not be empty');
            }
        }
    }

    private static function fieldMode(Instruction $instruction): string
    {
        return match ($instruction->name) {
            'ASSIGN_OBJ' => 'write',
            'ASSIGN_DIM' => 'array-write',
            'FETCH_DIM_R', 'FETCH_DIM_IS', 'FETCH_DIM_W' => 'array-read',
            default => 'read',
        };
    }

    private static function matchesField(
        MethodBody|Instruction $bodyOrInstruction,
        mixed $instructionOrTarget,
        mixed $targetOrMode,
        string $modeOrOpcode = '',
        string $opcode = '',
    ): bool {
        $body = $bodyOrInstruction instanceof MethodBody ? $bodyOrInstruction : null;
        $instruction = $bodyOrInstruction instanceof Instruction ? $bodyOrInstruction : $instructionOrTarget;
        $target = $bodyOrInstruction instanceof Instruction ? $instructionOrTarget : $targetOrMode;
        $mode = $bodyOrInstruction instanceof Instruction ? $targetOrMode : $modeOrOpcode;
        $opcode = $bodyOrInstruction instanceof Instruction ? $modeOrOpcode : $opcode;
        if (!$instruction instanceof Instruction || !is_string($mode)) {
            return false;
        }
        if (is_array($target)) {
            if ($body === null) {
                return false;
            }
            foreach (self::fieldTargets($target) as $candidate) {
                if (self::matchesField($body, $instruction, $candidate, $mode, $opcode)) {
                    return true;
                }
            }

            return false;
        }
        if ($opcode !== '') {
            $opcode = strtoupper($opcode);
            $expectedMode = match ($opcode) {
                'READ', 'GETFIELD' => 'read',
                'WRITE', 'PUTFIELD' => 'write',
                'ARRAY_READ' => 'array-read',
                'ARRAY_WRITE' => 'array-write',
                default => null,
            };
            if (($expectedMode !== null && $expectedMode !== $mode) || ($expectedMode === null && $instruction->name !== $opcode)) {
                return false;
            }
        }
        if ($target === '[]') {
            return in_array($mode, ['array-read', 'array-write'], true);
        }
        if (!is_string($target) || !str_starts_with($target, '::')) {
            return false;
        }
        [$member, $descriptor] = self::fieldTarget($target);
        if ($descriptor !== null && ($body === null || !self::matchesFieldDescriptor($body, $member, $descriptor))) {
            return false;
        }
        if ($mode === 'write') {
            return $instruction->name === 'ASSIGN_OBJ'
                && $instruction->operand2->kind === Operand::CONSTANT
                && $instruction->operand2->value === $member;
        }

        return in_array($instruction->name, ['FETCH_OBJ_R', 'FETCH_OBJ_W', 'FETCH_OBJ_IS'], true)
            && $instruction->operand2->kind === Operand::CONSTANT
            && $instruction->operand2->value === $member;
    }

    /** @return list<string> */
    private static function fieldTargets(mixed $target): array
    {
        if (is_string($target)) {
            self::validateAssignmentTarget($target);

            return [$target];
        }
        if (!is_array($target) || !is_string($target[0] ?? null) || count($target) > 2) {
            throw new InvalidArgumentException('FIELD aliases must start with a member target');
        }

        $targets = [$target[0]];
        self::validateAssignmentTarget($target[0]);
        $extension = $target[1] ?? null;
        if ($extension === null) {
            return $targets;
        }
        if (!is_array($extension) || count($extension) !== 1 || !array_key_exists('aliases', $extension)) {
            throw new InvalidArgumentException('Unknown FIELD selector extension; expected ["aliases" => NAME_LIST]');
        }
        $aliases = $extension['aliases'];
        if (!is_array($aliases) || !array_is_list($aliases) || $aliases === []) {
            throw new InvalidArgumentException('FIELD ALIASES must be a non-empty list of member targets');
        }
        foreach ($aliases as $alias) {
            if (!is_string($alias)) {
                throw new InvalidArgumentException('FIELD ALIASES must contain member targets');
            }
            self::validateAssignmentTarget($alias);
            $targets[] = $alias;
        }

        return $targets;
    }

    /** @return array{string, ?string} */
    private static function fieldTarget(string $target): array
    {
        $target = substr($target, 2);
        $separator = strpos($target, ':');
        if ($separator === false) {
            return [$target, null];
        }

        return [substr($target, 0, $separator), substr($target, $separator + 1)];
    }

    private static function matchesFieldDescriptor(MethodBody $body, string $member, string $descriptor): bool
    {
        if (!str_contains($body->name, '::')) {
            return false;
        }

        [$class] = explode('::', $body->name, 2);
        if (!class_exists($class)) {
            return false;
        }
        try {
            $property = new \ReflectionProperty($class, $member);
        } catch (\ReflectionException) {
            return false;
        }
        $type = $property->getType();
        if (!$type instanceof \ReflectionNamedType) {
            return false;
        }

        $actual = strtolower($type->getName());
        $descriptor = strtolower($descriptor);
        $descriptor = str_replace('/', '\\', $descriptor);

        return $actual === $descriptor;
    }

    private static function assignmentLength(MethodBody $body, int $index): int
    {
        return (in_array($body->instruction($index)->name, ['ASSIGN_OBJ', 'ASSIGN_DIM'], true)
            && isset($body->instructions()[$index + 1])
            && $body->instruction($index + 1)->name === 'OP_DATA') ? 2 : 1;
    }

    private static function matchesThrow(MethodBody $body, int $index, mixed $type): bool
    {
        $instruction = $body->instruction($index);
        if ($instruction->name !== 'THROW') {
            return false;
        }
        if ($type === '') {
            return true;
        }
        for ($cursor = $index - 1; $cursor >= 0 && $cursor >= $index - 4; $cursor--) {
            $candidate = $body->instruction($cursor);
            if (($candidate->operand1->kind === Operand::CONSTANT && $candidate->operand1->value === $type)
                || ($candidate->operand2->kind === Operand::CONSTANT && $candidate->operand2->value === $type)) {
                return true;
            }
        }

        return false;
    }

    private static function matchesConstant(Instruction $instruction, mixed $target, ?string $type, bool $nullValue = false): bool
    {
        foreach ([$instruction->result, $instruction->operand1, $instruction->operand2] as $operand) {
            if ($operand->kind === Operand::CONSTANT
                && (($nullValue && $operand->value === null) || (!$nullValue && ($target === '' || $operand->value === $target)))
                && self::matchesConstantType($operand->value, $type)
            ) {
                return true;
            }
        }

        return false;
    }

    private static function matchesVariable(
        MethodBody $body,
        int|Instruction $indexOrInstruction,
        Instruction|string $instructionOrName,
        mixed $nameOrMode,
        string|int|null $modeOrIndex,
        ?int $variableIndex = null,
        ?string $variableType = null,
        bool $variableArgsOnly = false,
    ): bool {
        $legacy = $indexOrInstruction instanceof Instruction;
        $instructionIndex = $legacy ? 0 : $indexOrInstruction;
        $instruction = $legacy ? $indexOrInstruction : $instructionOrName;
        $name = $legacy ? $instructionOrName : $nameOrMode;
        $mode = $legacy ? $nameOrMode : $modeOrIndex;
        $variableIndex = $legacy ? (is_int($modeOrIndex) ? $modeOrIndex : null) : $variableIndex;
        if (!$instruction instanceof Instruction || !is_string($name) || !is_string($mode)) {
            return false;
        }
        $matchesOperand = static function (Operand $operand) use ($body, $instructionIndex, $name, $variableIndex, $variableType, $variableArgsOnly): bool {
            if (!in_array($operand->kind, [Operand::CV, Operand::VARIABLE], true)) {
                return false;
            }
            if ($variableIndex !== null && $body->variableIndex($operand) !== $variableIndex) {
                return false;
            }
            if ($variableType !== null && !self::matchesVariableTypeAt($body, $operand, $variableType, $instructionIndex)) {
                return false;
            }
            if ($variableArgsOnly && !self::isArgumentVariable($body, $operand)) {
                return false;
            }

            return $name === '' || $body->variableName($operand) === $name;
        };

        if ($mode === 'store') {
            if ($instruction->name !== 'ASSIGN') {
                return false;
            }

            if ($matchesOperand($instruction->result)) {
                return true;
            }
            // PHP 8.4 represents an ordinary CV assignment as
            // ASSIGN <target>, <value>. The target has no declared type of
            // its own, so a typed ModifyVariable may use the value operand's
            // known type (for example, $local = $typedParameter).
            if ($instruction->operand1->kind === Operand::CV
                && ($name === '' || $body->variableName($instruction->operand1) === $name)
                && ($variableIndex === null || $body->variableIndex($instruction->operand1) === $variableIndex)
                && (!$variableArgsOnly || self::isArgumentVariable($body, $instruction->operand1))
            ) {
                return $variableType === null || self::matchesValueType($body, $instruction->operand2, $variableType);
            }

            return false;
        }

        if ($instruction->name === 'ASSIGN' || str_starts_with($instruction->name, 'RECV')) {
            return false;
        }
        // Return-type verification consumes a CV as metadata, not as a
        // value load. Treating it as LOAD would rewrite the verifier and can
        // leave the actual RETURN with an uninitialised result.
        if ($instruction->name === 'VERIFY_RETURN_TYPE') {
            return false;
        }
        foreach ([$instruction->operand1, $instruction->operand2] as $operand) {
            if (!in_array($operand->kind, [Operand::CV, Operand::VARIABLE], true)) {
                continue;
            }
            if ($matchesOperand($operand)) {
                return true;
            }
        }

        return false;
    }

    private static function isArgumentVariable(MethodBody $body, Operand $operand): bool
    {
        foreach ($body->instructions() as $instruction) {
            if (str_starts_with($instruction->name, 'RECV')
                && $instruction->result->kind === $operand->kind
                && $instruction->result->value === $operand->value
            ) {
                return true;
            }
        }

        return false;
    }

    private static function matchesVariableType(MethodBody $body, Operand $operand, string $type): bool
    {
        $actual = $body->variableType($operand);
        if ($actual === null || $type === 'mixed') {
            // Zend bytecode does not retain an inferred type for every local.
            // Unknown locals remain eligible; known declarations and literal
            // assignments are filtered strictly below.
            return true;
        }
        if ($type === 'object') {
            return $actual === 'object' || !in_array($actual, ['int', 'float', 'string', 'bool', 'array', 'null'], true);
        }

        return $actual === $type;
    }

    private static function matchesVariableTypeAt(MethodBody $body, Operand $operand, string $type, int $before): bool
    {
        $actual = self::inferredVariableType($body, $operand, $before);
        if ($actual === null || $type === 'mixed') {
            return true;
        }
        if ($type === 'object') {
            return $actual === 'object' || !in_array($actual, ['int', 'float', 'string', 'bool', 'array', 'null'], true);
        }

        return $actual === $type;
    }

    private static function inferredVariableType(MethodBody $body, Operand $operand, int $before): ?string
    {
        $declared = $body->variableType($operand);
        if ($declared !== null) {
            return $declared;
        }
        if ($operand->kind === Operand::CONSTANT) {
            return match (get_debug_type($operand->value)) {
                'integer' => 'int',
                'double' => 'float',
                'boolean' => 'bool',
                default => get_debug_type($operand->value),
            };
        }
        if (!in_array($operand->kind, [Operand::CV, Operand::TEMPORARY], true)) {
            return null;
        }

        for ($index = $before - 1; $index >= 0; $index--) {
            $instruction = $body->instruction($index);
            $produces = $instruction->result->kind === $operand->kind
                && $instruction->result->value === $operand->value;
            if ($operand->kind === Operand::CV) {
                $produces = $instruction->name === 'ASSIGN'
                    && $instruction->operand1->kind === Operand::CV
                    && $instruction->operand1->value === $operand->value;
            }
            if (!$produces) {
                continue;
            }
            $type = self::inferredInstructionType($body, $instruction, $index);
            if ($type !== null) {
                return $type;
            }
            break;
        }

        // A local may be assigned on more than one control-flow path. When
        // no dominating assignment is available, accept a type only when all
        // statically known assignments agree; conflicting paths remain
        // unknown rather than producing an unsafe match.
        $types = [];
        foreach ($body->instructions() as $index => $instruction) {
            $produces = $instruction->result->kind === $operand->kind
                && $instruction->result->value === $operand->value;
            if ($operand->kind === Operand::CV) {
                $produces = $instruction->name === 'ASSIGN'
                    && $instruction->operand1->kind === Operand::CV
                    && $instruction->operand1->value === $operand->value;
            }
            if (!$produces) {
                continue;
            }
            $type = self::inferredInstructionType($body, $instruction, $index);
            if ($type !== null) {
                $types[$type] = true;
            }
        }

        return count($types) === 1 ? array_key_first($types) : null;
    }

    private static function inferredInstructionType(MethodBody $body, Instruction $instruction, int $index): ?string
    {
        if ($instruction->name === 'ASSIGN') {
            return self::inferredVariableType($body, $instruction->operand2, $index);
        }
        if ($instruction->name === 'CONCAT') {
            return 'string';
        }
        if (in_array($instruction->name, ['ADD', 'SUB', 'MUL', 'MOD', 'POW'], true)) {
            $left = self::inferredVariableType($body, $instruction->operand1, $index);
            $right = self::inferredVariableType($body, $instruction->operand2, $index);
            if ($left === 'float' || $right === 'float') {
                return 'float';
            }
            if ($left === 'int' && $right === 'int') {
                return 'int';
            }
        }
        if ($instruction->name === 'DIV') {
            return 'float';
        }
        if (in_array($instruction->name, ['SL', 'SR', 'BW_AND', 'BW_OR', 'BW_XOR'], true)) {
            $left = self::inferredVariableType($body, $instruction->operand1, $index);
            $right = self::inferredVariableType($body, $instruction->operand2, $index);
            if ($left === 'int' && $right === 'int') {
                return 'int';
            }
        }
        if (in_array($instruction->name, ['BOOL', 'BOOL_NOT', 'IS_EQUAL', 'IS_NOT_EQUAL', 'IS_IDENTICAL', 'IS_NOT_IDENTICAL', 'IS_SMALLER', 'IS_SMALLER_OR_EQUAL', 'TYPE_CHECK'], true)) {
            return 'bool';
        }

        return null;
    }

    private static function matchesValueType(MethodBody $body, Operand $operand, string $type): bool
    {
        if ($operand->kind === Operand::CONSTANT) {
            return self::matchesConstantType($operand->value, $type);
        }

        return self::matchesVariableType($body, $operand, $type);
    }

    private static function matchesConstantType(mixed $value, ?string $type): bool
    {
        return match ($type) {
            null => true,
            'int', 'long' => is_int($value),
            'float', 'double' => is_float($value),
            'string' => is_string($value),
            'null' => $value === null,
            'bool' => is_bool($value),
            'array' => is_array($value),
            'object' => is_object($value),
            'class' => is_string($value) && (class_exists($value) || interface_exists($value) || trait_exists($value)),
            default => false,
        };
    }

    private static function isBuiltIn(string $type): bool
    {
        return in_array($type, [
            'HEAD', 'CONSTRUCTOR_HEAD', 'TAIL', 'RETURN', 'INVOKE',
            'INVOKE_ASSIGN', 'NEW', 'JUMP', 'FIELD', 'STORE', 'LOAD',
            'CONSTANT', 'THROW',
        ], true);
    }

    private static function isInvocationStart(string $name): bool
    {
        return in_array($name, ['INIT_FCALL', 'INIT_FCALL_BY_NAME', 'INIT_NS_FCALL_BY_NAME', 'INIT_STATIC_METHOD_CALL', 'INIT_METHOD_CALL', 'INIT_DYNAMIC_CALL', 'JMP_FRAMELESS'], true)
            || preg_match('/^FRAMELESS_ICALL_[0-3]$/', $name) === 1;
    }

    private static function invocationEnd(MethodBody $body, int $start): ?int
    {
        if ($body->instruction($start)->name === 'JMP_FRAMELESS') {
            $fallbackEnd = null;
            for ($index = $start + 1; $index < $body->count(); $index++) {
                $name = $body->instruction($index)->name;
                if (preg_match('/^FRAMELESS_ICALL_[0-3]$/', $name) === 1) {
                    return $index;
                }
                if (in_array($name, ['DO_FCALL', 'DO_ICALL', 'DO_UCALL', 'DO_FCALL_BY_NAME'], true)) {
                    $fallbackEnd = $index;
                }
            }

            return $fallbackEnd;
        }

        if (preg_match('/^FRAMELESS_ICALL_[0-3]$/', $body->instruction($start)->name) === 1) {
            return $start;
        }

        for ($index = $start + 1; $index < $body->count(); $index++) {
            if (in_array($body->instruction($index)->name, ['DO_FCALL', 'DO_ICALL', 'DO_UCALL', 'DO_FCALL_BY_NAME', 'DO_METHOD_CALL', 'DO_STATIC_METHOD_CALL'], true)) {
                return $index;
            }
        }

        return null;
    }

    /** Return the constructor call at the end of a NEW sequence. */
    private static function constructorEnd(MethodBody $body, int $start): ?int
    {
        $nestedCalls = 0;
        for ($index = $start + 1; $index < $body->count(); $index++) {
            $name = $body->instruction($index)->name;
            if (self::isInvocationStart($name)) {
                $nestedCalls++;
                continue;
            }
            if (!in_array($name, ['DO_FCALL', 'DO_ICALL', 'DO_UCALL', 'DO_FCALL_BY_NAME', 'DO_METHOD_CALL', 'DO_STATIC_METHOD_CALL'], true)) {
                continue;
            }
            if ($nestedCalls > 0) {
                $nestedCalls--;
                continue;
            }

            return $index;
        }

        return null;
    }

    private static function invocationArguments(MethodBody $body, int $start, int $end): int
    {
        if ($body->instruction($start)->name === 'JMP_FRAMELESS') {
            for ($index = $start + 1; $index <= $end; $index++) {
                if (preg_match('/^FRAMELESS_ICALL_([0-3])$/', $body->instruction($index)->name, $matches) === 1) {
                    return (int) $matches[1];
                }
            }
        }

        if (preg_match('/^FRAMELESS_ICALL_([0-3])$/', $body->instruction($start)->name, $matches) === 1) {
            return (int) $matches[1];
        }

        $arguments = 0;
        for ($index = $start + 1; $index < $end; $index++) {
            if (str_starts_with($body->instruction($index)->name, 'SEND')) {
                $arguments++;
            }
        }

        return $arguments;
    }

    private static function matchesInvocation(MethodBody $body, int $start, int $end, InvocationSpec $spec): bool
    {
        $init = $body->instruction($start);
        if ($init->name === 'JMP_FRAMELESS') {
            return $spec->kind === InvocationSpec::FUNCTION
                && $init->operand1->kind === Operand::CONSTANT
                && is_string($init->operand1->value)
                && self::matchesInvocationName($init->operand1->value, $spec)
                && $spec->acceptsArgumentCount(self::invocationArguments($body, $start, $end))
                && self::matchesInvocationSignature($body, $spec, $init->operand1->value, null);
        }

        if (preg_match('/^FRAMELESS_ICALL_[0-3]$/', $init->name) === 1) {
            return $spec->kind === InvocationSpec::FUNCTION
                && (($init->invocationTarget !== null && self::matchesInvocationName($init->invocationTarget, $spec))
                    || self::matchesFramelessFunction($body, $start, $spec))
                && $spec->acceptsArgumentCount(self::invocationArguments($body, $start, $end))
                && self::matchesInvocationSignature($body, $spec, $init->invocationTarget ?? $spec->name, null);
        }

        $name = $init->operand1->kind === Operand::CONSTANT && is_string($init->operand1->value) ? $init->operand1->value : null;
        $member = $init->operand2->kind === Operand::CONSTANT && is_string($init->operand2->value) ? $init->operand2->value : null;
        $calledName = $member ?? $name;
        $arguments = self::invocationArguments($body, $start, $end);
        if (!$spec->acceptsArgumentCount($arguments)) {
            return false;
        }

        $matched = match ($spec->kind) {
            InvocationSpec::FUNCTION => in_array($init->name, ['INIT_FCALL', 'INIT_FCALL_BY_NAME'], true)
                && $calledName !== null
                && self::matchesInvocationName($calledName, $spec),
            InvocationSpec::STATIC => $init->name === 'INIT_STATIC_METHOD_CALL'
                && $member !== null
                && self::matchesInvocationName($member, $spec)
                && ($spec->class === null || ($name !== null && InvocationSpec::matchesName($name, $spec->class))),
            InvocationSpec::MEMBER => $init->name === 'INIT_METHOD_CALL' && $member !== null && self::matchesInvocationName($member, $spec),
            default => false,
        };

        if (!$matched) {
            return false;
        }

        return self::matchesInvocationSignature($body, $spec, $calledName, $member);
    }

    private static function matchesInvocationSignature(MethodBody $body, InvocationSpec $spec, ?string $calledName, ?string $member): bool
    {
        if ($spec->signature === null) {
            return true;
        }
        try {
            if ($spec->kind === InvocationSpec::FUNCTION && $calledName !== null) {
                return $spec->acceptsSignature(new ReflectionFunction($calledName));
            }
            if ($spec->kind === InvocationSpec::STATIC && $spec->class !== null && $member !== null) {
                return $spec->acceptsSignature(new ReflectionMethod($spec->class, $member));
            }
            if ($spec->kind === InvocationSpec::MEMBER && $member !== null && str_contains($body->name, '::')) {
                [$class] = explode('::', $body->name, 2);
                return $spec->acceptsSignature(new ReflectionMethod($class, $member));
            }
        } catch (\ReflectionException) {
            return false;
        }

        return false;
    }

    private static function matchesInvocationName(string $actual, InvocationSpec $spec): bool
    {
        foreach ([$spec->name, ...$spec->aliases] as $pattern) {
            if (InvocationSpec::matchesName($actual, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private static function matchesFramelessFunction(MethodBody $body, int $start, InvocationSpec $spec): bool
    {
        if ($body->instruction($start)->invocationTarget !== null) {
            return self::matchesInvocationName($body->instruction($start)->invocationTarget, $spec);
        }

        for ($index = $start - 1; $index >= 0; $index--) {
            $instruction = $body->instruction($index);
            if ($instruction->name === 'JMP_FRAMELESS') {
                return $instruction->operand1->kind === Operand::CONSTANT
                    && is_string($instruction->operand1->value)
                    && self::matchesInvocationName($instruction->operand1->value, $spec);
            }

        }

        return false;
    }

    private static function isFramelessFallback(MethodBody $body, int $start): bool
    {
        return $start > 0 && $body->instruction($start - 1)->name === 'JMP_FRAMELESS';
    }
}
