#!/usr/bin/env bash
set -euo pipefail
repo_dir="$(cd "$(dirname "$0")/.." && pwd)"
plugin_dir="$repo_dir/panda-seasons"
test -f "$plugin_dir/panda-seasons.php"
grep -q '^ \* Version: 0.7.8$' "$plugin_dir/panda-seasons.php"
grep -q '^Stable tag: 0.7.8$' "$plugin_dir/readme.txt"
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
echo "Panda Seasons checks passed."
