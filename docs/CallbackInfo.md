# CallbackInfo

`CallbackInfo` and `CallbackInfoReturnable` are virtual injection parameters.
Needle lowers them while rewriting bytecode, so an injected target method does
not receive a PHP object at runtime. Their constructors intentionally throw;
they are type markers for the lowering pass, not runtime state objects.

```php
#[Inject('greet', new At('HEAD'))]
public function betterGreet(CallbackInfo $info, string $person): void
{
    if (!can_greet($person)) {
        $info->cancel('This person cannot be greeted');
    }
}
```

`CallbackInfo` provides `getId()`, `isCancellable()`, `isCancelled()`, and
`cancel(?string $reason = null)`. Cancellation returns from the target at the
injection point. `CallbackInfoReturnable` additionally provides
`getReturnValue()` and `setReturnValue($value)`; setting a value returns that
value from the target and marks the callback cancelled.

Use `CallbackInfoReturnable` for return-value injections, especially with
`return::modify`. A callback method's ordinary arguments follow the virtual
callback parameter and are mapped to the target method's arguments. A handler
can capture a target local by declaring a parameter with the same name as that
local; name-based capture takes precedence over positional mapping. An
unmappable callback parameter is rejected during preflight.

```php
#[Inject('score', new At('RETURN'))]
public function adjust(CallbackInfoReturnable $info, int $scoreBase): void
{
    $info->setReturnValue($info->getReturnValue() + $scoreBase);
}
```
