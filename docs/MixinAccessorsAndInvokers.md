# Accessors and invokers

`#[Accessor]` and `#[Invoker]` are Mixin-style declarations for exposing a
member that the target class keeps private or protected. Their methods are
normally abstract in the mixin class: Needle installs the implementation when
the mixin is injected.

## Accessors

An accessor infers its property from the method name:

```php
#[Accessor]
public function getSecret(): string
{
    throw new \LogicException('Mixin accessor stub');
}

#[Accessor]
public function setSecret(string $value): void
{
    throw new \LogicException('Mixin accessor stub');
}
```

The inferred names are `secret`. An explicit property name can be supplied
when the method name does not follow that convention:

```php
#[Accessor('internalValue')]
public function readValue(): mixed
{
    throw new \LogicException('Mixin accessor stub');
}
```

Getters and `is...` methods take no arguments. Setters named `set...` take one
argument. Static accessors must target static properties, and instance
accessors must target instance properties. Read-only properties cannot have a
setter.

## Invokers

An invoker targets a method by explicit name or by convention:

```php
#[Invoker('whisper')]
public function callWhisper(string $prefix): string
{
    throw new \LogicException('Mixin invoker stub');
}
```

Without an explicit target, `callWhisper` and `invokeWhisper` first try the
corresponding `whisper` method. Private and protected methods are valid
targets, as are static methods. Invoker arguments and return values are
forwarded unchanged.

Generated implementations are direct Zend bytecode clones. Accessors use
property read/write opcodes and invokers clone the target method body; no
reflection-backed runtime dispatcher or helper object is called at the call
site.

## Final and mutable members

PHP reserves the word `final`, so the attribute class is named `Final_` and is
normally imported under a readable alias:

```php
use Communism\Mixin\Final_ as FinalMember;

#[FinalMember]
public function helper(): void {}
```

`FinalMember` makes an injected or overwritten method final and makes a
shadowed property readonly. `#[Mutable]` is valid on a shadow and removes the
target method's final flag or the target property's readonly flag. Conflicting
`Final_` and `Mutable` declarations are rejected before the target is changed.
