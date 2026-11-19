#!/usr/bin/env python3
"""Build Lite's separate WordPress.org and public-update distributions."""
from pathlib import Path
import argparse
import hashlib
import json
import re
import shutil
import zipfile

ROOT = Path(__file__).resolve().parents[1]
SOURCE = ROOT / 'panda-seasons-free'
parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('--output', type=Path, default=Path('/Users/matthias/Documents/Codex/Outputs'))
parser.add_argument('--review-only', action='store_true')
args = parser.parse_args()
output = args.output
output.mkdir(parents=True, exist_ok=True)
main = (SOURCE / 'panda-seasons-free.php').read_text()
version = re.search(r'^ \* Version: ([0-9.]+)$', main, re.M).group(1)
assert "PANDA_SEASONS_FREE_VERSION', '" + version + "'" in main
assert 'Plugin Name: Panda Seasons Lite' in main
assert 'Stable tag: ' + version in (SOURCE / 'readme.txt').read_text()
for item in json.loads((SOURCE / 'sources/manifest.json').read_text())['files']:
    for kind in ('runtime', 'readable'):
        assert hashlib.sha256((SOURCE / item[kind]).read_bytes()).hexdigest() == item[kind + '_sha256']

bootstrap = """// Included only in the GitHub distribution; WordPress.org remains independent.
if ( is_readable( PANDA_SEASONS_FREE_PATH . 'includes/class-panda-public-bootstrap.php' ) ) {
\trequire_once PANDA_SEASONS_FREE_PATH . 'includes/class-panda-public-bootstrap.php';
}

"""
assert main.count(bootstrap) == 1
base = {}
for file in sorted(SOURCE.rglob('*')):
    if not file.is_file() or file.name == '.DS_Store' or file.suffix in {'.map', '.pyc'}:
        continue
    relative = str(file.relative_to(ROOT))
    assert 'class-panda-public' not in file.name
    base[relative] = file.read_bytes()
review = {name: data for name, data in base.items() if not name.endswith(('.po', '.mo'))}
review['panda-seasons-free/panda-seasons-free.php'] = main.replace(bootstrap, '').encode()
public = dict(base)
public['panda-seasons-free/includes/class-panda-public-updater.php'] = (ROOT / 'tools/github/class-panda-public-updater.php').read_bytes()
public['panda-seasons-free/includes/class-panda-public-bootstrap.php'] = (ROOT / 'tools/github/free-bootstrap.php').read_bytes()

def write(name, files):
    target = output / name
    with zipfile.ZipFile(target, 'w', compression=zipfile.ZIP_DEFLATED) as package:
        for relative, data in sorted(files.items()):
            info = zipfile.ZipInfo(relative, (2026, 11, 19, 0, 0, 0))
            info.compress_type = zipfile.ZIP_DEFLATED
            info.external_attr = 0o100644 << 16
            package.writestr(info, data)
    with zipfile.ZipFile(target) as package:
        assert package.testzip() is None
    sha = hashlib.sha256(target.read_bytes()).hexdigest()
    target.with_suffix(target.suffix + '.sha256').write_text(sha + '  ' + name + '\n')
    return {'file': name, 'sha256': sha, 'bytes': target.stat().st_size}

if args.review_only:
    print(json.dumps(write(f'panda-seasons-lite-{version}-wordpress-org-review.zip', review), indent=2))
else:
    artifacts = [write(f'panda-seasons-free-{version}-wordpress-org.zip', review), write(f'panda-seasons-free-{version}.zip', public)]
    for item in list(artifacts):
        name = item['file'].replace('panda-seasons-free-', 'panda-seasons-lite-', 1)
        shutil.copyfile(output / item['file'], output / name)
        (output / (name + '.sha256')).write_text(item['sha256'] + '  ' + name + '\n')
        artifacts.append(dict(item, file=name))
    update = {'slug': 'panda-seasons-free', 'version': version, 'requires': '6.4', 'requires_php': '7.4', 'tested': '7.1', 'sha256': artifacts[1]['sha256']}
    (output / 'panda-seasons-free-update.json').write_text(json.dumps(update, separators=(',', ':')) + '\n')
    (output / f'panda-seasons-lite-{version}-release-artifacts.json').write_text(json.dumps({'product': 'Panda Seasons Lite', 'version': version, 'artifacts': artifacts, 'update': update}, indent=2) + '\n')
    print(json.dumps(artifacts, indent=2))
