=== Panda Seasons ===
Contributors: kultur3gmbh
Tags: calendar, advent calendar, seasonal campaign, email calendar
Requires at least: 6.4
Requires PHP: 7.4
Stable tag: 0.8.3
License: GPLv2 or later

Interactive seasonal calendars with freely positioned doors on responsive desktop and mobile images. Web calendars work independently; email delivery requires the free ContactPanda plugin.

== Description ==

Create multiple parallel calendars in a visual drag-and-drop composer, schedule individual doors, use separate desktop and mobile layouts, open door content in a pop-up, and optionally deliver each day's content by email through ContactPanda.

Use `[panda_seasons id="123"]` to display a published calendar. The optional attributes `show_title="no"`, `frame="yes"` and `shadow="yes"` control the presentation of each individual embed.

== Changelog ==

= 0.8.3 =
* Extends the dedicated calendar-signup elements for Avada, Elementor, Divi and the WordPress block editor.
* Adds an optional heading override, frame, shadow and top and bottom spacing to every signup embed.
* Lets frontend signup buttons use the active theme's button design.

= 0.8.2 =
* Refined the fixed calendar editor layout, action toolbar, shortcodes and inspector state.
* Added image fitting, optional element and door shadows, embed spacing and signup visibility controls.

= 0.8.1 =
* Adds per-embed title, frame and shadow switches to the shortcode and the Avada, Elementor, Divi and block-editor calendar elements.
* Keeps the shared ContactPanda color picker unchanged and stacks color controls in narrow defaults and element inspectors.
* Uses only the shared ContactPanda image picker UI and hides the replaced legacy selectors while retaining click, drop, preview, replace and remove actions.
* Moves template, preview and save actions above the editor tabs and arranges grid and creation tools in dedicated rows.
* Removes fixed shadows from the calendar canvas and doors; an optional shadow now applies only to the complete embedded calendar block.
* Opens the Panda Seasons dashboard from the main menu and hides the separate door-list submenu while door editing remains inside each calendar.

= 0.8.0 =
* Adds a dedicated responsive Panda Seasons dashboard with three calendar cards per desktop row, cover previews and direct edit and preview actions.
* Shows separate per-calendar counts for door clicks, active email subscribers and generated leads.
* Records only aggregate daily door-click totals without visitor identifiers or raw IP addresses.
* Adds contextual onboarding for the free ContactPanda Basic plugin and a link to ContactPanda Plus.
* Includes the Panda Seasons dashboard illustration locally in the plugin instead of loading it from the marketing website.
* Expands undo and redo history from three to twenty editor steps.
* Continues to use the central ContactPanda color and image components with the standalone Seasons fallback.
* Synchronizes calendar-specific tags and consent states with ContactPanda Basic 0.8.48 while keeping general newsletter consent separate.

= 0.7.10 =
* Uses the central ContactPanda color picker throughout the Seasons editor, including theme and template colors and responsive stacking below the minimum control width.
* Uses the central click-and-drop image picker for desktop and mobile cover images, door backgrounds and visual design layers.
* Keeps the standalone Seasons fallback available when ContactPanda is not installed.
* Includes the separate optional general-newsletter signup alongside calendar email registration, without merging consent or unsubscribe scopes.

= 0.7.9 =
* Lets a visitor explicitly request the general newsletter in addition to an email calendar without merging the two consent scopes.
* Uses ContactPanda's provider-neutral extension interface, avoids a second double opt-in for an already confirmed newsletter address, and records the separate outcome.
* Keeps calendar-only, all-calendar, and all-mailing unsubscribe scopes distinct and auditable.

= 0.7.8 =
* Aligns the configurable 10, 20 or 30 pixel grid from the centre of each desktop and mobile canvas.
* Adds exact door width and height controls with optional aspect locking and one-click application to all doors in the current layout.
* Adds three-step undo and redo controls, including keyboard shortcuts.
* Adds canvas format presets and custom width-to-height dimensions for desktop and mobile layouts.
* Adds Shift-click multi-selection so doors, design areas and text areas can be moved together.

= 0.7.7 =
* Restores the desktop and mobile editor switches directly beside the closed and opened preview controls.
* Keeps the selected door state while switching between the separately stored desktop and mobile layouts.

= 0.7.6 =
* Initializes the editor with the visibly selected closed-door preview instead of the obsolete mixed actual-date state.
* Uses the saved calendar defaults instead of legacy blue and gray fallback colors when a door has no explicit background value.

= 0.7.5 =
* Removes the obsolete purple demonstration gradient when no desktop or mobile cover image is selected.
* Makes transparent door colors reveal the configured calendar background color consistently in the editor, preview and frontend.

= 0.7.4 =
* Uses the same saved door and design-area colors in the editor, preview and frontend.
* Adds selectable 10, 20 and 30 pixel grids for dragging, resizing and aligning all elements.
* Reorganizes the calendar tools into two clear rows with the save action at the far right.
* Adds duplicate and trash actions directly to design areas and text areas.
* Keeps hover outlines inside clickable doors and removes false hover affordances from locked doors.
* Shows the selected calendar below Avada, Elementor, Divi and block-editor calendar elements.
* Removes the unused featured-image control from calendar editing.

= 0.7.3 =
* Replaces the unreliable WordPress publish-button bridge with a dedicated verified calendar save request.
* Shows a definitive saved or failed state instead of leaving the editor at “Saving calendar”.
* Saves changed door, cover and design-area images before the preview is opened.
* Marks media-library selections, removals and rich-text changes as unsaved immediately.
* Refreshes the preview only after the server confirms that the complete calendar was stored.

= 0.7.2 =
* Adds one central calendar save action with clear saved and unsaved feedback across doors, design areas, text areas, positions and settings.
* Keeps the selected door or design element active after applying changes and preserves the current inspector state while switching elements.
* Warns before opening the preview with unsaved changes and can save the calendar before continuing.
* Moves the compact WordPress calendar status panel below the visual editor while keeping the title at the top.
* Rebuilds design-area image selection as a consistent click-and-drop field.
* Adds direct template export from the editor with a choice between complete contents and layout-only templates.

= 0.7.1 =
* Rebuilds the general settings area with dedicated, conflict-free two-column layout rules and keeps cover images and their save action in one compact card.
* Preserves door inspector accordions and state tabs while switching doors, and keeps saved design elements selected for continued editing.
* Adds configurable double opt-in reminders for calendar registrations using the same schedule model as ContactPanda newsletters.
* Arranges calendar and newsletter registration settings in two clear columns and connects Seasons to the shared ContactPanda desktop/mobile email preview interface.
* Adds dedicated calendar-signup elements for the block editor, Avada, Elementor and Divi.

= 0.7.0 =
* Consolidates the prototype data model into one canonical calendar and door contract.
* Migrates existing calendars in place while preserving doors, contents, layouts, media assignments and settings.
* Removes duplicate legacy fields for door ownership, opening behavior, opacity and single-question quizzes after migration.
* Replaces duplicated version-specific stylesheets with one editor and one frontend stylesheet.
* Adds one shared, portable template export used by both the WordPress interface and MCP, without subscriber or consent data.
* Keeps a verified pre-migration snapshot of the existing Casa-Pepe test calendar outside the release package.

= 0.6.4 =
* Adds a dedicated Registration and email delivery tab to the calendar editor.
* Makes signup, consent, status, double opt-in and confirmation texts configurable per calendar.
* Adds a compact ContactPanda connection and registration statistics overview.
* Uses the configured texts in the live signup, double opt-in email and confirmation page.

= 0.6.3 =
* Rebuilds the upper new-door defaults as two explicit full-width rows so legacy editor CSS can no longer shrink their dropdowns.
* Saves desktop and mobile background colors together with cover images, including calendars without cover images.
* Clarifies the ContactPanda-only advance registration, double opt-in process and calendar-specific contact tag.
* Adds the release date below a larger popup heading for every opened door.

= 0.6.2 =
* Moves the default call-to-action into the right-hand new-door defaults block and adds its own save button.
* Forces the two upper default-setting rows and their dropdowns to use the full available column width.
* Keeps the door content popup settings in the left column and matches the popup title size to the CTA title.
* Removes the unused standard WordPress content editor from calendar editing screens without deleting stored data.

= 0.6.1 =
* Expands the new-door general settings to the full available width with two evenly aligned controls per row.
* Adds an explicit save button directly to the door content popup settings.
* Adds reusable calendar-wide call-to-action defaults while preserving optional individual settings per door.
* Replaces the bulk style confirmation with a compact block selector for appearance, related content and call-to-action settings.

= 0.6.0 =
* Reorganizes the calendar toolbar with grid controls beside the opened and closed state controls and preview aligned to the right.
* Adds configurable padding to freely positioned text areas.
* Places door media and text in two popup columns and moves the call-to-action below them across the full width.
* Adds a rich-text CTA editor and aligns CTA type and button text in one row.
* Rebuilds new-door defaults on a consistent two-column grid with aligned field heights and spacing.

= 0.5.8 =
* Separates the door media dropzone from the WordPress text editor and makes file dropping reliable in Safari.
* Saves an edited door directly in WordPress, including selected images, and restores the image after reopening the calendar.
* Keeps the saved media synchronized between the editor, preview and frontend content popup.
* Stacks headings, paragraphs and lists vertically inside freely positioned text areas.

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
