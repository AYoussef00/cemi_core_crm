#!/usr/bin/env bash
# Start Laravel with a project-writable PHP upload temp (fixes macOS/sandbox
# "unable to create a temporary file" on multipart uploads).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
TMP="$ROOT/storage/app/php-upload-tmp"
mkdir -p "$TMP"
export TMPDIR="$TMP"
export TMP="$TMP"
export TEMP="$TMP"
cd "$ROOT"
exec php -d "upload_tmp_dir=$TMP" -d "sys_temp_dir=$TMP" artisan serve --host=127.0.0.1 --port="${PORT:-8000}" "$@"
