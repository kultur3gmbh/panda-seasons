=== Panda Seasons Free ===
Contributors: kultur3gmbh
Tags: advent calendar, seasonal calendar, christmas calendar, calendar
Requires at least: 6.4
Requires PHP: 7.4
Stable tag: 0.1.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Create simple responsive seasonal calendars with one background image, colored doors and text content.

== Description ==

Panda Seasons Free is a complete, lightweight calendar for seasonal campaigns and Advent calendars.

* Add any number of scheduled doors.
* Position and resize doors separately for desktop and mobile.
* Choose separate background and text colors for closed and opened doors.
* Display formatted text in an accessible content dialog.
* Use one general background image or a background color for the complete calendar.
* Embed calendars with the Panda Seasons block or `[panda_seasons id="123"]`.

The free edition does not include images inside individual doors, design layers, advanced frames and shadows, quizzes, downloads, shop integrations, email delivery or newsletter functions. The complete Panda Seasons edition can replace Panda Seasons Free without converting your calendars. Pro-specific data is preserved when a calendar is temporarily edited in the free edition.

== Installation ==

1. Upload the `panda-seasons-free` folder to `/wp-content/plugins/` or install the ZIP in WordPress.
2. Activate Panda Seasons Free.
3. Create a calendar under Panda Seasons.
4. Publish it and insert the block or shortcode on a page.

Panda Seasons and Panda Seasons Free use the same calendar records and must not be active at the same time.

== Frequently Asked Questions ==

= Can I upgrade without rebuilding my calendar? =

Yes. Deactivate Panda Seasons Free, install and activate Panda Seasons. Existing calendars and doors are opened directly by the complete edition.

= What happens to advanced fields if I return to the free edition? =

They remain stored. Panda Seasons Free does not display or overwrite advanced media, shop, quiz, CTA, email or design-layer fields.

== Changelog ==

= 0.1.4 =
* Makes door movement and resizing clearer and provides exact percentage fields for position and size.
* Stores separate desktop and mobile geometry for every door.
* Adds a public GitHub update channel with an immediate manual update check.

= 0.1.3 =
* Uses the shared ContactPanda color and image controls consistently.
* Adds theme color palettes, including WordPress, Avada, Elementor and Divi colors when available.
* Adds configurable border color, width and style for closed and opened doors.
* Improves image previews and keeps the controls compatible with Panda Seasons.

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
* Uses the same calendar and door data contract as Panda Seasons.
* Prevents both editions from running simultaneously.

