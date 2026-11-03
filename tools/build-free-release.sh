#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SOURCE="$ROOT/panda-seasons-free"
DETECTED_VERSION="$(awk -F': ' '/^ \* Version:/{print $2; exit}' "$SOURCE/panda-seasons-free.php")"
VERSION="${1:-$DETECTED_VERSION}"
if [[ -z "$VERSION" ]]; then
	echo "Could not read the Panda Seasons Free version." >&2
	exit 1
fi
TARGET="$ROOT/dist/panda-seasons-free-$VERSION.zip"

mkdir -p "$ROOT/dist"
python3 "$ROOT/tools/compile_mo.py" "$SOURCE/languages/panda-seasons-free-de_DE.po" "$SOURCE/languages/panda-seasons-free-de_DE.mo"
python3 "$ROOT/tools/compile_mo.py" "$SOURCE/languages/panda-seasons-free-de_DE_formal.po" "$SOURCE/languages/panda-seasons-free-de_DE_formal.mo"
rm -f "$TARGET"
cd "$ROOT"
zip -qr "$TARGET" panda-seasons-free -x '*.DS_Store' '*.map'
unzip -tq "$TARGET"
printf '%s\n' "$TARGET"

