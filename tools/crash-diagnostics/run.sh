#!/usr/bin/env bash
set -euo pipefail

project_root=$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)
build_directory="$project_root/tmp/crash-diagnostics"
mkdir -p "$build_directory"

library="$build_directory/libzendful_crash_diagnostics.so"
source="$project_root/tools/crash-diagnostics/linux.c"
if [[ ! -f "$library" || "$source" -nt "$library" ]]; then
  cc -shared -fPIC -std=c17 "$source" -o "$library" -ldl
fi

export ZENDFUL_CRASH_DIAGNOSTICS_LIBRARY="$library"
exec php -d auto_prepend_file="$project_root/tools/crash-diagnostics/preload.php" "$@"
