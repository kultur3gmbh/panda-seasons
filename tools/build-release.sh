#!/usr/bin/env bash
set -euo pipefail
repo_dir="$(cd "$(dirname "$0")/.." && pwd)"
detected_version="$(awk -F': ' '/^ \* Version:/{print $2; exit}' "$repo_dir/panda-seasons/panda-seasons.php")"
version="${1:-$detected_version}"
if [[ -z "$version" ]]; then
	echo "Could not read the Panda Seasons version." >&2
	exit 1
fi
archive="$repo_dir/dist/panda-seasons-$version.zip"
manifest="$repo_dir/dist/panda-seasons-update.json"
cd "$repo_dir"
mkdir -p "$repo_dir/dist"
rm -f "$archive" "$manifest"
zip -qr "$archive" panda-seasons -x '*.DS_Store'
sha256="$(shasum -a 256 "$archive" | awk '{print $1}')"
cat > "$manifest" <<EOF
{
  "slug": "panda-seasons",
  "version": "$version",
  "requires": "6.4",
  "tested": "7.1.3",
  "requires_php": "7.4",
  "url": "https://github.com/kultur3gmbh/panda-seasons/releases/tag/v$version",
  "download_url": "https://github.com/kultur3gmbh/panda-seasons/releases/download/v$version/panda-seasons-$version.zip",
  "sha256": "$sha256"
}
EOF
echo "$archive"
echo "$manifest"
