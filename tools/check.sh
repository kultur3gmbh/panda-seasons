#!/usr/bin/env bash
set -euo pipefail
repo_dir="$(cd "$(dirname "$0")/.." && pwd)"
plugin_dir="$repo_dir/panda-seasons"
free_dir="$repo_dir/panda-seasons-free"
test -f "$plugin_dir/panda-seasons.php"
header_version="$(awk -F': ' '/^ \* Version:/{print $2; exit}' "$plugin_dir/panda-seasons.php")"
constant_version="$(sed -n "s/^define( 'PANDA_SEASONS_VERSION', '\([^']*\)' );/\1/p" "$plugin_dir/panda-seasons.php" | head -1)"
stable_version="$(awk -F': ' '/^Stable tag:/{print $2; exit}' "$plugin_dir/readme.txt")"
test -n "$header_version"
test "$header_version" = "$constant_version"
test "$header_version" = "$stable_version"
grep -R "Text Domain: panda-seasons" "$plugin_dir/panda-seasons.php" >/dev/null
for translation_file in \
  "$plugin_dir/languages/panda-seasons-de_DE.po" \
  "$plugin_dir/languages/panda-seasons-de_DE.mo" \
  "$plugin_dir/languages/panda-seasons-de_DE_formal.po" \
  "$plugin_dir/languages/panda-seasons-de_DE_formal.mo"; do
  test -f "$translation_file"
done
if command -v php >/dev/null 2>&1; then
	find "$plugin_dir" -name '*.php' -print0 | xargs -0 -n1 php -l >/dev/null
fi
python3 "$repo_dir/tools/check_php_structure.py" "$plugin_dir"
python3 "$repo_dir/tests/structure.py"
python3 "$repo_dir/tests/consolidation.py"
test -f "$free_dir/panda-seasons-free.php"
free_header_version="$(awk -F': ' '/^ \* Version:/{print $2; exit}' "$free_dir/panda-seasons-free.php")"
free_constant_version="$(sed -n "s/^define( 'PANDA_SEASONS_FREE_VERSION', '\([^']*\)' );/\1/p" "$free_dir/panda-seasons-free.php" | head -1)"
free_stable_version="$(awk -F': ' '/^Stable tag:/{print $2; exit}' "$free_dir/readme.txt")"
test "$free_header_version" = "$free_constant_version"
test "$free_header_version" = "$free_stable_version"
test -f "$free_dir/languages/panda-seasons-free-de_DE.po"
test -f "$free_dir/languages/panda-seasons-free-de_DE.mo"
test -f "$free_dir/languages/panda-seasons-free-de_DE_formal.po"
test -f "$free_dir/languages/panda-seasons-free-de_DE_formal.mo"
if command -v php >/dev/null 2>&1; then
	find "$free_dir" -name '*.php' -print0 | xargs -0 -n1 php -l >/dev/null
fi
python3 "$repo_dir/tools/check_php_structure.py" "$free_dir"
python3 "$repo_dir/tests/free_structure.py"
echo "Panda Seasons checks passed."
