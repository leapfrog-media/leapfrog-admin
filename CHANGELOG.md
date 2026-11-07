# Changelog

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
