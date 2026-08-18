<?php

declare(strict_types=1);

use Communism\Internals\Zend;
use Communism\Mixin\Accessor;
use Communism\Mixin\At;
use Communism\Mixin\Group;
use Communism\Mixin\Inject;
use Communism\Mixin\Invoker;

class ZendEdgeTarget
{
    public string $value;
    public static string $staticValue;
    public readonly string $readonlyValue;

    public function __construct()
    {
        $this->readonlyValue = 'readonly';
    }

    public function getValue(): string
    {
        return $this->value;
    }
    public function get(): string
    {
        return $this->value;
    }
    public function setValue(string $value): void
    {
        $this->value = $value;
    }
    public static function getStaticValue(): string
    {
        return self::$staticValue;
    }
    public function setReadonlyValue(string $value): void {}
    public function run(): string
    {
        return 'run';
    }
    public function callRun(): string
    {
        return $this->run();
    }
    public function unrelated(): string
    {
        return 'unrelated';
    }

    #[Group('edge')]
    public function grouped(): void {}
}

it('validates generated accessor and invoker targets', function (): void {
    $invoke = static function (string $name, mixed ...$arguments): mixed {
        $method = new ReflectionMethod(Zend::class, $name);
        $method->setAccessible(true);

        return $method->invoke(null, ...$arguments);
    };
    $class = ZendEdgeTarget::class;

    expect($invoke('accessorProperty', $class, new ReflectionMethod($class, 'getValue'), null))
        ->toBe('value')
        ->and($invoke('accessorProperty', $class, new ReflectionMethod($class, 'getStaticValue'), null))
        ->toBe('staticValue')
        ->and($invoke('accessorIsSetter', new ReflectionMethod($class, 'setValue')))->toBeTrue()
        ->and($invoke('accessorIsSetter', new ReflectionMethod($class, 'getValue')))->toBeFalse()
        ->and($invoke('invokerMethod', new ReflectionClass($class), new ReflectionMethod($class, 'callRun'), null))
        ->toBe('run');

    expect(static fn(): mixed => $invoke('accessorProperty', $class, new ReflectionMethod($class, 'run'), null))
        ->toThrow(InvalidArgumentException::class)
        ->and(static fn(): mixed => $invoke('accessorProperty', $class, new ReflectionMethod($class, 'getValue'), 'missing'))
        ->toThrow(InvalidArgumentException::class)
        ->and(static fn(): mixed => $invoke('accessorProperty', $class, new ReflectionMethod($class, 'getValue'), 'staticValue'))
        ->toThrow(InvalidArgumentException::class)
        ->and(static fn(): mixed => $invoke('accessorProperty', $class, new ReflectionMethod($class, 'get'), null))
        ->toThrow(InvalidArgumentException::class)
        ->and(static fn(): mixed => $invoke('accessorProperty', $class, new ReflectionMethod($class, 'setReadonlyValue'), null))
        ->toThrow(InvalidArgumentException::class)
        ->and(static fn(): mixed => $invoke('invokerMethod', new ReflectionClass($class), new ReflectionMethod($class, 'unrelated'), null))
        ->toThrow(InvalidArgumentException::class)
        ->and(static fn(): mixed => $invoke('invokerMethod', new ReflectionClass($class), new ReflectionMethod($class, 'callRun'), 'callRun'))
        ->toThrow(InvalidArgumentException::class)
        ->and(static fn(): mixed => $invoke('invokerMethod', new ReflectionClass($class), new ReflectionMethod($class, 'run'), 'missing'))
        ->toThrow(InvalidArgumentException::class)
        ->and(static fn(): mixed => $invoke('invokerMethod', new ReflectionClass($class), new ReflectionMethod($class, 'run'), null))
        ->toThrow(InvalidArgumentException::class);
});

it('applies mutability and attaches groups through Zend internals', function (): void {
    $invoke = static function (string $name, mixed ...$arguments): mixed {
        $method = new ReflectionMethod(Zend::class, $name);
        $method->setAccessible(true);

        return $method->invoke(null, ...$arguments);
    };
    $class = ZendEdgeTarget::class;

    /** @var ?Group $noGroup */
    $noGroup = $invoke('groupForMethod', new ReflectionMethod($class, 'run'));
    /** @var Group $grouped */
    $grouped = $invoke('groupForMethod', new ReflectionMethod($class, 'grouped'));

    expect($noGroup)->toBeNull()
        ->and($grouped->name)->toBe('edge')
        ->and(static fn(): mixed => $invoke('setPropertyMutability', $class, 'missing', false, true))
        ->toThrow(InvalidArgumentException::class)
        ->and(static fn(): mixed => $invoke('setMethodMutability', $class, '', false, true))
        ->toThrow(InvalidArgumentException::class)
        ->and(static fn(): mixed => $invoke('setMethodMutability', $class, 'missing', false, true))
        ->toThrow(InvalidArgumentException::class);

    $invoke('setPropertyMutability', $class, 'value', false, true);
    $invoke('setPropertyMutability', $class, 'value', true, false);
    $invoke('setMethodMutability', $class, 'run', false, true);
    $invoke('setMethodMutability', $class, 'run', true, false);

    $group = new Group('attached');
    /** @var Inject $attached */
    $attached = $invoke('attachGroup', new Inject('run', new At('HEAD')), $group);
    /** @var Inject $preserved */
    $preserved = $invoke('attachGroup', new Inject('run', new At('HEAD'), group: $group), new Group('ignored'));

    expect($attached->group)->toBe($group)
        ->and($preserved->group)->toBe($group);

    Zend::disableJitForFunction('strlen');
    Zend::disableJitForMethod($class, 'run');
    Zend::disableJitForClass($class);
});
