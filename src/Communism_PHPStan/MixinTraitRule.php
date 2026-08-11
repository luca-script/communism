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
 * File: MixinTraitRule.php                                                   *
 * Purpose: Source file for MixinTraitRule.php.                               *
 *============================================================================*/

declare(strict_types=1);

namespace Communism_PHPStan;

use Communism\Mixin\Mixin;
use PhpParser\Node;
use PhpParser\Node\Stmt\Trait_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/** Rejects the removed trait-based mixin surface. */
/** @implements Rule<Trait_> */
final class MixinTraitRule implements Rule
{
    public function getNodeType(): string
    {
        return Trait_::class;
    }

    /** @return list<\PHPStan\Rules\IdentifierRuleError> */
    public function processNode(Node $node, Scope $scope): array
    {
        foreach ($node->attrGroups as $group) {
            foreach ($group->attrs as $attribute) {
                if ($scope->resolveName($attribute->name) !== Mixin::class) {
                    continue;
                }

                return [RuleErrorBuilder::message(
                    'Mixins must be final classes with a private zero-argument constructor; traits are not mixins.',
                )->identifier('communism.mixinTrait')->build()];
            }
        }

        return [];
    }
}
