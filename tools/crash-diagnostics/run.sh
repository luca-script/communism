#!/usr/bin/env bash
set -euo pipefail

project_root=$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)
build_directory="$project_root/tmp/crash-diagnostics"
mkdir -p "$build_directory"

library="$build_directory/libzendful_crash_diagnostics.so"
source="$project_root/tools/crash-diagnostics/linux.c"
if ! command -v php-config >/dev/null 2>&1; then
  echo 'php-config is required to locate the PHP headers.' >&2
  exit 1
fi
php_include_flags=$(php-config --includes)
if [[ ! -f "$library" || "$source" -nt "$library" ]]; then
  # php-config points at the headers belonging to the active PHP binary.
  # The repository's local php-src checkout is intentionally not required.
  cc -shared -fPIC -std=c17 $php_include_flags "$source" -o "$library" -ldl
fi

export ZENDFUL_CRASH_DIAGNOSTICS_LIBRARY="$library"
exec php -d auto_prepend_file="$project_root/tools/crash-diagnostics/preload.php" "$@"
