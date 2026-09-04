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
    - `[x]` multiple target methods on one callback declaration, using a
      PHP-native list of method names.
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
    - `[x]` virtual `getMethodName()` exposing the selected PHP target method.
    - `[x]` virtual callback escape rejection before target mutation.
    - `[~]` local capture by target name and positional parameter; surrogate
      fallback handlers are supported, while full stack / local-frame capture
      modes remain to be implemented.
    - `[x]` explicit `Local(name: ...)` and `Parameter(name: ...)/Parameter(ordinal: ...)`
      bindings for resolving handler-parameter name conflicts.
    - `[x]` positional capture of initialized named CV locals after target
      parameters; full stack / local-frame capture modes remain unsupported.
    - `[~]` callback exception and cancellation diagnostics; fail-hard callback
      preparation failures expose structured point, method, instruction-span,
      and chained-cause data, while runtime callback event export remains.
- `[x]` `ModifyArg`: replace one argument of a matched invocation.
    - `[x]` argument and handler-return type validation, including PHP union
      signatures, with compatible numeric coercion under `#[Coerce]`.
    - `[x]` `Slice` ranges are shared with the generic `Inject` resolver.
- `[~]` `ModifyArgs`: expose and rewrite all invocation arguments through a
  virtual `Args` type.
    - `[x]` argument reads, writes, count queries, and handler validation are
      lowered to opcode operands; no `Args` object is created at runtime.
    - `[x]` virtual `Args` escape rejection and invalid-operation diagnostics.
    - `[x]` replacements reject non-constant or out-of-range argument indexes.
    - `[x]` statically known `setAll` lowering without constructing an array.
    - `[~]` statically known `setAll` locals and array constructions are
      lowered, including variable element expressions in source order and
      zero-argument functions or static methods returning literal lists; opaque
      or argument-dependent runtime-built arrays remain. Known primitive replacements are validated
      against the reflected invocation types before mutation, including
      union-typed parameters, and opted-in known numeric and iterable-to-array
      replacements (including typed expression operands) are coerced with
      explicit bytecode casts; unknown runtime operands remain conservative.
    - `[x]` `Slice` ranges are supported for `ModifyArgs` declarations.
- `[~]` `ModifyConstant`: replace a matching literal.
    - `[x]` exact literal and wildcard literal replacement.
    - `[x]` generic ordinal filtering and basic integer, float, string, null,
      boolean, array, and object discriminators.
    - `[x]` PHP-compatible `long`/`double` aliases, explicit `nullValue`, and
      resolvable class-name discriminators.
    - `[x]` `Slice` ranges are supported for constant matching.
- `[~]` `ModifyVariable`: replace a local-variable store by name or wildcard.
    - `[x]` named stores and wildcard stores.
    - `[x]` named and wildcard local loads, plus generic ordinal filtering.
    - `[x]` `require`, `expect`, and `allow` match-count constraints.
    - `[x]` PHP-native `argsOnly` filtering for method-argument locals.
    - `[x]` diagnostic `print` mode for inspecting locals without mutation.
    - `[x]` `Slice` ranges are supported for local matching.
- `[~]` local selection supports declaration-order CV indexes and handler-type
  filtering for known declared/literal types, including PHP union/intersection
  handler types; decompiled target parameter metadata preserves those PHP type
  expressions for matching and replacement coercion. Simple preceding assignments,
  arithmetic temporaries, and concatenation temporaries are propagated for
  load matching; implicit handler-type discrimination and conservative
  whole-body inference across consistent control-flow assignments are covered.
  Callback locals are also rejected when their CV exists but is not initialized
  at the injection point, including correct post-assignment handling; known
  captured-local callback types are validated and support `#[Coerce]` casts.
- `[~]` `Redirect`: redirect a matched invocation.
    - `[x]` global function replacement.
    - `[x]` static/member invocation replacement with matched argument and
      receiver mapping, including computed handler return values.
    - `[x]` field read/write redirection.
    - `[x]` constructor redirection through `NEW` (allocation plus constructor call),
      including direct constructor-argument mapping and signature validation.
    - `[x]` array read/write redirection through the Mixin-shaped `FIELD` point
      with target `[]`.
    - `[x]` `Slice` ranges are supported for redirects.
- `[~]` `Coerce` parameter behavior for compatible but non-identical callback
  argument types; primitive numeric and reference compatibility checks,
  union/intersection-aware callback and member-receiver validation/casts,
  Redirect argument coercion,
  method-level Redirect return coercion, and
  union/intersection-aware field and constructor replacement validation are
  implemented. Iterable-to-array
  coercion and numeric explicit cast emission are implemented, including
  parameter-level callback coercion, scalar string/boolean casts,
  `ModifyConstant`/`ModifyVariable` input coercion, field Redirect input coercion, and typed
  `ModifyConstant`/`ModifyVariable` replacement results;
  other array-shape coercions and cast
  contexts remain.

## Injection-point language

- `[~]` Mixin-style invocation selectors.
    - `[x]` global, static, and member invocation forms.
    - `[x]` exact argument counts and bounded argument-count ranges.
    - `[x]` wildcard matching for global, static, and member method names.
    - `[x]` reflected PHP signatures, including named, union, nullable, and
      intersection types, PHP-native selector quantifiers, and explicit
      `['aliases' => [...]]` remapping; signatures use PHP types rather than
      JVM descriptors.
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
    - `[x]` invocation ordinal and assignment-result filtering.
    - `[ ]` deprecated permissive/strict remapping search modes, which have no
      direct PHP analogue.
- `[~]` field access filters.
    - `[x]` field-name filtering for assignment bytecode.
    - `[x]` read/write mode and array-operation mode through `At::opcode`.
    - `[x]` PHP-shaped field descriptors (`::member:type`), including
      slash-separated namespaced class names.
- `[~]` constant filters.
    - `[x]` exact-value and wildcard matching.
    - `[x]` constant type, explicit null, ordinal, and class/type discriminators.
- `[x]` jump filters for conditional/unconditional branches and opcode.
- `[x]` `At.shift` (`BEFORE`, `AFTER`, `BY`).
    - `[x]` validation of the shift spelling, positive `BY` distance, and a
      maximum distance of 32 instructions.
    - `[x]` safe `BY` relocation for before/after callbacks.
- `[x]` `Slice(from, to)` ranges and clear invalid-slice errors.
- `[~]` explicit target selectors.
    - `[x]` function, static-method, member-method, and argument-range targets.
    - `[x]` PHP signatures, wildcards, PHP-native quantifiers, explicit selector
      aliases, and external reference maps.

## Mixin class composition and metadata

- `[x]` final, non-instantiable mixin-class declarations with a private
  zero-argument constructor, including the `"*"` wildcard target.
- `[~]` `Shadow` declarations.
    - `[x]` required property shadowing with type/static/readonly validation.
    - `[x]` method shadow declarations with optional target aliases.
    - `[x]` method prefix handling with target/prefix conflict validation.
- `[x]` `Overwrite` method replacement with deterministic `__deletedN` names.
    - `[x]` missing-target and duplicate-annotation validation.
    - `[~]` source/target staticness, visibility, parameter shape/types, and
      return-type compatibility are validated before mutation; complete
      conflict diagnostics remain.
- `[x]` `Unique` collision-safe member composition with deterministic names,
  including class-level declarations and the PHP-native `silent` metadata.
- `[~]` soft overrides and intrinsic methods.
    - `[x]` non-displacing and displacing `Intrinsic` methods, including
      rewriting direct self-calls to a displaced original method.
    - `[x]` PHP-native `SoftOverride` methods shadow inherited methods on the
      transformed child class without mutating the parent.
    - `[ ]` soft-interface metadata beyond inherited PHP methods.
- `[x]` accessor and invoker generation for private/protected fields and
  methods, including static members and inferred `get`/`set`/`call` names.
  Generated accessors and invokers use direct cloned Zend bytecode without a
  runtime reflection dispatcher.
  Constructor invokers remain unsupported because PHP constructors operate on
  an object being created rather than an existing receiver.
- `[~]` interface implementation declarations and default-method composition
  (`Implements_` / `Interface_`).
    - `[x]` interface contract validation and prefixed default-method composition
      under PHP method names.
    - `[x]` PHP-native `InterfaceRemap` modes, including `ALL` fallback to an
      unprefixed method and strict `ONLY_PREFIXED` behavior.
    - `[x]` registering the interface in an already-declared PHP class through
      the Zend class-entry interface list.
- `[x]` `Final_` and `Mutable` field/method mutability controls, including
  Zend final methods and PHP readonly shadow properties.
- `[~]` compatible receiver/interface coercion declarations.
    - `[x]` typed Redirect member receivers are validated against declared class
      and interface receiver types before bytecode mutation.
    - `[x]` parameter-level or method-level `Coerce` explicitly authorizes
      otherwise unknown/dynamic Redirect receiver operands.
- `[~]` dynamic/obfuscated member declarations and descriptor aliases
  (`Dynamic`, `Desc`).
    - `[x]` metadata-only `Dynamic` declarations on methods/properties, with
      their descriptions included in unresolved injection diagnostics.
    - `[x]` PHP-shaped `Desc` selector values can be used directly as invocation
      targets, including owner, argument, return-type, and ID metadata.
    - `[~]` obfuscation/remapping metadata.
        - `[x]` deterministic `Overwrite` and `Shadow` member aliases for renamed
          PHP symbols.
        - `[x]` reusable `ReferenceMap` remapping for invocation and field
          selectors, including selector aliases.
- `[x]` pseudo mixins for optional targets (`Pseudo`), which safely skip an
  absent class during explicit injection.
- `[ ]` constructor and enum-extension support where PHP has an analogue.

## Configuration, transformation, and lifecycle

- [x] compile-only target inspection through Zend's compiler, including
      detached class/method/function bytecode snapshots without executing files.
- [x] PHPStan bytecode target validation, including inherited methods resolved
      through their declaring class and actual injection-point matching.
- `[x]` compile-time resolution of statically resolvable transitive include /
  require targets, including `__DIR__` concatenations, cycle detection,
  detached declaration merging, and missing-required-target diagnostics.

- `[x]` declarative mixin configurations with target lists, priority, required
  flag, compatibility version, and environment side through
  `MixinConfiguration` and `Zend::applyMixinConfigurations()`; class-based
  manifests are installable through `Runtime::installOrGet()->add()`.
- `[~]` deterministic transformation ordering and conflict diagnostics.
    - `[x]` all injection points are resolved before target mutation.
    - `[x]` explicit stable priority ordering; cross-mixin conflicts retain the
      structured placement diagnostics emitted during each ordered transform.
- `[~]` class-load/pre-load integration.
    - `[x]` explicit reflection-driven application to already declared classes.
    - `[x]` opt-in automatic post-autoload registration through
      `Zend::registerPreloadConfigurations()`, reversible with
      `clearPreloadConfigurations()`, with clear already-loaded target errors.
- `[x]` declarative target selection through `MixinConfiguration`; no separate
  plugin hook is exposed because arbitrary runtime vetoes are outside the
  intended composition model.
- `[x]` reference-map/obfuscation equivalent for renamed PHP symbols through
  `ReferenceMap`.
- `[x]` active transformation re-entrancy protection; recursive mutation is
  rejected and the guard is cleared after success or failure. Persistent audit
  trails and lifecycle event exports are intentionally not part of the API.
- `[~]` transformed bytecode export and debugging.
    - `[x]` method decompilation and disassembly with opcode constants and member
      names.
    - `[x]` persisted immutable before/after transformed-body snapshots with
      changed-method and structured diff output.
- `[ ]` hot reload support, if the PHP runtime permits it.
- `[x]` PHP-native debug options equivalent to export, verbose, strict, and
  verify through `DebugOptions` and `Zend::setDebugOptions()`. Export controls
  persisted snapshots, verbose emits lifecycle diagnostics, strict controls
  required configuration failures, and verification remains safety-enforced by
  the independent assembler verifier even when the option is disabled.

## Bytecode safety and diagnostics

- `[x]` fail-fast validation before replacing target opcode/literal arrays.
- `[x]` exact ownership and deallocation of replaced Zend opcode/literal/cache
  storage on supported runtimes.
- `[~]` bytecode verification after every rewrite.
    - `[x]` branch relocation, cache slots, CVs, temporaries, literal indexes,
      and opcode handlers are rebuilt by the assembler.
    - `[x]` an independent verifier rejects malformed instruction, operand, and
      metadata shapes before assembly.
- `[~]` structured injection errors.
    - `[x]` errors identify the injection point, target method, and count found
      through `InjectionException` fields.
    - `[x]` mixin class, handler, resolved instruction location, slice, and
      selector context in a structured exception object.
- `[~]` diagnostics for duplicate replacement spots, overlapping slices, and
  incompatible handler signatures before any mutation.
    - `[x]` duplicate and overlapping replacement placements expose a
      structured conflict kind and both instruction spans.
    - `[x]` handler-signature diagnostics expose handler, target, selector, and
      resolved instruction-span context through `HandlerValidationException`.
- `[~]` regression coverage for completed features.
    - `[x]` execution and negative-validation tests for the current injection,
      callback, composition, assembler, and opcode-rewrite features.
    - `[ ]` at least two unique execution and edge-case tests for every future
      feature marked complete.

## Next implementation order

1. Complete the remaining PHP-native `Coerce` array-shape and cast contexts.
2. Add richer local-frame capture where Zend exposes the required metadata.
3. Document and test the PHP-impossible JVM lifecycle features, then re-audit
   actual parity against the upstream checklist.

Every item promoted to `[x]` must add execution coverage, a negative
validation case, and relevant edge-case coverage before the status changes.
