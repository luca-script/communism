# Mixin-style injection points

Communism uses the Mixin model for injection declarations. A mixin is a final,
non-instantiable class with a private zero-argument constructor. An injection method
uses `#[Inject]` with an `At` object, while specialized handlers use
`ModifyArg`, `ModifyArgs`, `ModifyConstant`, `ModifyVariable`, or `Redirect`.

```php
#[Inject('greet', new At('HEAD'))]
public function beforeGreet(CallbackInfo $info, string $person): void {}

#[ModifyConstant('score', new At('CONSTANT'), 10)]
public function changeTen(int $constant): int
{
    return $constant + 1;
}

#[Redirect('score', new At('INVOKE', 'calculateScore'))]
public function replaceScoreCall(): int
{
    return 42;
}
```

Supported injection points include `HEAD`, `TAIL`, `RETURN`, `INVOKE`,
`INVOKE_ASSIGN`, `NEW`, `JUMP`,
`FIELD`, `CONSTANT`, `STORE`, and `THROW`. `INVOKE` accepts a function or
method target specification. `FIELD`, `STORE`, and typed `THROW` points use
their `target` value.

`NEW` matches object-construction opcodes. Its target is an optional class
name; omitting it matches every construction. `NEW` callbacks run before the
construction begins.

`INVOKE_ASSIGN` matches an invocation whose result is immediately assigned to
a local variable. Its callbacks run after the assignment, so a callback may
capture that local by name.

`JUMP` matches branch opcodes. Its target may be an exact opcode such as
`JMPZ`, `CONDITIONAL`, `UNCONDITIONAL`, or empty to match every supported
branch. Jump callbacks run before the branch instruction.

`FIELD` and `JUMP` also accept the Mixin-style `opcode` filter. For fields,
use `READ`, `WRITE`, `ARRAY_READ`, `ARRAY_WRITE`, or an exact Zend opcode such
as `FETCH_OBJ_R` and `ASSIGN_OBJ`; for jumps, use an exact opcode or
`CONDITIONAL`/`UNCONDITIONAL`. `target` and `opcode` are mutually exclusive for
`JUMP`.

`At` also carries Mixin-style `ordinal`, `shift`, and `by` metadata. An
ordinal selects the matching injection-point occurrence; `ModifyArg` uses its
separate `index` argument to select the invocation argument. `shift: 'AFTER'`
places a callback after an invocation, while bounded `BY` shifting moves a
before/after callback by a small instruction distance.

Invocation targets may use `*` as a wildcard in the function, class, or method
name. For example, `*` matches any global call, `SomeClass::*` matches any
static method on `SomeClass`, and `->get*` matches member methods beginning
with `get`.

`CONSTRUCTOR_HEAD` is available for constructor-specific head injection. When
the constructor calls `parent::__construct()`, the anchor is placed after the
first emitted static constructor call; otherwise it is placed at the beginning
of the constructor body.

`ModifyVariable` supports both `STORE` and `LOAD` points. Its `name`, `index`,
and `ordinal` properties select a local; `index` is the declaration-order CV
index in the target method. `ModifyConstant`
supports exact or wildcard values and basic type discriminators such as
`int`, `float`, `string`, and `null`.

`ModifyArg` validates both the handler parameter and its return type against
the selected invocation argument. Add `#[Coerce]` to the handler for a
compatible numeric conversion.

`Redirect` handlers may declare the matched invocation arguments. For member
calls, the receiver is mapped as the first handler argument when declared.
The handler may return a computed value; it does not need to return a literal.

`Redirect` also supports `FIELD` points. A field-read handler returns the
replacement value; a field-write handler receives the value that would have
been assigned and may perform an alternate action instead of the write.

For PHP array operations, use `FIELD` with the explicit target `[]`. An array
read handler may accept the array and key; an array-write handler may accept
the array, key, and assigned value. The original array operation is replaced
only after the handler has been successfully lowered.

`Redirect` supports `NEW` points as well. The point covers the allocation and
its constructor call, and the handler's return value becomes the constructed
object:

```php
#[Redirect('makeReplacement', new At('NEW', Widget::class))]
public function makeReplacement(): object
{
    return new ReplacementWidget();
}
```

`ModifyArgs` handlers accept exactly one virtual `Args` parameter. Calls to
`get`, `set`, `setAll`, and `getCount` are recognized while lowering and
redirected to the matched invocation's argument operands; no `Args` object or
runtime array is constructed. `setAll` currently requires a statically known
scalar list with exactly one value per argument.

Methods marked `#[Shadow]` are declarations for existing target methods. They
are validated and omitted from composition; `#[Shadow('actualName')]` may
refer to a target method with a different name.

Needle resolves all declarations before changing Zend opcode or literal
storage. A miss, invalid count constraint, or unsupported target fails before
bytecode mutation.

## Custom Needle injection points

Bytecode-level extensions can register a custom point with
`Needle\Matcher::registerInjectionPoint()`. Custom point IDs must start with
`_`, making it explicit that they are private Needle extensions. The resolver
receives the decompiled `MethodBody` and the `At` declaration, and returns
`Needle\MatchResult` objects. The result's `type` must name the lowering
semantics to use (`begin`, `return`, `invoke`, `field`, and so on), while its
`start`, `end`, and `action` identify the concrete location. Registration is
process-local and should be removed with `unregisterInjectionPoint()` when the
transformer is finished.

```php
Matcher::registerInjectionPoint('_CUSTOM_HEAD', static function (MethodBody $body, At $at): array {
    return [new MatchResult(0, 0, 'begin', 'before')];
});
```

Custom points are part of `Needle`, not the normal mixin surface. They are
intended for code that directly extends the bytecode transformation layer.
