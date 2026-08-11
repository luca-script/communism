<?php

declare(strict_types=1);

use Communism\Mixin\At;
use Communism\Mixin\Inject;
use Communism\Mixin\Mixin;
use Communism\Mixin\Overwrite;
use Communism\Reflect\ReflectionClass;
use Communism\Mixin\Shadow;
use Communism\Mixin\Unique;

#[Mixin(PartialInjectionTarget::class, OverrideInjectionTarget::class)]
final class PartialInjectionTrait
{
    private function __construct() {}
    #[Shadow]
    public bool $shouldGive;

    public function allowGiving(): void
    {
        $this->shouldGive = true;
    }

    public function notInjected(): void {}

    #[Overwrite('canGive')]
    public function replacementCanGive(): bool
    {
        return false;
    }
}


/**
 * @method void allowGiving()
 */
final class PartialInjectionTarget
{
    public function __construct(private bool $shouldGive = false) {}

    public function canGive(): bool
    {
        return $this->shouldGive;
    }
}

final class OverrideInjectionTarget
{
    private bool $shouldGive = true;

    public function canGive(): bool
    {
        return $this->shouldGive;
    }
}

it('partially injects selected mixin methods into a class', function (): void {
    (new ReflectionClass(PartialInjectionTarget::class))->inject(PartialInjectionTrait::class, ['allowGiving']);

    $target = new PartialInjectionTarget();
    call_user_func([$target, 'allowGiving']);

    expect($target->canGive())->toBeTrue();
    expect(in_array('notInjected', get_class_methods($target), true))->toBeFalse();
});
it('moves an overridden method to an internal name', function (): void {
    (new ReflectionClass(OverrideInjectionTarget::class))->inject(PartialInjectionTrait::class, ['replacementCanGive']);

    $target = new OverrideInjectionTarget();

    expect($target->canGive())->toBeFalse();
});

#[Mixin(UniqueTarget::class)]
final class UniqueMixin
{
    private function __construct() {}
    #[Unique]
    public function existing(): string
    {
        return 'mixin';
    }
}

final class UniqueTarget
{
    public function existing(): string
    {
        return 'target';
    }
}


it('keeps the target member when a Unique member collides', function (): void {
    (new ReflectionClass(UniqueTarget::class))->inject(UniqueMixin::class);

    $target = new UniqueTarget();
    expect($target->existing())->toBe('target');
    $uniqueMethod = array_values(array_filter(
        get_class_methods($target),
        static fn(string $method): bool => str_starts_with($method, '__unique_'),
    ))[0] ?? null;
    if (!is_string($uniqueMethod)) {
        throw new RuntimeException('The unique method was not injected');
    }
    expect((new ReflectionMethod($target, $uniqueMethod))->invoke($target))->toBe('mixin');
});

#[Mixin(OverwriteStandardTarget::class)]
final class OverwriteStandardMixin
{
    private function __construct() {}
    #[Overwrite]
    public function value(): int
    {
        return 7;
    }
}

final class OverwriteStandardTarget
{
    public function value(): int
    {
        return 2;
    }
}


it('uses the Mixin-shaped Overwrite annotation without a target alias', function (): void {
    (new ReflectionClass(OverwriteStandardTarget::class))->inject(OverwriteStandardMixin::class);

    expect((new OverwriteStandardTarget())->value())->toBe(7);
});
