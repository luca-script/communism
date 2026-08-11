# Pseudo mixins

PHP cannot transform a class which is not declared, but an optional target can
still be represented with `#[Pseudo]`:

```php
#[Mixin('Optional\\Feature\\Target')]
#[Pseudo]
final class OptionalFeatureMixin
{
    private function __construct() {}

    // Applied when the optional target is declared.
}
```

Applying a pseudo mixin to an absent class is a no-op. Applying an ordinary
mixin to an absent class remains an error, so a typo in a required target is
not silently ignored.
