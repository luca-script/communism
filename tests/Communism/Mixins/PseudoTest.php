<?php

declare(strict_types=1);

use Communism\Mixin\Mixin;
use Communism\Mixin\Pseudo;

#[Mixin('Optional\Feature\Target')]
#[Pseudo]
final class OptionalFeatureMixin
{
    private function __construct() {}
}


#[Mixin('Required\Feature\Target')]
final class RequiredFeatureMixin
{
    private function __construct() {}
}


/** @return string */
function pseudoAbsentTarget(): string
{
    return 'Optional\Feature\Target';
}

/** @return string */
function requiredAbsentTarget(): string
{
    return 'Required\Feature\Target';
}

it('skips a pseudo mixin when its optional target is absent', function (): void {
    Communism\Internals\Zend::injectMixinMethods(pseudoAbsentTarget(), OptionalFeatureMixin::class);

    expect(class_exists('Optional\Feature\Target'))->toBeFalse();
});

it('rejects an absent target without a Pseudo declaration', function (): void {
    expect(function (): never {
        Communism\Internals\Zend::injectMixinMethods(requiredAbsentTarget(), RequiredFeatureMixin::class);
        throw new \RuntimeException('Required injection unexpectedly succeeded');
    })->toThrow(\InvalidArgumentException::class, 'target class Required\\Feature\\Target is not declared');
});
