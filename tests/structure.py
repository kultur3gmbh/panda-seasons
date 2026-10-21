#!/usr/bin/env python3
from pathlib import Path

root = Path(__file__).resolve().parent.parent / "panda-seasons"
required = [
    "panda-seasons.php",
    "includes/class-panda-seasons-plugin.php",
    "includes/class-panda-seasons-schema.php",
    "includes/class-panda-seasons-subscriptions.php",
    "includes/class-panda-seasons-mailer.php",
    "assets/frontend.css",
    "assets/frontend.js",
]
for filename in required:
    if not (root / filename).exists():
        raise SystemExit(f"Missing {filename}")
plugin = (root / "includes/class-panda-seasons-plugin.php").read_text(encoding="utf-8")
subscriptions = (root / "includes/class-panda-seasons-subscriptions.php").read_text(encoding="utf-8")
if "panda_seasons" not in plugin or "_ps_cover_mobile" not in plugin:
    raise SystemExit("Calendar contract missing")
for composer_contract in ("_ps_position_desktop", "_ps_position_mobile", "data-door-modal", "panda-seasons__canvas"):
    if composer_contract not in plugin:
        raise SystemExit(f"Missing visual composer contract {composer_contract}")
for scope in ("calendar", "all_calendars", "all_mailings"):
    if scope not in subscriptions:
        raise SystemExit(f"Missing unsubscribe scope {scope}")
