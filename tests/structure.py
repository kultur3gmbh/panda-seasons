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
	"includes/class-panda-seasons-integrations.php",
	"includes/class-panda-seasons-mcp.php",
    "assets/frontend.css",
    "assets/frontend.js",
	"assets/block-editor.js",
]
for filename in required:
    if not (root / filename).exists():
        raise SystemExit(f"Missing {filename}")
plugin = (root / "includes/class-panda-seasons-plugin.php").read_text(encoding="utf-8")
updater = (root / "includes/class-panda-seasons-updater.php").read_text(encoding="utf-8")
integrations = (root / "includes/class-panda-seasons-integrations.php").read_text(encoding="utf-8")
mcp = (root / "includes/class-panda-seasons-mcp.php").read_text(encoding="utf-8")
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
	"_ps_closed_opacity",
	"_ps_door_image_id",
	"_ps_door_image_position",
	"_ps_quiz_question",
	"panda_seasons_subscribe",
	"_ps_newsletter_mode",
	"_ps_unlock_behavior",
	"panda_seasons_preview",
	"_ps_default_active_border_color",
	"_ps_default_closed_image_overlay",
	"_ps_closed_image_overlay",
	"_ps_closed_label_position",
	"preview_date",
	"data-save-door",
	"data-apply-door-appearance",
	"panda_seasons_layer_text_editor",
	"_ps_contact_tag",
	"panda_seasons_contact_signup_context",
):
    if content_contract not in plugin:
        raise SystemExit(f"Missing door content contract {content_contract}")
for scope in ("calendar", "all_calendars", "all_mailings"):
    if scope not in subscriptions:
        raise SystemExit(f"Missing unsubscribe scope {scope}")
for update_contract in ("releases/latest", "panda-seasons-update.json", "hash_file( 'sha256'", "upgrader_pre_download"):
    if update_contract not in updater:
        raise SystemExit(f"Missing standalone updater contract {update_contract}")
for integration_contract in ("register_block_type", "elementor/widgets/register", "et_builder_ready", "fusion_builder_map"):
    if integration_contract not in integrations:
        raise SystemExit(f"Missing builder integration contract {integration_contract}")
for mcp_contract in ("wp_abilities_api_init", "wp_register_ability", "list-calendars", "get-calendar", "save-door", "delete-door", "create-layout-draft", "apply-document-content"):
    if mcp_contract not in mcp:
        raise SystemExit(f"Missing MCP contract {mcp_contract}")
