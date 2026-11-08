# Changelog

## 2.6.0 — 2026-11-08

Updates from GitHub, shared by all Leapfrog plugins.

- Leapfrog Admin, Leapfrog Framework and Leapfrog Performance update through WordPress's own updater (Dashboard > Updates, the Plugins screen, automatic updates) from github.com/leapfrog-media. The newest version tag (`v1.2.3`) is the newest version; WordPress installs GitHub's zip of that tag into the plugin's own folder. "View details" shows the new version's changelog.
- Public repositories need no setup. Private ones need a GitHub token that can read them, shared by all Leapfrog plugins: `LEAPFROG_GITHUB_TOKEN` in wp-config.php (best) or the new **Updates** tab (option `leapfrog_github_token`, not autoloaded). The token is only sent to api.github.com, for the plugin's own repository.
- **Updates** tab: each Leapfrog plugin's installed and newest version, why a check failed (no token, refused token, no access, GitHub's hourly limit), Check Now, and the token.
- A copy that is a Git working folder (like the development copies on kineticmoney.test) is never updated, so work can't be overwritten.
- The plugin header's `Update URI` stops WordPress.org offering a different plugin with the same folder name.
- The shared code is the `GitHubUpdater` class in `includes/github-updater.php`; Leapfrog Performance and Leapfrog Framework carry identical copies (only the namespace differs).
- GitHub's zips leave out build sources and tooling (`.gitattributes` export-ignore).

## 2.5.0 — 2026-11-08

Code snippets, for small site-specific code (snippets every site needs belong in a Leapfrog plugin).

- **Snippets** menu with WordPress's own list and edit screens: PHP, CSS, JavaScript and HTML snippets in WordPress's code editor (syntax colours, line numbers, Ctrl+S to save), with notes, an on/off switch in the list, and a running order.
- Where each runs: everywhere, the site, the admin or the log-in page; CSS, JavaScript and HTML in the head, at the start of the body or in the footer. For: everyone, logged-in people, logged-out visitors or one role. Optionally only at certain addresses (/contact/, /blog/*). PHP snippets also run for AJAX and scheduled tasks.
- Safety: PHP is syntax-checked on save and not switched on if it has a mistake; a snippet that errors or crashes while running is switched off and the error shown on its screen. Safe mode runs no snippets: `?lfa-safe-mode=1` for administrators, or `define( 'LEAPFROG_SNIPPETS_SAFE_MODE', true );` in wp-config.php.
- Export (Bulk actions > Export) and Import (Snippets > Import); imported snippets start switched off.
- Only administrators can see or change snippets. Snippets are stored as a private post type (`lfa_snippet`, code in post meta); the active ones are copied into the option `leapfrog_admin_snippets` so running them needs no database query.
- A switchable feature on the Features tab (off = no snippets run).

## 2.4.0 — 2026-11-08

Lists and pages: the last milestone of the Leapfrog Admin plan.

- **List columns** tab: for each list (Posts, Pages and other post types), drag columns into order, remove columns for everyone, and add **ID** and **Last modified** (with who last edited) columns, both sortable. Add drop-down filters for the list's categories and tags (shown once the taxonomy has terms). Columns appear in the editor once Leapfrog has seen the list (option `leapfrog_admin_columns_seen`). The tick box and Title always stay; Screen Options still works per person. Option `leapfrog_admin_columns`.
- **Page tree**: Pages > Tree view (and a Tree view link above the Pages list), for every hierarchical post type. Drag to reorder and to move a page under another; Save Order changes only the parent and order of items that moved, without changing their "last modified" date or running save hooks. Pages you can't edit stay put.
- **Profile pictures**: choose a profile picture from the Media Library on Users > Profile (administrators can set anyone's). It replaces Gravatar wherever WordPress shows that person's picture. User meta `lfa_avatar`.
- The ordering helper used by the admin menu moved to `includes/page.php` so list columns can share it.

## 2.3.0 — 2026-11-08

Dashboard, notices, login page and brand colours. Dashboard and Notices are set for everyone or a role.

- **Dashboard** tab: hide any dashboard widget Leapfrog has seen (option `leapfrog_admin_dashboard_seen`) and the welcome panel; add a help box at the top of the dashboard with your own title and text (a role can have its own). Option `leapfrog_admin_dashboard`.
- **Notices** tab: "Gather notices" moves admin notices into a panel behind a Notices button on the right of the toolbar, with a count that turns red when one is an error. Messages straight after an action (Post updated, Settings saved, form errors), inline messages and ones inside forms stay on the page. "Hide the Help tab" removes the Help tab. Option `leapfrog_admin_notices`.
- **Login page** tab: logo (with width), background colour and image, and button colour on wp-login.php; blank colours follow the Leapfrog admin colours. The logo links to the site. Where people land after logging in and after logging out, for everyone and per role (a page on this site or a full address on it; someone sent to log in on the way to a page still goes there). Option `leapfrog_admin_login`.
- **Use Brand Colours** on the Colours tab, when Leapfrog Framework has brand colours: fills in primary for the sidebar, accent for the selected menu and badges, secondary for buttons, for you to check in the preview and save.
- The layer picker can leave out people (Dashboard and Notices). Leaving a page while a form is saving no longer asks about unsaved changes.

## 2.2.0 — 2026-11-08

Admin menu and admin bar editors, each for everyone, a role or one person.

- **Layers** (`includes/layers.php`): changes for Everyone, then a role, then one person; the most specific wins. Someone with two roles gets the changes from both, and if one role hides an item that the other shows, it is shown. "Changes for" switches layer; badges list the layers that have changes; Reset removes a layer's changes.
- **Admin menu** tab: drag to reorder top-level items and sub-items, rename (counters such as comments waiting are kept), change icons (any Dashicon, with search), hide, and add separators. A layer can keep the order from above or have its own. Items added later by plugins slot in after the item they followed. Hiding only removes the link. Saving is refused if it would hide Leapfrog or Leapfrog Admin from the person saving. Option `leapfrog_admin_menu`.
- **Admin bar** tab: hide any toolbar item seen in the admin or on the site (Leapfrog lists items as administrators browse, option `leapfrog_admin_bar_seen`), add your own links (admin, site or both, optionally in a new tab), and a Bricks menu (Edit this page with Bricks, Templates, Custom fonts, Settings, each only for people allowed to use it). Option `leapfrog_admin_bar`.
- Both are features that can be switched off on the Features tab.
- `assets/page.js`: shared unsaved-changes warning and confirm buttons (the role editor uses it too).

## 2.1.0 — 2026-11-08

Foundations: the shared Leapfrog menu, tabs with feature switches, and a user role editor.

- **Leapfrog menu.** One top-level Leapfrog link (the outline Leapfrog mark) shared by all Leapfrog plugins, with Leapfrog Admin, Leapfrog Framework and (later) Leapfrog Performance beneath. Clicking it opens Leapfrog Admin with the sub-items expanded. Whichever Leapfrog plugin loads first adds the link (`includes/menu.php`; Leapfrog Framework 1.2.2 carries the same logic).
- The settings screen moved from Settings > Leapfrog Admin to **Leapfrog > Leapfrog Admin** (`admin.php?page=leapfrog-admin`). Old links redirect, including the Native Admin Colours ones.
- **Tabs and feature switches.** Colours, Toolbar icon and User roles are each a tab and a feature that can be switched off on the Features tab (option `leapfrog_admin_disabled_features`). A feature that is off is not loaded. The toolbar icon has its own tab now.
- **User roles.** A list of roles with how many people have each; add a role (from scratch or from another role's permissions), copy, rename and delete roles. Each permission is ticked in groups (Posts, Pages, other content types, Media, Appearance, Plugins, Users, Site and settings, Bricks, other plugins), described in plain words, with powerful ones marked; search and "only show ticked" filters; add a custom permission. "Undo all changes" puts a role back to how it was before Leapfrog first changed it (option `leapfrog_admin_role_backups`). Deleting a role moves its people to a role you choose. Built-in roles, the new-user role and your own roles can't be deleted; Administrator and your own roles keep `read` and `manage_options`.
- Code: `includes/settings.php` is now `includes/colours-settings.php`; new `menu.php`, `page.php`, `features.php`, `roles.php`; `assets/page.css`, `roles.js`, `toolbar-icon-settings.js`.

## 2.0.0 — 2026-11-07

Native Admin Colours is now **Leapfrog Admin**, the Leapfrog Media admin plugin. Colours, palettes and the toolbar icon work as before.

- The settings screen moved from Appearance > Native Admin Colours to **Settings > Leapfrog Admin**. Old links redirect there.
- The colour scheme is called **Leapfrog** in Users > Profile (id `leapfrog-admin`).
- Settings, saved palettes and the toolbar icon are copied to new option names (`leapfrog_admin_colours`, `leapfrog_admin_presets`, `leapfrog_admin_toolbar_icon`) on activation and after updates. Users who chose Native Admin Colours or Praxis Living move to Leapfrog. Old options are kept.
- A notice suggests deactivating Native Admin Colours while both plugins are active.
- Exports are now `leapfrog-admin-colours.json`. Native Admin Colours export files still import.
- Code: namespace `Leapfrog\Admin`, `--lfa-*` CSS variables and `lfa-` classes, `leapfrog_admin_*` actions and nonces. The colour scheme code moved to `includes/colours.php`; migration lives in `includes/migrate.php`.
- `npm run build` rebuilds the colour stylesheet.
- Removed the Praxis Living 1.0 form handlers (old open forms and reset button).

## Native Admin Colours history

### 1.3.3
- Separate sidebar controls for selected text and hover/focus text, applied to labels and icon glyphs with automatic contrast, and shown in the live preview.

### 1.3.2
- Toolbar hover and focus text under Extra colours, with Automatic fallback and preview.

### 1.3.1
- All colour and layout rules scoped to users who select the scheme; self-profile previews stay in sync when switching schemes.

### 1.3.0
- Media Library toolbar icon for the site-name item, separate from the favicon, palettes and exports.

### 1.2.1
- Expanded desktop submenu panels match the chosen sidebar width.

### 1.2.0
- Separate toolbar, submenu, link and navigation text colours; active plugin row colour; sidebar width, menu text size and button rounding.
- Named palettes and validated JSON import/export; expanded live preview with contrast guidance.

### 1.1.1
- Forms opened before the rename still save; old settings links redirect.

### 1.1.0
- Renamed from Praxis Living Admin Colour Scheme to Native Admin Colours for use on any site, with migration.

### 1.0.0
- Initial native colour scheme with editable colours and preview.
