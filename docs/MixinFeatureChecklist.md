# Needle / Mixin feature checklist

`tmp/Mixin` is a shallow checkout of the upstream FabricMC Mixin source. This
file inventories the Mixin surface and tracks the PHP/Needle equivalent. The
public API belongs in `Communism\Mixin`, `Communism\Reflect`, and
`Communism\Bytecode`; bytecode machinery belongs in the unsupported
`Communism\Internals` namespace.

Status markers:

- `[x]` implemented and covered by tests;
- `[~]` partially implemented, with the missing semantics listed below it;
- `[ ]` not implemented.

The checklist tracks project work, not whether the corresponding Java feature
exists upstream.

## Injection annotations and callback semantics

- `[~]` `Inject`: callback injection at one or more target points.
  - `[x]` `HEAD`, `TAIL`, `RETURN`, field/store, invocation, constant, and
    throw points supported by the current bytecode model.
  - `[x]` callback ordering before and after invocation points.
  - `[x]` `require`, `expect`, and `allow` match-count guarantees.
  - `[x]` explicit `Group` declarations with minimum/maximum aggregate counts.
  - `[x]` `Slice` ranges limiting the search region with reversed/missing-bound
    validation.
  - `[~]` ordinal selection and bounded target shifting; generic injection
    ordinals and callback `BY` shifting work, while `ModifyArg` intentionally
    rejects `BY` because its matched argument is not an insertion point.
  - `[x]` `Surrogate` handlers for alternate captured-local signatures, using
    the PHP-compatible `<handler>Surrogate` naming convention.
  - `[~]` `LocalCapture` policies equivalent to `NO_CAPTURE`, `PRINT`,
    `FAILSOFT`, and `CAPTURE_FAILHARD`; policy handling is implemented, while
    full stack-frame capture remains to be added.
- `[~]` virtual `CallbackInfo` and `CallbackInfoReturnable`.
  - `[x]` cancellation and return-value replacement.
  - `[x]` callback id, cancellability, cancellation state, and return value.
  - `[x]` virtual callback escape rejection before target mutation.
  - `[~]` local capture by target name and positional parameter; surrogate
    fallback handlers are supported, while full stack / local-frame capture
    modes remain to be implemented.
  - `[ ]` callback exception and cancellation diagnostics matching Mixin.
- `[x]` `ModifyArg`: replace one argument of a matched invocation.
  - `[x]` argument and handler-return type validation, with compatible numeric
    coercion under `#[Coerce]`.
- `[~]` `ModifyArgs`: expose and rewrite all invocation arguments through a
  virtual `Args` type.
  - `[x]` argument reads, writes, count queries, and handler validation are
    lowered to opcode operands; no `Args` object is created at runtime.
  - `[x]` virtual `Args` escape rejection and invalid-operation diagnostics.
  - `[x]` replacements reject non-constant or out-of-range argument indexes.
  - `[x]` statically known `setAll` lowering without constructing an array.
  - `[ ]` dynamic `setAll`, coercion, and immutable/primitive argument rules.
- `[~]` `ModifyConstant`: replace a matching literal.
  - `[x]` exact literal and wildcard literal replacement.
  - `[x]` generic ordinal filtering and basic integer, float, string, null,
    boolean, array, and object discriminators.
  - `[x]` PHP-compatible `long`/`double` aliases and resolvable class-name
    discriminators.
- `[~]` `ModifyVariable`: replace a local-variable store by name or wildcard.
  - `[x]` named stores and wildcard stores.
  - `[x]` named and wildcard local loads, plus generic ordinal filtering.
  - `[x]` `require`, `expect`, and `allow` match-count constraints.
- `[~]` local index selection is supported using declaration-order CV indexes;
  local type and implicit-discriminator handling remain to be added.
- `[~]` `Redirect`: redirect a matched invocation.
  - `[x]` global function replacement.
  - `[x]` static/member invocation replacement with matched argument and
    receiver mapping, including computed handler return values.
  - `[x]` field read/write redirection.
  - `[x]` constructor redirection through `NEW` (allocation plus constructor call).
  - `[x]` array read/write redirection through the Mixin-shaped `FIELD` point
    with target `[]`.
- `[~]` `Coerce` parameter behavior for compatible but non-identical callback
  argument types; primitive numeric and reference compatibility checks,
  Redirect argument coercion, and method-level Redirect return coercion are
  implemented. Field and constructor coercion remains.

## Injection-point language

- `[~]` Mixin-style invocation selectors.
  - `[x]` global, static, and member invocation forms.
  - `[x]` exact argument counts and bounded argument-count ranges.
  - `[x]` wildcard matching for global, static, and member method names.
  - `[ ]` descriptors, quantifiers, dynamic descriptors, and remappable
    selectors.
- `[~]` built-in `At` values.
  - `[x]` `HEAD`, `TAIL`, `RETURN`, `INVOKE`, `FIELD`, `CONSTANT`, `STORE`,
    and `THROW`.
  - `[x]` `INVOKE_ASSIGN` assignment-result matching with after-callbacks.
  - `[x]` `JUMP` opcode, conditional, unconditional, and wildcard filters.
  - `[x]` constructor-head anchors after the emitted parent constructor call.
  - `[x]` `NEW` class matching with exact and wildcard targets.
  - `[x]` custom registered injection points through the internal Needle registry.
- `[~]` `BeforeInvoke` filters: target, ordinal, and search mode.
  - `[x]` target and argument-count filtering.
  - `[ ]` invocation ordinal, permissive/strict search, and assignment-result
    filtering.
- `[~]` field access filters.
  - `[x]` field-name filtering for assignment bytecode.
  - `[x]` read/write mode and array-operation mode through `At::opcode`.
  - `[ ]` field descriptors.
- `[~]` constant filters.
  - `[x]` exact-value and wildcard matching.
  - `[ ]` constant type, null, ordinal, and class/type discriminators.
- `[x]` jump filters for conditional/unconditional branches and opcode.
- `[x]` `At.shift` (`BEFORE`, `AFTER`, `BY`).
  - `[x]` validation of the shift spelling, positive `BY` distance, and a
    maximum distance of 32 instructions.
  - `[x]` safe `BY` relocation for before/after callbacks.
- `[x]` `Slice(from, to)` ranges and clear invalid-slice errors.
- `[~]` explicit target selectors.
  - `[x]` function, static-method, member-method, and argument-range targets.
  - `[ ]` descriptors, wildcards, quantifiers, dynamic descriptors, and
    obfuscation/remapping aliases.

## Mixin class composition and metadata

- `[x]` final, non-instantiable mixin-class declarations with a private
  zero-argument constructor, including the `"*"` wildcard target.
- `[~]` `Shadow` declarations.
  - `[x]` required property shadowing with type/static/readonly validation.
  - `[x]` method shadow declarations with optional target aliases.
  - `[ ]` prefix handling.
- `[x]` `Overwrite` method replacement with deterministic `__deletedN` names.
  - `[x]` missing-target and duplicate-annotation validation.
  - `[ ]` complete signature, visibility, and conflict diagnostics.
- `[x]` `Unique` collision-safe member composition with deterministic names.
- `[ ]` soft overrides and intrinsic methods.
- `[x]` accessor and invoker generation for private/protected fields and
  methods, including static members and inferred `get`/`set`/`call` names.
  Constructor invokers remain unsupported because PHP constructors operate on
  an object being created rather than an existing receiver.
- `[ ]` interface implementation declarations and default-method composition
  (`Implements` / `Interface`).
- `[x]` `Final_` and `Mutable` field/method mutability controls, including
  Zend final methods and PHP readonly shadow properties.
- `[ ]` compatible receiver/interface coercion declarations.
- `[ ]` dynamic/obfuscated member declarations and descriptor aliases
  (`Dynamic`, `Desc`).
- `[x]` pseudo mixins for optional targets (`Pseudo`), which safely skip an
  absent class during explicit injection.
- `[ ]` constructor and enum-extension support where PHP has an analogue.

## Configuration, transformation, and lifecycle

- [x] compile-only target inspection through Zend's compiler, including
  detached class/method/function bytecode snapshots without executing files.
- [x] PHPStan bytecode target validation, including inherited methods resolved
  through their declaring class and actual injection-point matching.
- [ ] compile-time resolution of transitive include / require targets.

- `[ ]` declarative mixin configurations with target lists, priority, required
  flag, compatibility version, and environment side.
- `[~]` deterministic transformation ordering and conflict diagnostics.
  - `[x]` all injection points are resolved before target mutation.
  - `[ ]` explicit priority ordering and cross-mixin conflict reporting.
- `[~]` class-load/pre-load integration.
  - `[x]` explicit reflection-driven application to already declared classes.
  - `[ ]` automatic pre-load registration and clear already-loaded errors.
- `[ ]` plugin hooks for conditional application and target selection.
- `[ ]` reference-map/obfuscation equivalent for renamed PHP symbols.
- `[ ]` re-entrancy protection and a transformation audit trail.
- `[~]` transformed bytecode export and debugging.
  - `[x]` method decompilation and disassembly with opcode constants and member
    names.
  - `[ ]` persisted transformed-body snapshots and structured diff output.
- `[ ]` hot reload support, if the PHP runtime permits it.
- `[ ]` debug flags equivalent to Mixin's export, verbose, strict, and verify
  options.

## Bytecode safety and diagnostics

- `[x]` fail-fast validation before replacing target opcode/literal arrays.
- `[x]` exact ownership and deallocation of replaced Zend opcode/literal/cache
  storage on supported runtimes.
- `[~]` bytecode verification after every rewrite.
  - `[x]` branch relocation, cache slots, CVs, temporaries, literal indexes,
    and opcode handlers are rebuilt by the assembler.
  - `[ ]` an independent verifier that rejects malformed bodies before install.
- `[~]` structured injection errors.
  - `[x]` errors identify the injection point, target method, and count found.
  - `[ ]` mixin class, handler, resolved instruction location, slice, and
    selector context in a structured exception object.
- `[ ]` diagnostics for duplicate replacement spots, overlapping slices, and
  incompatible handler signatures before any mutation.
- `[~]` regression coverage for completed features.
  - `[x]` execution and negative-validation tests for the current injection,
    callback, composition, assembler, and opcode-rewrite features.
  - `[ ]` at least two unique execution and edge-case tests for every future
    feature marked complete.

## Next implementation order

1. Finish `ModifyVariable` load/store resolution, including local type and
   implicit-discriminator handling.
2. Complete `Coerce` and Redirect support for fields, constructors, and arrays.
3. Implement the remaining composition metadata: `Implements`, `Dynamic`,
   `Desc`, `Pseudo`, and soft
   overrides.
4. Add configuration, priority, lifecycle hooks, re-entrancy protection,
   structured diagnostics, and an independent bytecode verifier.

Every item promoted to `[x]` must add execution coverage, a negative
validation case, and relevant edge-case coverage before the status changes.
