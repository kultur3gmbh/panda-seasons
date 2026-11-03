#!/usr/bin/env python3
from pathlib import Path

root = Path(__file__).resolve().parents[1]
plugin = root / "panda-seasons-free"
main = (plugin / "panda-seasons-free.php").read_text()
code = (plugin / "includes/class-panda-seasons-free-plugin.php").read_text()
readme = (plugin / "readme.txt").read_text()
admin_css = (plugin / "assets/admin.css").read_text()
shared_css = (plugin / "assets/contactpanda-admin-ui.css").read_text()
editor_ui = (plugin / "assets/panda-editor-ui.js").read_text()

assert "Plugin Name: Panda Seasons Free" in main
assert "Version: 0.1.11" in main
assert "Update URI:" not in main
assert "Stable tag: 0.1.11" in readme
assert "panda-seasons/panda-seasons.php" in main
assert "panda_seasons_free_autoload" in main
assert "if ( ! is_admin() ) return;" in code
assert "'posts_per_page' => 100" in code
assert "'no_found_rows'  => true" in code
assert "'posts_per_page' => -1" not in code
assert "_ps_cover_desktop" in code
assert "_ps_position_desktop" in code
assert "_ps_position_mobile" in code
assert "_ps_default_shape" in code
assert "_ps_default_closed_border_color" in code
assert "_ps_default_open_border_color" in code
assert "contactpanda-admin-ui.js" in code
assert "contactpanda-admin-ui.css" in code
assert "data-position-field=\"x\"" in code
assert "data-position-field=\"w\"" in code
assert "Panda_Seasons_Free_Updater" not in main
assert not (plugin / "includes/class-panda-seasons-free-updater.php").exists()
assert "Panda Seasons', 'panda-seasons-free'" in code
assert "data-template=\"classic\"" in code
assert "data-template=\"modern\"" in code
assert "panda-seasons-free-dashboard__template-gallery" not in code
assert "panda-seasons-free-dashboard__comparison-table" not in code
assert "panda-seasons-free-dashboard__upgrade" in code
assert "_ps_content_type', 'general'" in code
assert "if ( $is_new )" in code
assert "'panda-seasons/calendar'" in code
for editor_contract in (
    'data-panda-editor-tab="elements"',
    'data-panda-editor-tab="edit"',
    'data-panda-editor-panel="elements"',
    'data-panda-editor-panel="edit"',
    'data-panda-editor-element',
    'panda-seasons-free-sidebar',
):
    assert editor_contract in code, editor_contract
assert ".panda-seasons-free-sidebar" in admin_css
assert ".panda-seasons-free-editor-door" in editor_ui
assert "canvas.scrollTop = canvasScroll" in editor_ui
assert "window.scrollTo(window.scrollX, pageScroll)" in editor_ui
assert ".contactpanda-unified-color-popover{grid-template-columns:minmax(0,1fr) 18px 18px" in shared_css
assert ".contactpanda-unified-color-square{width:100%!important;height:auto!important;min-height:112px!important;aspect-ratio:1}" in shared_css
for forbidden in ("wc_get_product", "quiz_enabled", "contactpanda_available", "_ps_media_id", "_ps_door_image_id"):
    assert forbidden not in code, forbidden

print("Panda Seasons Free structure checks passed")

