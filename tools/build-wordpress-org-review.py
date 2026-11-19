#!/usr/bin/env python3
"""Prepare a review-only archive, without changing versions or public releases."""
from pathlib import Path
import hashlib
import json
import re
import zipfile

ROOT = Path(__file__).resolve().parents[1]
SOURCE = ROOT / "panda-seasons-free"
OUTPUT = Path("/Users/matthias/Documents/Codex/Outputs")
header = (SOURCE / "panda-seasons-free.php").read_text()
version = re.search(r"^ \* Version: ([0-9.]+)$", header, re.M).group(1)
assert "Plugin Name: Panda Seasons Lite" in header
assert f"Stable tag: {version}" in (SOURCE / "readme.txt").read_text()
runtime = (SOURCE / "assets/contactpanda-admin-ui.js").read_text()
readable = (SOURCE / "sources/contactpanda-admin-ui.js").read_text()
assert re.sub(r"\s", "", runtime) == re.sub(r"\s", "", readable)

# Keep the existing submission slug/text domain until the reviewer reserves a new one.
archive = OUTPUT / f"panda-seasons-basic-{version}-wordpress-org-review.zip"
excluded_names = {"class-panda-public-bootstrap.php", "class-panda-public-updater.php", ".DS_Store"}
OUTPUT.mkdir(parents=True, exist_ok=True)
with zipfile.ZipFile(archive, "w", compression=zipfile.ZIP_DEFLATED) as package:
    for file in sorted(SOURCE.rglob("*")):
        if not file.is_file() or file.name in excluded_names or file.suffix in {".po", ".mo", ".map"}:
            continue
        package.write(file, file.relative_to(ROOT))
with zipfile.ZipFile(archive) as package:
    assert package.testzip() is None
    assert all(not name.endswith((".po", ".mo")) for name in package.namelist())
    assert "panda-seasons-free/sources/contactpanda-admin-ui.js" in package.namelist()

manifest = {
    "product": "Panda Seasons Lite", "version": version,
    "status": "local review preparation; not uploaded or published",
    "technical_slug": "panda-seasons-free",
    "name_status": "Basic/Plus requested by Matthias; WordPress.org acceptance not confirmed",
    "sha256": hashlib.sha256(archive.read_bytes()).hexdigest(),
    "language_pack_note": "PO/MO omitted only from this variant; English fallback until WordPress language packs exist",
}
archive.with_suffix(".json").write_text(json.dumps(manifest, indent=2) + "\n")
print(archive)
