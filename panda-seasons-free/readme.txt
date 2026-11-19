=== Panda Seasons Lite ===
Contributors: kultur3gmbh
Tags: advent calendar, seasonal calendar, christmas calendar, calendar
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.9.6
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Create simple responsive seasonal calendars with one background image, colored doors and text content.

== Description ==

Panda Seasons Lite is a complete, lightweight calendar for seasonal campaigns and Advent calendars.

* Add any number of scheduled doors.
* Position and resize doors separately for desktop and mobile.
* Choose separate background and text colors for closed and opened doors.
* Display formatted text in an accessible content dialog.
* Use one general background image or a background color for the complete calendar.
* Embed calendars with the Panda Seasons Lite block or `[panda_seasons id="123"]`.

The free edition does not include images inside individual doors, design layers, advanced frames and shadows, quizzes, downloads, shop integrations, email delivery or newsletter functions. The Panda Seasons Full edition can replace Panda Seasons Lite without converting your calendars. Plus-specific data is preserved when a calendar is temporarily edited in the free edition.

== Privacy ==

Panda Seasons Lite does not automatically contact an external service or load remote template previews. Calendar images are selected from the local WordPress Media Library. Optional links to the Panda Seasons Lite product website open only when clicked.

== Source code ==

All PHP, JavaScript and CSS source is included in this plugin. The scripts are maintained directly: no npm, webpack, transpilation or minification build is required to run or modify them.

The shared Panda administration controls are in assets/contactpanda-admin-ui.js. A complete, expanded readable copy is included in sources/contactpanda-admin-ui.js, including the compact tag-selection helper. The two files differ only in whitespace. The shared runtime component is retained unchanged to avoid a product-specific fork. Other JavaScript sources are the files directly inside assets/.

The plugin and its bundled source are distributed under GPLv2 or later. Translation source catalogs remain maintained in the development workspace. The WordPress.org submission omits bundled PO/MO translations and uses WordPress language packs; until a language pack is available, untranslated strings fall back to English. The POT template remains included.

== Installation ==

1. Upload the `panda-seasons-free` folder to `/wp-content/plugins/` or install the ZIP in WordPress.
2. Activate Panda Seasons Lite.
3. Create a calendar under Panda Seasons Lite.
4. Publish it and insert the block or shortcode on a page.

Panda Seasons Full and Panda Seasons Lite use the same calendar records and must not be active at the same time.

== Frequently Asked Questions ==

= Can I upgrade without rebuilding my calendar? =

Yes. Deactivate Panda Seasons Lite, install and activate Panda Seasons Full. Existing calendars and doors are opened directly by the complete edition.

= What happens to advanced fields if I return to the free edition? =

They remain stored. Panda Seasons Lite does not display or overwrite advanced media, shop, quiz, CTA, email or design-layer fields.

== Changelog ==

= 0.9.6 =
* Unambiguous Canvas view switches, compact paired phone fields and consistent media/file selection.
* Refined label settings order and shorter field captions.


= 0.9.5 =
* Full-width Canvas editor with compact dialogs, responsive single-column phone forms and inline save confirmation.
* Combined unlock date and time, compact field groups and preserved existing calendar data.


= 0.9.4 =
* Shared compact checkbox and radio controls preserve inline labels, keyboard focus and responsive layout.


= 0.9.3 =
* Shared action alignment preserves readable labels and consistent icons in lists and dialogs.

= 0.9.2 =
* Unified PandaCosmo product presentation and shared controls.
* Shared account and family integration improvements.


= 0.9.1 =
* Consistent PandaCosmo product names and cross-references; existing technical identifiers and saved data are retained.
* Manual installation replaces the earlier 1.0.0 review build with the pre-1.0 development series.


= 1.0.0 =
* First 1.0 release of the existing free calendar edition.

= 0.3.4 =
* Remove automatic external template previews and document privacy behavior.
* Complete translation context and document bounded calendar ordering.

= 0.3.3 =
* Updated shared editor receiving gaps and button icon handling.

= 0.3.1 =
* Restores reliable door movement and resizing with the shared pointer interaction pattern.
* Restores the square and round starter grids in the Add elements panel for click and drag-and-drop insertion.

= 0.3.0 =
* Aligns the reduced Free editor with the full Panda Seasons Lite editor structure and global Panda controls.
* Uses a full-width WordPress editing shell while retaining the wide canvas and right-hand three-tab editor.
* Switches directly to element editing when a door is selected.
* Removes the duplicate background image picker and keeps the shared family image picker.
* Makes starter templates selectable and draggable onto the calendar.
* Improves the Panda Seasons Full presentation and links it directly to the product page.

= 0.1.17 =
* Keeps element palettes consistently left aligned.

= 0.1.16 =
* Aligns the calendar editor with the shared three-tab Panda editor layout.
* Moves calendar-wide controls into the dedicated Basic settings panel.
* Generates the current calendar preview during saving and stores it as the calendar featured image for dashboard cards.

= 0.1.15 =
* Shows only real, centrally published Panda Seasons Full templates in the dashboard gallery.
* Uses the official template thumbnails in a responsive three-column desktop layout.

= 0.1.14 =

* Moves the free calendar cover to the shared responsive Panda image picker without a separate URL field.

= 0.1.13 =

* Adopts the responsive Panda image picker for the standalone free calendar editor.
* Adds compact media metadata and aligned replace, edit, external-link, and delete actions without a permanent URL field.

= 0.1.12 =

* Uses the shared two-column calendar builder and keeps the canvas position stable while editing doors.
* Consolidates shared color and image controls and bounds calendar and door workloads.

= 0.1.11 =

* Adds the standalone shared Panda editor fallback and compact color controls.

= 0.1.10 =
* Aligns standalone controls, responsive color fields, media uploads, and action icons with the Panda family design system.

= 0.1.9 =

* Adds the official WordPress Plugin Check to continuous validation for WordPress.org readiness.
* Keeps the standalone free package separate from protected update code.

= 0.1.8 =

* Unified administration actions with the grayscale Panda button system and centered icons.

= 0.1.7 =
* Applies the neutral-grey action style to every large Panda Seasons Lite administration button.
* Detects action words anywhere in descriptive labels and adds the matching shared icon.

= 0.1.6 =
* Adds the shared neutral-grey Panda action system to the standalone free edition.
* Keeps large labelled actions responsive and compact composer actions unchanged.

= 0.1.5 =
* Expands the dashboard into a clear Pro product presentation with benefits, use cases and repeated upgrade actions.
* Adds a visual showcase for the selected template concepts 1, 3, 4, 5, 7 and 9.
* Adds a concise Free and Pro feature comparison.
* Provides complete formal German translations for the new sales presentation.

= 0.1.4 =
* Makes door movement and resizing clearer and provides exact percentage fields for position and size.
* Stores separate desktop and mobile geometry for every door.
* Prepares the plugin for updates from the official WordPress.org directory.

= 0.1.3 =
* Uses the shared ContactPanda color and image controls consistently.
* Adds theme color palettes, including WordPress, Avada, Elementor and Divi colors when available.
* Adds configurable border color, width and style for closed and opened doors.
* Improves image previews and keeps the controls compatible with Panda Seasons Lite.

= 0.1.2 =
* Uses the unified ContactPanda color picker with transparency and the family image uploader design.
* Adds click and drag-and-drop creation of doors.
* Adds square, rounded and round door shapes.
* Includes two starter templates.
* Improves the order of door appearance and content fields.

= 0.1.1 =
* Loads administration hooks only in the WordPress back office.
* Bounds calendar and door queries for predictable memory use.
* Keeps frontend assets registered without loading editor assets.

= 0.1.0 =
* Initial free edition with a responsive visual editor, scheduled doors, open and closed colors, text content, shortcode and block.
* Uses the same calendar and door data contract as Panda Seasons Lite.
* Prevents both editions from running simultaneously.

