# Changelog

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
