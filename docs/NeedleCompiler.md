# Needle compile-only inspection

Communism\Internals\Needle\Compiler::compileFile() is the low-level path used when
Needle needs Zend's view of a PHP file without running that file.

It calls Zend's exported compile_file() entry point with
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

The compiler table is request-local. Needle records its original bounds,
reads only entries created by this compilation, destroys those entries
through Zend's hash-table destructors, and restores the compiler arena to a
checkpoint taken before compilation. This is important because constructing
the snapshot can autoload Needle's own PHP classes into the same Zend tables.

This is intentionally an incomplete bytecode view. It does not execute
include, require, autoloaders, or top-level expressions, and it does not
resolve runtime-dependent declarations. It is sufficient for locating
declared classes, functions, methods, opcode sites, literal constants, and
member names. Source-level/static reflection should supplement it when a
mixin needs information that PHP only determines during linking.

PHPStan uses this bridge through `Communism_PHPStan\BytecodeIndex`. For an
inherited target method it first asks PHPStan for the method's declaring class,
then compiles that class's source file. The static rule can therefore validate
that an `At` point actually exists in the target body without loading or
executing the target file.

Needle is an internal bytecode tool. Normal consumers should continue to use
the Mixin attributes and leave this namespace to tooling and transformation
implementations.
