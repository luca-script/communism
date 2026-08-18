# Crash diagnostics helper

This directory contains the deliberately small crash harness used by CI.

The harness is invoked by the coverage job for both backends:

```text
tools/crash-diagnostics/run.sh coverage.php zendful
tools/crash-diagnostics/run.sh coverage.php communism
```

On Windows, `run.ps1` discovers a Visual Studio installation with the x64
C++ toolchain through `vswhere.exe`; it does not depend on a fixed Visual
Studio version or edition path. The harness also requires PHP's FFI extension,
because the preload script installs the native crash handler through FFI.

The Linux and Windows implementations are separate because signal handling,
exception handling, and native stack walking are platform-specific. CMake
selects the implementation and uses the runner's system compiler.

The PHP preload only installs the native handler. The native handler reads its
configuration from the environment and writes a report only when
`ZENDFUL_CRASH_DIAGNOSTICS_DUMP_PATH` is configured; it always prints the
report to stderr.

The complete Zend executor chain is debugger-owned. On Linux, source
php-src/.gdbinit and call zbacktrace after ____executor_globals. On Windows,
tools/cdb-backtrace-php.txt uses CDB to call PHP's debug-backtrace functions
from the stopped process. Those paths can inspect current_execute_data
directly because the debugger has the PHP symbols and the exact Zend ABI.

The helper's crash handlers are intentionally a CI diagnostic mechanism, not
a replacement for production crash reporting. They must not be used from
normal application code.
