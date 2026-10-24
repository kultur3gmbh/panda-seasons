=== Panda Seasons ===
Contributors: kultur3gmbh
Tags: calendar, advent calendar, seasonal campaign, email calendar
Requires at least: 6.4
Requires PHP: 7.4
Stable tag: 0.5.7
License: GPLv2 or later

Interactive seasonal calendars with freely positioned doors on responsive desktop and mobile images. Web calendars work independently; email delivery requires the free ContactPanda plugin.

== Description ==

Create multiple parallel calendars in a visual drag-and-drop composer, schedule individual doors, use separate desktop and mobile layouts, open door content in a pop-up, and optionally deliver each day's content by email through ContactPanda.

Use `[panda_seasons id="123"]` to display a published calendar.

== Changelog ==

= 0.5.7 =
* Simplifies the calendar toolbar to closed, opened and preview controls with a clearer two-row tool layout.
* Refines desktop and mobile cover cards with 4:3 previews and aligned ratio and background controls.
* Reorganizes new-door defaults into balanced rows and removes redundant animation help text.
* Streamlines media, download and call-to-action fields with type-dependent controls.
* Completes formal German call-to-action translations and clarifies that apply-to-all changes styles only.
* Verifies that selected door media is saved and rendered in the central content popup.

= 0.5.6 =
* Streamlined the calendar editor, cover image selection, grid alignment and design-element saving.
* Added a verified, refreshable GitHub template library that imports layout-only draft calendars.
* Improved calendar email guidance, CTA controls and German formal translations.

= 0.5.5 =
* Reorganizes the general defaults and compact cover-image controls as requested.
* Adds a true 10-pixel editor grid and a rich-text editor with heading formats for text areas.
* Clarifies the selected door in the inspector and adds reliable save, cancel, apply-to-all and trash actions.
* Adds calendar-specific ContactPanda tags and signup origin context, including optional door details.
* Adds compatibility with both ContactPanda and the earlier ContactHub newsletter shortcode.

= 0.5.4 =
* Limits the door inspector to a clean maximum of two controls per row with aligned field heights.
* Adds independent nine-point label positions for the closed and opened states.
* Adds date and time simulation to the protected preview and removes the WordPress admin bar from it.
* Adds heading formats to the visual door-content editor.
* Orders each door editor as basics, appearance, content type, matching content, call to action and calendar email.
* Compacts the desktop, mobile and content-window settings into aligned rounded cards.
* Keeps the publishing controls visible above both main editor tabs.

= 0.5.3 =
* Uses one shared background image per door with separate closed/open overlays and nine image positions.
* Adds draggable design areas that combine a background color with an optional positioned image.
* Reorganizes the door editor, hides unavailable integrations and adds a content heading with a release-date fallback.
* Adds rectangle, rounded, square and round label backgrounds with automatic, pixel or percentage width.
* Adds separate desktop and mobile canvas background colors and a compact 60:40 defaults layout.
* Displays popup content in a responsive image-and-text layout and adds an appearance action for all doors.

= 0.5.2 =
* Places the image-save action below both cover images and moves content-window styling directly below it in the left settings column.
* Adds centered and four-corner positions for the compact opened-door number, including its background.
* Adds central closed/open color-overlay defaults for door background images and individual per-door overrides.
* Applies image overlays only when the matching door background image exists.

= 0.5.1 =
* Adds draggable and resizable image, text, and color layers with separate desktop and mobile positions.
* Adds a manual image-refresh action for desktop and mobile cover images and cache-safe protected previews.
* Adds three clear opening modes: manual on release day, automatic on release day, and permanently open from release date.
* Adds optional compact door numbers in a chosen corner after opening.
* Adds configurable colors, border, spacing, radius, and width for the door content window.
* Opens the content window immediately when an already opened door is clicked again.

= 0.5.0 =
* Splits the visual editor into a focused calendar workspace and a separate general-defaults tab without losing unsaved changes.
* Refreshes newly selected desktop and mobile cover images immediately in both the editor and the protected preview.
* Adds a selective old-value/new-value dialog for applying chosen global defaults to existing doors.
* Adds automatic, pixel-based, and percentage-based round label backgrounds for closed and opened doors.
* Separates door content into general, video, product, and quiz modes while keeping calls to action independently available.
* Imports content into existing calendars with fill-empty, merge, and full-overwrite modes while preserving layout and appearance.
* Extends the MCP document workflow with the same merge modes and content-type setting.

= 0.4.9 =
* Ensures Panda Seasons MCP abilities remain available when another plugin initializes WordPress ability categories early.

= 0.4.8 =
* Register Panda Seasons abilities early enough for MCP discovery on WordPress 6.9 and newer.

= 0.4.7 =
* Adds an MCP workflow for creating a layout-only draft from an analyzed reference image and normalized door rectangles.
* Accepts closed, opened, active, border, label, typography, shape and animation defaults while creating the layout.
* Generates a separate three-column mobile layout when no mobile rectangles are supplied.
* Adds a second MCP step that applies normalized Word-document content to existing doors by number without changing their positions.
* Keeps generated calendars as drafts for visual review before publication.

= 0.4.6 =
* Keeps the door editor actions permanently visible in the right-hand inspector.
* Shows newly selected door media immediately in the content preview and explains where that media appears.
* Adds secure WordPress Abilities for MCP discovery, calendar reading and editing, door creation, editing, duplication and deletion, and template export.
* Excludes subscriber records and personal newsletter-registration data from all MCP responses.
* Remains fully compatible with WordPress installations that do not provide the Abilities API.

= 0.4.5 =
* Adds a first calendar-template module for exporting calendars and importing them as new drafts on another installation.
* Transfers calendar settings, doors, positions, content and reachable media while resetting installation-specific product, coupon and event assignments.
* Changes the general editor layout to a 60:40 split so the settings column never exceeds the image column.
* Restores labels above every color field and refines their typography.
* Reduces the complete color picker by another 30 percent and prevents Safari from clipping the transparency control.
* Further reduces the global-theme icon while retaining its accessible click target.

= 0.4.4 =
* Corrects the Safari transparency control and keeps it exactly aligned with the hue control.
* Reduces the complete color picker, its text, global-color icon, spacing, and palette by about 22 percent.
* Reuses the exact theme colors already detected and displayed by ContactPanda.
* Fixes deletion from the compact actions attached directly to each door.
* Keeps label-visibility checkboxes compact regardless of active theme admin styles.
* Reorganizes every individual door state into a clear single-column inspector without horizontal overflow.
* Completes the formal German translation of the door-label explanation and corrects door terminology.

= 0.4.3 =
* Reorganizes default and individual door appearance settings into compact, consistently aligned rows.
* Moves label visibility into the matching closed or opened state tab and conditionally shows percentage width.
* Places global-theme color access inside every color-code field and reduces the overall control size.
* Moves general shape, animation, and current-door highlighting settings above the state tabs.
* Adds compact duplicate and delete actions to the upper-right edge of every door in the visual editor.
* Removes the separate opening-effect sample door in favor of the interactive calendar preview.

= 0.4.2 =
* Rebuilds all calendar color fields as compact Avada-style RGBA pickers with immediate full controls.
* Adds a separate globe button for the global colors of the design source selected in ContactPanda Basic.
* Adds the explanatory tooltip “Globale Farbeinstellungen” to the global-color button.
* Makes Avada calendar elements fill the complete width of their containing column.
* Places label visibility above the state tabs and aligns door border color and width in one row.
* Adds independent label backgrounds, borders, padding, and content-based or percentage-based widths.

= 0.4.1 =
* Adds a centered, closable interactive preview with desktop and mobile sizes.
* Adds release-day-only door availability alongside permanent availability from the release date.
* Refines the compact RGBA color picker and improves Safari opening-effect previews.
* Moves general opening controls into the right-hand defaults panel and normalizes German door terminology.

= 0.4.0 =
* Adds a protected interactive preview that simulates door opening while disabling live actions.
* Adds per-door post-unlock behavior: open automatically, highlight until clicked, or remain closed until clicked.
* Reorganizes cover images, ratios, defaults, and publishing controls for a denser editor layout.
* Refines every color field with saturation, hue, and vertical transparency controls plus theme palettes.
* Adds a configurable active-door border color and simplifies the default typography and color layout.

= 0.3.9 =
* Adds native calendar selection elements for Gutenberg, Elementor, Divi, and Avada.
* Shows copyable calendar and signup shortcodes beside the WordPress publish panel.
* Moves the animated sample door next to the opening settings and simplifies visible color labels.

= 0.3.8 =
* Adds configurable label typography, visibility, RGBA colors, and per-state door borders.
* Introduces a reusable expandable color picker with transparency and global theme colors.
* Organizes default colors into closed and opened tabs with an animated sample-door preview.
* Restores the standard WordPress publish and update panel inside the full-screen calendar editor.

= 0.3.7 =
* Adds three-column defaults, alpha color controls, clearer state tabs, live previews, per-door calls to action, and WooCommerce coupons.

= 0.3.6 =
* Fixes preview controls, adds theme-aware default door colors, keeps the selected door open after applying settings, and completes formal German interface text.

= 0.3.5 =
* Rebuilds the visual calendar editor as a large embedded two-column workspace with live door previews.

= 0.3.4 =
* Adds a full-width door editor, drag-to-create doors, RGBA state styling, conditional email content, and multi-question quizzes.

= 0.3.3 =
* Moved and reformatted email delivery and signup settings for the editor sidebar.
* Placed unlock date and time in one responsive row.
* Replaced invalid relative media previews with a clear media dropzone.
* Added configurable door-opening and page-turn animations with reduced-motion support.

= 0.3.2 =
* Fixed a PHP parse error in the standalone updater introduced in version 0.3.1.

= 0.3.1 =
* Replaced the door editing pop-up with a responsive editor panel beside the calendar.
* Added actual, all-closed and all-open design previews.
* Added square, rounded and round door shapes, adjustable background opacity and optional state images.
* Added standalone browser-based multiple-choice quizzes for individual doors.
* Added an independent email-calendar signup shortcode for advance registration.
* Added optional newsletter links, external form shortcodes and the separate ContactPanda newsletter form.
* ContactPanda-dependent frontend areas now stay hidden when the dependency is unavailable.
* Added a standalone WordPress update channel with SHA-256 package verification.

= 0.3.0 =
* Added complete door content editing in the visual composer.
* Added optional unlock times in the site timezone.
* Added image, video, audio, downloads and linked buttons.
* Added optional WooCommerce product and event links.
* Added separate rich content for each calendar email.
* Added configurable door labels and colors before and after opening.
* Added an administrative preview that works before the public unlock time.

= 0.2.0 =
* Added a visual drag-and-drop calendar composer.
* Added freely movable and resizable doors.
* Added separate desktop and mobile door layouts.
* Added door content editing in an accessible pop-up.
* Added front-end door content pop-ups.
* Existing calendars receive safe automatic starting positions.

= 0.1.0 =
* Initial release.
