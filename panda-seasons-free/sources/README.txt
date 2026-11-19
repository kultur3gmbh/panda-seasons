Panda Seasons Lite — readable JavaScript sources

Every JavaScript file in assets/ has a complete readable copy of the same name
in sources/. All are editable GPLv2-or-later source, not generated bundles.
The browser loads assets/; sources/ is included for inspection and editing.
There is no transpiler, bundler, external dependency download or minification
step required to run this plugin. Shared Panda runtime files remain unchanged.

The copies were expanded with Prettier 3.6.2 (Babel parser):
  npx prettier@3.6.2 --parser babel --single-quote --use-tabs --print-width 100 assets/FILE.js > sources/FILE.js
Repeat for each JavaScript file listed in manifest.json.

Formatting normalizes indentation, quotes and optional semicolons. Each pair
was parsed with Prettier's Babel parser; syntax trees match after removing
source positions, formatting metadata, comments and empty statements. Hashes
of both files are recorded in manifest.json. Comments remain in the readable
files. If editing the readable copy, copy the changed implementation to its
assets/ counterpart too, then regenerate the copies and manifest.

The optional formatter is only a developer tool; it is not bundled or loaded
by the plugin. Its source and license are at https://github.com/prettier/prettier.
