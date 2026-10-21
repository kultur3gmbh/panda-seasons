#!/usr/bin/env python3
from pathlib import Path

root = Path(__file__).resolve().parent.parent / "panda-seasons"
required = [
    "panda-seasons.php",
    "includes/class-panda-seasons-plugin.php",
    "includes/class-panda-seasons-schema.php",
    "includes/class-panda-seasons-subscriptions.php",
    "includes/class-panda-seasons-mailer.php",
    "includes/class-panda-seasons-updater.php",
    "assets/frontend.css",
    "assets/frontend.js",
]
for filename in required:
    if not (root / filename).exists():
        raise SystemExit(f"Missing {filename}")
plugin = (root / "includes/class-panda-seasons-plugin.php").read_text(encoding="utf-8")
updater = (root / "includes/class-panda-seasons-updater.php").read_text(encoding="utf-8")
subscriptions = (root / "includes/class-panda-seasons-subscriptions.php").read_text(encoding="utf-8")
if "panda_seasons" not in plugin or "_ps_cover_mobile" not in plugin:
    raise SystemExit("Calendar contract missing")
for composer_contract in ("_ps_position_desktop", "_ps_position_mobile", "data-door-modal", "panda-seasons__canvas"):
    if composer_contract not in plugin:
        raise SystemExit(f"Missing visual composer contract {composer_contract}")
for content_contract in (
    "_ps_unlock_time",
    "_ps_media_id",
    "_ps_download_id",
    "_ps_button_url",
    "_ps_product_id",
    "_ps_event_id",
    "_ps_email_content",
    "_ps_closed_background",
    "data-preview-door",
	"_ps_closed_opacity",
	"_ps_closed_image_id",
	"_ps_quiz_question",
	"panda_seasons_subscribe",
	"_ps_newsletter_mode",
):
    if content_contract not in plugin:
        raise SystemExit(f"Missing door content contract {content_contract}")
for scope in ("calendar", "all_calendars", "all_mailings"):
    if scope not in subscriptions:
        raise SystemExit(f"Missing unsubscribe scope {scope}")
for update_contract in ("releases/latest", "panda-seasons-update.json", "hash_file( 'sha256'", "upgrader_pre_download"):
    if update_contract not in updater:
        raise SystemExit(f"Missing standalone updater contract {update_contract}")
