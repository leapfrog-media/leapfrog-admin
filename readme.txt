=== Leapfrog Admin ===
Contributors: Leapfrog Media
Requires at least: 6.3
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.6.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

The Leapfrog Media admin: an editable admin colour scheme with saved palettes,
a custom toolbar icon and a user role editor. Formerly Native Admin Colours.

== Installation ==
1. Install and activate the plugin.
2. Open Leapfrog > Leapfrog Admin.
3. For the colours: open Users > Profile > Admin Colour Scheme, select Leapfrog
   and click Update Profile.

== Leapfrog menu and features ==
All Leapfrog plugins share one Leapfrog link in the admin menu, with each plugin
beneath it. Leapfrog Admin has a tab per feature (Colours, Toolbar icon, User
roles). Switch features off on the Features tab: a feature that is off is not
loaded and its tab is hidden; its settings are kept.

== Admin menu and admin bar ==
Both editors work in layers: Everyone, then a role, then one person, and the most
specific wins. Choose the layer under "Changes for". Anything a role or person
leaves "As above" follows the layer above it.

Admin menu: drag items and sub-items into order, rename them, change icons, hide
them and add separators. Hiding only removes the link; the page still opens from
its address, so use the role editor to stop access. You can't save a change that
hides Leapfrog or Leapfrog Admin from yourself.

Admin bar: hide toolbar items (in the admin and on the site), add your own links,
and show a Bricks menu. The list of items fills in as administrators browse the
admin and the site, because many items only appear on certain pages.

== Dashboard, notices and login page ==
Dashboard: hide widgets and the welcome panel, and add a help box with your own
text, for everyone or a role. Notices: gather admin notices behind a Notices
button in the toolbar and hide the Help tab, for everyone or a role. Login page:
your logo, background and button colour (blank colours follow the admin colours),
and where people land after logging in and out, for everyone or per role.

On the Colours tab, Use Brand Colours fills in the main colours from Leapfrog
Framework's brand colours; check the preview and save.

== Lists, page tree and profile pictures ==
List columns: for each list (Posts, Pages, …) reorder and remove columns, add ID
and Last modified columns, and add category and tag filters. Page tree: Pages >
Tree view, where you drag pages into order and under each other. Profile
pictures: everyone can choose a picture from the Media Library on their profile
instead of using Gravatar.

== Code snippets ==
Snippets in the admin menu holds small, site-specific PHP, CSS, JavaScript and
HTML snippets, edited in WordPress's own code editor. Choose where each runs,
for whom, and optionally only at certain addresses. A PHP snippet with a mistake
is not switched on, and one that errors while running is switched off. If a
snippet breaks the site, add ?lfa-safe-mode=1 to an admin address (as an
administrator), or define LEAPFROG_SNIPPETS_SAFE_MODE as true in wp-config.php.

== User roles ==
Leapfrog > Leapfrog Admin > User roles lists every role and how many people have
it. Add a role from scratch or from another role's permissions, copy, rename and
delete roles, and tick what each role may do. Permissions are grouped and
described in plain words; powerful ones are marked. "Undo all changes" puts a
role back to how it was before Leapfrog first changed it. Deleting a role moves
its people to a role you choose. Built-in roles, the role new users get and your
own roles can't be deleted, and Administrator and your own roles always keep
"read" and "manage_options" so nobody is locked out. WordPress stores roles
itself, so they stay as they are if this plugin is switched off or removed.

== Colours ==
Each user chooses their own scheme. Only administrators with manage_options can
change the site's shared settings. Saved settings apply to everyone who selected
the Leapfrog scheme. All colour and layout rules are scoped to the current viewer
selecting this scheme. Selecting another scheme restores that scheme's own
colours and native dimensions.

== Moving from Native Admin Colours ==
On activation (and after each update) Leapfrog Admin copies the Native Admin
Colours settings, saved palettes and toolbar icon, and moves every user who chose
the Native Admin Colours (or older Praxis Living) scheme to Leapfrog. The old
settings are left in place, so nothing is lost if the old plugin is switched back
on. Deactivate Native Admin Colours afterwards; a notice reminds you while both
are active. Old Appearance > Native Admin Colours links open the new screen.

== Appearance controls ==
* Sidebar background, selected menu and badges, primary buttons, page background.
* Active plugin background: highlights activated rows on the Plugins screen.
* Separate toolbar and submenu backgrounds, and link colours.
* Separate sidebar, toolbar and submenu text colours.
* Sidebar selected item text and Sidebar hover and focus text, including icon glyphs.
* Toolbar hover and focus text colour for top-level items, labels and icon glyphs.
* Expanded desktop sidebar width: 140-320 px (default 160).
* Sidebar and submenu text size: 11-20 px (default 13).
* Native button corner rounding: 0-16 px (default 3).

Extra colours can be left blank for automatic colours that keep text readable.
Link colours darken when needed for contrast on light content. Use light page and
active plugin backgrounds; dark values are rejected because WordPress's content
panels use light styling. Collapsed and mobile navigation keep WordPress's native
dimensions. Third-party screens may keep their own styles. Frontend pages and the
Bricks builder are unchanged.

== Toolbar icon ==
Leapfrog > Leapfrog Admin > Toolbar icon. Choose Image opens the Media Library;
Save Toolbar Icon applies it to the site-name item in the toolbar for every user
and colour scheme, in the dashboard and on the site. Use Default Icon restores
the original. The site favicon is unchanged. The image is stored separately from
palettes, resets and exports, so choose it on each site.

== Saved palettes and transfer ==
Save up to 20 named palettes, including every colour and sizing control. Reusing
a name replaces that palette. Export Saved Settings downloads a JSON file with
the current colours and sizing; Import and Apply accepts that file (or one from
Native Admin Colours), validates it, then replaces the current settings. Invalid
files change nothing. Reset to Defaults keeps saved palettes.

== Performance ==
The settings page uses WordPress's own colour picker and a small preview script.
Other admin screens load one compiled stylesheet and inline variables, plus a tiny
toolbar icon stylesheet when an icon is chosen. No external fonts or remote
requests. Capabilities, login and authentication are not changed.

== Updates ==
Updates come from GitHub through WordPress's own updater (Dashboard > Updates,
the Plugins screen, automatic updates). The newest version tag (v1.2.3) on
github.com/leapfrog-media/leapfrog-admin is the newest version, so a release is just a
tagged commit, pushed. Public repositories need nothing more. Private ones need
a GitHub token that can read them: LEAPFROG_GITHUB_TOKEN in wp-config.php, or
the Updates tab of Leapfrog Admin or Leapfrog Performance (one token covers all
Leapfrog plugins). A copy that is a Git working folder is never updated.

== Development ==
Compiled CSS is included; no build tools are needed to run the plugin.
To rebuild assets/colors.min.css after changing src/colors.scss or src/build.mjs:
npm install
npm run build

The src/wordpress Sass partials are copied from WordPress 7.1.3:
wp-admin/css/colors/_admin.scss, _mixins.scss, _variables.scss, _tokens.scss.
Copyright the WordPress contributors. Core partials are preserved unmodified.
These files and generated CSS are GPL-2.0-or-later. See LICENSE.txt.

== Changelog ==
See CHANGELOG.md.
