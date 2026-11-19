#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SOURCE="$ROOT/panda-seasons-free"
VERSION="$(awk -F': ' '/^ \* Version:/{print $2; exit}' "$SOURCE/panda-seasons-free.php")"
if [[ -n "${1:-}" && "$1" != "$VERSION" ]]; then
  echo "Requested version must match the plugin header." >&2
  exit 1
fi
python3 "$ROOT/tools/compile_mo.py" "$SOURCE/languages/panda-seasons-free-de_DE.po" "$SOURCE/languages/panda-seasons-free-de_DE.mo"
python3 "$ROOT/tools/compile_mo.py" "$SOURCE/languages/panda-seasons-free-de_DE_formal.po" "$SOURCE/languages/panda-seasons-free-de_DE_formal.mo"
python3 "$ROOT/tools/build-lite-release.py"
