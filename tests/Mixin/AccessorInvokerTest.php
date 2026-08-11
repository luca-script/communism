<?php

declare(strict_types=1);

use Communism\Mixin\Accessor;
use Communism\Mixin\Invoker;
use Communism\Mixin\Mixin;

#[Mixin(AccessorTarget::class)]
final class AccessorMixin
{
    private function __construct() {}
    #[Accessor]
    public function getSecret(): string
    {
        throw new \LogicException('Mixin accessor stub');
    }

    #[Accessor]
    public function setSecret(string $secret): void
    {
        throw new \LogicException('Mixin accessor stub');
    }

    #[Invoker]
    public function callWhisper(string $prefix): string
    {
        throw new \LogicException('Mixin invoker stub');
    }
}

/**
 * @method string getSecret()
 * @method void setSecret(string $secret)
 * @method string callWhisper(string $prefix)
 */
final class AccessorTarget
{
    private string $secret = 'hidden';

    private function whisper(string $prefix): string
    {
        return $prefix . $this->secret;
    }

    public function directWhisper(string $prefix): string
    {
        return $this->whisper($prefix);
    }
}


#[Mixin(StaticAccessorTarget::class)]
final class StaticAccessorMixin
{
    private function __construct() {}
    #[Accessor]
    public static function getCount(): int
    {
        throw new \LogicException('Mixin accessor stub');
    }

    #[Invoker('sum')]
    public static function callSum(int $left, int $right): int
    {
        throw new \LogicException('Mixin invoker stub');
    }
}

/**
 * @method static int getCount()
 * @method static int callSum(int $left, int $right)
 */
final class StaticAccessorTarget
{
    private static int $count = 4;

    private static function sum(int $left, int $right): int
    {
        return $left + $right;
    }

    public static function directSum(int $left, int $right): int
    {
        return self::sum($left, $right);
    }

    public static function directCount(): int
    {
        return self::$count;
    }
}


final class MissingAccessorTarget {}

#[Mixin(MissingAccessorTarget::class)]
final class MissingAccessorMixin
{
    private function __construct() {}
    #[Accessor]
    public function getMissing(): string
    {
        throw new \LogicException('Mixin accessor stub');
    }
}


final class MissingInvokerTarget {}

#[Mixin(MissingInvokerTarget::class)]
final class MissingInvokerMixin
{
    private function __construct() {}
    #[Invoker('doesNotExist')]
    public function callMissing(): void
    {
        throw new \LogicException('Mixin invoker stub');
    }
}


it('generates instance accessors and private method invokers', function (): void {
    (new Communism\Reflect\ReflectionClass(AccessorTarget::class))->inject(AccessorMixin::class);

    $target = new AccessorTarget();
    expect($target->getSecret())->toBe('hidden')
        ->and($target->callWhisper('value: '))->toBe('value: hidden');

    $target->setSecret('changed');
    expect($target->getSecret())->toBe('changed')
        ->and($target->callWhisper('value: '))->toBe('value: changed');
});

it('generates static accessors and explicitly named invokers', function (): void {
    (new Communism\Reflect\ReflectionClass(StaticAccessorTarget::class))->inject(StaticAccessorMixin::class);

    expect(StaticAccessorTarget::getCount())->toBe(4)
        ->and(StaticAccessorTarget::callSum(6, 7))->toBe(13);
});

it('rejects an accessor for a missing property before adding the method', function (): void {
    expect(function (): never {
        (new Communism\Reflect\ReflectionClass(MissingAccessorTarget::class))->inject(MissingAccessorMixin::class);
        throw new \RuntimeException('Accessor injection unexpectedly succeeded');
    })
        ->toThrow(\InvalidArgumentException::class, 'targets missing property $missing');
    expect(in_array('getMissing', get_class_methods(MissingAccessorTarget::class), true))->toBeFalse();
});

it('rejects an invoker for a missing target method before adding the method', function (): void {
    expect(function (): never {
        (new Communism\Reflect\ReflectionClass(MissingInvokerTarget::class))->inject(MissingInvokerMixin::class);
        throw new \RuntimeException('Invoker injection unexpectedly succeeded');
    })
        ->toThrow(\InvalidArgumentException::class, 'targets a missing method');
    expect(in_array('callMissing', get_class_methods(MissingInvokerTarget::class), true))->toBeFalse();
});
