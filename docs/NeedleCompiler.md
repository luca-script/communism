# Needle compile-only inspection

`Communism\Internals\Needle\Compiler::compileFile()` is the Communism-facing
path used when Needle needs Zend's view of a PHP file without running that
file. The unsafe compiler lifecycle belongs to `Zendful\Internals\Compiler`;
Needle receives detached `Zendful` value handles and converts them into its
own internal snapshots.

The fallback Zendful backend calls Zend's exported `compile_file()` entry point with
ZEND_COMPILE_WITHOUT_EXECUTION. The result is copied immediately into
Needle values:

- CompiledFile::body contains the detached top-level MethodBody.
- CompiledFile::classes contains newly compiled classes and their methods.
- CompiledFile::functions contains newly compiled global functions.

This follows the relevant OPcache compiler lifecycle in php-src:
OPcache records the original function/class table bounds, compiles with
delayed binding and without execution, then extracts the newly-created
entries into its persistent script. Needle uses the same bounded-table
approach, but copies the entries into ordinary PHP values and destroys them
instead of retaining a persistent script.

The public opcache_compile_file() wrapper is not sufficient for this job. It
returns only a boolean after OPcache has retained or destroyed the compiled
op array; the bridge must call Zend's compiler entry point directly.

The returned objects do not contain FFI pointers. Their opcode operands,
literal values, variable names, source locations, and method bodies remain
usable after the Zend compiler storage has been released.

The compiler table is request-local. Zendful records its original bounds,
reads only entries created by this compilation, destroys those entries through
Zend's hash-table destructors, and restores the compiler arena to a checkpoint
taken before compilation. This is important because constructing the snapshot
can autoload Needle's own PHP classes into the same Zend tables.

This is intentionally an incomplete bytecode view. It does not execute
include, require, autoloaders, or top-level expressions, and it does not
resolve runtime-dependent declarations. It is sufficient for locating
declared classes, functions, methods, opcode sites, literal constants, and
member names. Source-level/static reflection should supplement it when a
mixin needs information that PHP only determines during linking.

## OPcache ownership

OPcache does not provide a per-function release operation. Its public
`opcache_invalidate()` function marks a persistent script as stale; it does
not detach the already-installed class entry, function, opcode array, or
literal pool from the current request. `opcache_reset()` schedules a complete
cache restart and disables the accelerator for the current request, but it
also does not detach data that has already been loaded.

When a cached script is loaded, OPcache copies the top-level `zend_op_array`
header into request memory but intentionally reuses the persistent function
and class entries. The methods in a cached class therefore remain backed by
OPcache storage. OPcache may also place the literal pool before the opcode
array, so relative constant offsets can be negative.

Needle treats an immutable OPcache function or class as read-only. Rewriting
one raises an exception before touching shared storage. To obtain process-local
mutable Zend data, reset or disable OPcache before the target file is loaded,
then let PHP compile the file while the accelerator is disabled for that
request. Invalidating the file after its class has already been loaded is too
late. Needle's test bootstrap performs this reset before Composer loads test
classes.

Cloning only the opcode array is not a safe workaround: the method pointer is
owned by the shared class entry, and class-name caches, inheritance metadata,
runtime cache slots, and existing instances can still reference the original
entry. A future detach implementation must clone and relink that complete
request-local graph before installing it in the executor tables.

PHPStan uses this bridge through `Communism_PHPStan\BytecodeIndex`. For an
inherited target method it first asks PHPStan for the method's declaring class,
then compiles that class's source file. The static rule can therefore validate
that an `At` point actually exists in the target body without loading or
executing the target file.

Needle is an internal bytecode tool. Normal consumers should continue to use
the Mixin attributes and leave this namespace to tooling and transformation
implementations. Communism does not require FFI when a native Zendful backend
is installed; the fallback backend requires the PHP FFI extension.
