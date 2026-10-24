#!/usr/bin/env bash
set -euo pipefail
repo_dir="$(cd "$(dirname "$0")/.." && pwd)"
version="${1:-0.4.7}"
archive="$repo_dir/dist/panda-seasons-$version.zip"
cd "$repo_dir"
rm -f "$archive"
zip -qr "$archive" panda-seasons -x '*.DS_Store'
echo "$archive"
