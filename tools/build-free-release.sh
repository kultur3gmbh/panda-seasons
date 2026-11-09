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
cp "$TARGET" "$ROOT/dist/panda-seasons-free-$VERSION-wordpress-org.zip"
BUILD_DIR="$(mktemp -d)"
trap 'rm -rf "$BUILD_DIR"' EXIT
unzip -q "$TARGET" -d "$BUILD_DIR"
cp "$ROOT/tools/github/class-panda-public-updater.php" "$BUILD_DIR/panda-seasons-free/includes/"
cp "$ROOT/tools/github/free-bootstrap.php" "$BUILD_DIR/panda-seasons-free/includes/class-panda-public-bootstrap.php"
( cd "$BUILD_DIR" && zip -qr "$TARGET" panda-seasons-free )
unzip -tq "$TARGET"
SHA256="$(shasum -a 256 "$TARGET" | awk '{print $1}')"
printf '{"slug":"panda-seasons-free","version":"%s","requires":"6.4","requires_php":"7.4","tested":"6.4","sha256":"%s"}\n' "$VERSION" "$SHA256" > "$ROOT/dist/panda-seasons-free-update.json"
printf '%s\n' "$TARGET"

