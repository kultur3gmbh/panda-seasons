#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
VERSION="${1:-0.1.1}"
SOURCE="$ROOT/panda-seasons-free"
TARGET="$ROOT/dist/panda-seasons-free-$VERSION.zip"

python3 "$ROOT/tools/compile_mo.py" "$SOURCE/languages/panda-seasons-free-de_DE.po" "$SOURCE/languages/panda-seasons-free-de_DE.mo"
python3 "$ROOT/tools/compile_mo.py" "$SOURCE/languages/panda-seasons-free-de_DE_formal.po" "$SOURCE/languages/panda-seasons-free-de_DE_formal.mo"
rm -f "$TARGET"
cd "$ROOT"
zip -qr "$TARGET" panda-seasons-free -x '*.DS_Store' '*.map'
unzip -tq "$TARGET"
printf '%s\n' "$TARGET"

