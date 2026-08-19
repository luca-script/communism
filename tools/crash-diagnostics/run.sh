#!/usr/bin/env bash
set -euo pipefail

project_root=$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)
build_directory="$project_root/tmp/crash-diagnostics"
mkdir -p "$build_directory"

library="$build_directory/libzendful_crash_diagnostics.so"
source="$project_root/tools/crash-diagnostics/linux.c"
php_binary=$(command -v php || true)
if [[ -z "$php_binary" ]]; then
  echo 'php is required to run crash diagnostics.' >&2
  exit 1
fi
php_binary=$(readlink -f "$php_binary")
php_directory=$(dirname "$php_binary")
php_config="$php_directory/php-config"
if [[ ! -x "$php_config" ]]; then
  echo "php-config for the active PHP binary was not found: $php_config" >&2
  exit 1
fi
php_include_flags=$("$php_config" --includes)
if [[ ! -f "$library" || "$source" -nt "$library" ]]; then
  # php-config points at the headers belonging to the active PHP binary.
  # The repository's local php-src checkout is intentionally not required.
  cc -shared -fPIC -std=c17 $php_include_flags "$source" -o "$library" -ldl
fi

export ZENDFUL_CRASH_DIAGNOSTICS_LIBRARY="$library"
if "$php_binary" \
    -d display_errors=1 \
    -d log_errors=1 \
    -d auto_prepend_file="$project_root/tools/crash-diagnostics/preload.php" \
    "$@"; then
  status=0
else
  status=$?
fi
if [[ $status -ne 0 ]]; then
  echo "Crash-diagnostics PHP process exited with status $status." >&2
fi
exit "$status"
