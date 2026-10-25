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
admin_js = (root / "assets/admin.js").read_text(encoding="utf-8")
admin_css = (root / "assets/admin-041.css").read_text(encoding="utf-8")
frontend_css = (root / "assets/frontend.css").read_text(encoding="utf-8")
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
for media_contract in ("wp_ajax_panda_seasons_save_editor_door", "save_editor_door_ajax", "_ps_media_id"):
    if media_contract not in plugin:
        raise SystemExit(f"Missing persistent door media contract {media_contract}")
for dropzone_contract in ("carriesFiles", "event.stopPropagation()", "data-select-door-media"):
    if dropzone_contract not in admin_js:
        raise SystemExit(f"Missing isolated media dropzone contract {dropzone_contract}")
if ".panda-seasons-door-modal__dropzone.is-dragover" not in admin_css:
    raise SystemExit("Missing visible media dropzone state")
if ".panda-seasons__layer--text{display:flex;flex-direction:column" not in frontend_css:
    raise SystemExit("Text layers must stack headings and paragraphs vertically")
for release_contract in (
	"panda_seasons_door_cta_editor",
	"door_cta_markup",
	"panda-seasons-default-general-grid",
	"Click Add door, Design area or Text area",
	"data-layer-field=\"padding\"",
):
	if release_contract not in plugin:
		raise SystemExit(f"Missing 0.6.0 editor contract {release_contract}")
for layer_contract in ("activeDoor.ctaText = getEditorContent('panda_seasons_door_cta_editor')", "padding: 8"):
	if layer_contract not in admin_js:
		raise SystemExit(f"Missing 0.6.0 editor behavior {layer_contract}")
for frontend_contract in ("--ps-layer-padding", ".panda-seasons__content-layout.has-media", ".panda-seasons__cta{"):
	if frontend_contract not in frontend_css:
		raise SystemExit(f"Missing 0.6.0 frontend layout {frontend_contract}")
for release_contract in ("_ps_default_cta_enabled", "_ps_cta_mode", "data-apply-door-dialog", "Select settings to apply", "Save settings"):
	if release_contract not in plugin:
		raise SystemExit(f"Missing 0.6.1 editor contract {release_contract}")
for behavior_contract in ("data-confirm-apply-door-settings", "ctaMode: 'default'", "updateDefaultCtaVisibility"):
	if behavior_contract not in admin_js:
		raise SystemExit(f"Missing 0.6.1 editor behavior {behavior_contract}")
for release_contract in ("panda-seasons-default-cta__save", "'supports'     => array( 'title', 'thumbnail' )"):
	if release_contract not in plugin:
		raise SystemExit(f"Missing 0.6.2 editor contract {release_contract}")
if ".panda-seasons__modal-content>h3,.panda-seasons__cta h4" not in frontend_css:
	raise SystemExit("Missing 0.6.2 equal popup heading sizes")
for update_contract in ("releases/latest", "panda-seasons-update.json", "hash_file( 'sha256'", "upgrader_pre_download", "maybe_clear_forced_check_cache", "clear_update_cache", "version_compare( $version, $this->version, '>' )"):
    if update_contract not in updater:
        raise SystemExit(f"Missing standalone updater contract {update_contract}")
for integration_contract in ("register_block_type", "elementor/widgets/register", "et_builder_ready", "fusion_builder_map"):
    if integration_contract not in integrations:
        raise SystemExit(f"Missing builder integration contract {integration_contract}")
for mcp_contract in ("wp_abilities_api_init", "wp_register_ability", "list-calendars", "get-calendar", "save-door", "delete-door", "create-layout-draft", "apply-document-content"):
    if mcp_contract not in mcp:
        raise SystemExit(f"Missing MCP contract {mcp_contract}")
