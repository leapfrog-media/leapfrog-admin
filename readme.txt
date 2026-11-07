=== Native Admin Colours ===
Requires at least: 6.3
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.3.3
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A lightweight, native WordPress admin colour scheme with editable colours and sizing.

== Installation ==
1. Install and activate the plugin.
2. Open Users > Profile > Admin Colour Scheme.
3. Select Native Admin Colours and click Update Profile.
4. Open Appearance > Native Admin Colours to customise the shared settings.

Each user chooses their own scheme. Only administrators with manage_options can
change the site's shared settings. Saved settings apply to everyone who selected
Native Admin Colours. Existing colours survive an update from earlier versions.
All colour and layout rules are scoped to the current viewer selecting this scheme.
Selecting another scheme restores that scheme's own colours and native dimensions.

== Appearance controls ==
* Sidebar background, selected menu and badges, primary buttons, page background.
* Active plugin background: highlights activated rows on the Plugins screen.
* Separate toolbar and submenu backgrounds, and link colours.
* Separate sidebar, toolbar and submenu text colours.
* Sidebar selected item text and Sidebar hover and focus text, including icon glyphs.
  Automatic chooses readable text against the selected menu/highlight colour.
* Toolbar hover and focus text colour for top-level items, labels and icon glyphs.
  Leave blank for automatic contrast against the highlighted toolbar background.
* Expanded desktop sidebar width: 140-320 px (default 160).
* Sidebar and submenu text size: 11-20 px (default 13).
* Native button corner rounding: 0-16 px (default 3).

Extra colours can be left blank for automatic colours. Main button and selected
menu text automatically choose a light or dark ink. Link colours darken when
needed for contrast on light content. Custom navigation text colours show a live
contrast warning. Use light page and active plugin backgrounds; dark values are
rejected because WordPress's content panels and plugin metadata use light styling.

Collapsed and mobile navigation retain WordPress's native dimensions. Native
button rounding applies to standard WordPress buttons. Third-party screens may
retain their own styles. Frontend pages and the Bricks builder are unchanged.

== Toolbar icon ==
Choose a separate image under Appearance > Native Admin Colours > Toolbar icon.
Choose Image opens the standard Media Library. Save Toolbar Icon applies it to
the site-name item in the WordPress toolbar, for every user and colour scheme,
both in the dashboard and while viewing the site with the toolbar visible.
Use Default Icon restores the original toolbar icon. The site favicon is unchanged.
A light image with a transparent background works well on a dark toolbar.

The image is stored separately from colour palettes, resets and JSON transfers:
choose the appropriate Media Library image on each site. Deleted images fall back
to the default icon. Media selection loads only on this plugin's settings screen.

== Saved palettes and transfer ==
Save up to 20 named palettes, including every colour and sizing control. Save and
Apply Palette saves the current form values and applies them. Reusing the same
name replaces that palette. Apply Palette loads saved settings. Delete Palette
removes the preset without changing the current settings.

Export Saved Settings downloads a portable JSON file containing the currently
saved colours and sizing. Import and Apply accepts that file or pasted JSON,
validates the complete settings, then replaces the current settings. Invalid
files leave settings unchanged. Files are limited to 64 KB. Exports contain only
appearance settings, not site names, roles, login rules or saved palette libraries.
To move several palettes, apply and export each one separately.

Reset to Defaults restores colours and sizing and keeps saved palettes.

== Performance ==
The settings page uses WordPress's own colour picker and a small preview script.
Other admin screens load the compiled colour stylesheet and inline variables,
plus the small toolbar icon stylesheet when an icon is selected.
There are no external fonts, remote requests or replacement admin screens.
This plugin does not change capabilities, login redirects or authentication.
When a custom toolbar icon is selected, a small scoped stylesheet also loads for
logged-in visitors whose frontend toolbar is visible.

== Returning to another scheme ==
Choose another scheme under Users > Profile and save. Deactivating the plugin
makes WordPress fall back to its default scheme. User preferences are retained.

== Development ==
Compiled CSS is included; no build tools are needed to run the plugin.
To rebuild, install the development tools in this plugin directory first:
npm install --no-save sass postcss postcss-selector-parser
Then run:
npx sass --style=compressed --no-source-map src/colors.scss assets/colors.raw.css
node src/build.mjs

The src/wordpress Sass partials are copied from WordPress 7.1.3:
wp-admin/css/colors/_admin.scss, _mixins.scss, _variables.scss, _tokens.scss.
Copyright the WordPress contributors. Core partials are preserved unmodified.
These files and generated CSS are GPL-2.0-or-later. See LICENSE.txt.

== Changelog ==
= 1.3.3 =
* Add separate sidebar controls for selected text and hover/focus text.
* Apply those colours to top-level labels and icon glyphs, with automatic contrast.
* Show selected and focused sidebar items in the live preview.

= 1.3.2 =
* Add Toolbar hover and focus text under Extra colours, with Automatic fallback.
* Apply the colour consistently to top-level labels, icon glyphs and open items.
* Show the focused toolbar colour and contrast guidance in the live preview.

= 1.3.1 =
* Scope all colour and layout rules to users selecting Native Admin Colours.
* Keep self-profile live previews in sync when switching between schemes.
* Preserve other users' saved schemes, palettes and the separate toolbar icon.

= 1.3.0 =
* Separate Media Library image selector for the toolbar site-name icon.
* Preserve the favicon, site link, label and dropdown, with one-click restoration.
* Image selection is per site and independent of colour schemes and palettes.

= 1.2.1 =
* Expanded desktop submenu panels automatically match the chosen sidebar width.

= 1.2.0 =
* Separate toolbar, submenu, links and navigation text colours, with Automatic.
* Active plugin row background colour.
* Desktop sidebar width, menu text size and native button corner rounding.
* Named palettes and validated JSON import/export.
* Expanded live preview, contrast guidance and responsive settings layout.
* Keep existing colours and settings when older forms are submitted.

= 1.1.1 =
* Allow colour changes from forms opened before the plugin was renamed.
* Redirect old settings-page links to Native Admin Colours.
* Preserve normal WordPress permission, nonce and option validation checks.

= 1.1.0 =
* Renamed to Native Admin Colours for use across different websites.
* Preview displays the current site's title.
* Migrates colours and scheme selections from Praxis Living Admin Colour Scheme.

= 1.0.0 =
* Initial native colour scheme with editable colours and preview.
