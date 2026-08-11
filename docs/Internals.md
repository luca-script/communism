# Internal namespaces

`Communism\Internals` is not a supported consumer API. It contains the Needle
bytecode machinery, the Zend/FFI bridge, generated-member runtime helpers, and
other implementation details used by the public namespaces.

Classes in `Communism\Internals` may be renamed, removed, or change behavior
between versions without warning. Applications should use `Communism\Mixin`,
`Communism\Reflect`, and `Communism\Bytecode` instead.
