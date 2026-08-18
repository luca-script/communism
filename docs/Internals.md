# Internal namespaces and runtime ownership

This repository has two layers with different responsibilities:

- `Zendful` owns the backend-neutral runtime handles and the unsafe Zend
  boundary. Its fallback implementation uses FFI; installations with a
  `Zendful\Native` backend can provide the same handles without requiring FFI
  at runtime.
- `Communism` owns the mixin, reflection, bytecode, and Needle behavior. It
  consumes Zendful handles and must not access Zend memory or FFI directly.

`Communism\Internals` is not a supported consumer API. It contains the Needle
bytecode machinery, generated-member runtime helpers, and other implementation
details used by the public namespaces. Runtime access and mutation are
delegated to the backend-neutral handles exposed by `Zendful`; FFI and other
unsafe runtime operations are not part of Communism's implementation.

Classes in `Communism\Internals` may be renamed, removed, or change behavior
between versions without warning. Applications should use `Communism\Mixin`,
`Communism\Reflect`, and `Communism\Bytecode` instead.

The test suites are intentionally split to keep these boundaries visible:

- `composer test:zendful` runs Zendful API, backend, compiler, and executor
  tests.
- `composer test:communism` runs Communism behavior and architecture tests.
- `composer test` runs both suites.
