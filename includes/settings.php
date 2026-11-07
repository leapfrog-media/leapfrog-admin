<?php
namespace NativeAdminColours;

if (!defined('ABSPATH')) { exit; }

/** One field definition drives storage, validation and the settings screen. */
function fields(): array
{
    return [
        'navigation' => ['label' => 'Sidebar background', 'type' => 'colour', 'default' => '#262626', 'group' => 'Main colours'],
        'accent' => ['label' => 'Selected menu and badges', 'type' => 'colour', 'default' => '#ff6f60', 'group' => 'Main colours'],
        'action' => ['label' => 'Primary buttons', 'type' => 'colour', 'default' => '#b13f32', 'group' => 'Main colours'],
        'canvas' => ['label' => 'Page background', 'type' => 'colour', 'default' => '#f6f5f3', 'light' => true, 'group' => 'Main colours', 'help' => 'Choose a light colour to match WordPress’s content panels.'],
        'active_plugin' => ['label' => 'Active plugin background', 'type' => 'colour', 'default' => '#f0f6fc', 'light' => true, 'group' => 'Main colours', 'help' => 'Highlights activated rows on the Plugins screen. Choose a light colour.'],
        'toolbar' => ['label' => 'Toolbar background', 'type' => 'colour', 'default' => '', 'group' => 'Extra colours', 'help' => 'Automatic: matches the sidebar.'],
        'submenu' => ['label' => 'Submenu background', 'type' => 'colour', 'default' => '', 'group' => 'Extra colours', 'help' => 'Automatic: a darker shade of the sidebar.'],
        'link' => ['label' => 'Links', 'type' => 'colour', 'default' => '', 'group' => 'Extra colours', 'help' => 'Automatic: based on the button colour. Links darken when needed for readable contrast.'],
        'menu_text' => ['label' => 'Sidebar text', 'type' => 'colour', 'default' => '', 'group' => 'Extra colours', 'help' => 'Automatic: light or dark text to match the sidebar.'],
        'menu_selected_text' => ['label' => 'Sidebar selected item text', 'type' => 'colour', 'default' => '', 'group' => 'Extra colours', 'help' => 'Text and icon colour for the current top-level sidebar item. Automatic: readable light or dark text against the selected menu colour.'],
        'menu_focus_text' => ['label' => 'Sidebar hover and focus text', 'type' => 'colour', 'default' => '', 'group' => 'Extra colours', 'help' => 'Text and icon colour for a top-level sidebar item while hovered, focused or its flyout is open. This takes priority over selected item text while focused. Automatic: readable light or dark text against the highlight colour.'],
        'toolbar_text' => ['label' => 'Toolbar text', 'type' => 'colour', 'default' => '', 'group' => 'Extra colours', 'help' => 'Automatic: light or dark text to match the toolbar.'],
        'toolbar_focus_text' => ['label' => 'Toolbar hover and focus text', 'type' => 'colour', 'default' => '', 'group' => 'Extra colours', 'help' => 'Text and icons on top-level toolbar items while hovered, focused or their dropdown is open. Automatic: readable light or dark text for the highlight background.'],
        'submenu_text' => ['label' => 'Submenu text', 'type' => 'colour', 'default' => '', 'group' => 'Extra colours', 'help' => 'Automatic: light or dark text to match the submenu.'],
        'menu_width' => ['label' => 'Sidebar width', 'type' => 'number', 'default' => 160, 'min' => 140, 'max' => 320, 'group' => 'Sizing', 'help' => '140–320 px. Applies to the expanded desktop sidebar.'],
        'menu_font_size' => ['label' => 'Menu text size', 'type' => 'number', 'default' => 13, 'min' => 11, 'max' => 20, 'group' => 'Sizing', 'help' => '11–20 px. Applies to sidebar and submenu text.'],
        'button_radius' => ['label' => 'Button corner rounding', 'type' => 'number', 'default' => 3, 'min' => 0, 'max' => 16, 'group' => 'Sizing', 'help' => '0–16 px. Zero gives square corners.'],
    ];
}

function valid_value($value, array $field): bool
{
    if ($field['type'] === 'number') {
        return (is_int($value) || (is_string($value) && preg_match('/^\d{1,3}$/D', $value))) && (int) $value >= $field['min'] && (int) $value <= $field['max'];
    }
    if ($value === '' && $field['default'] === '') { return true; }
    $hex = normalise_colour($value);
    return $hex !== null && (empty($field['light']) || luminance($hex) >= 0.70);
}

/** Validate imports and presets as a whole before changing any saved settings. */
function validate_palette($input)
{
    if (!is_array($input) || array_diff(array_keys($input), array_keys(fields()))) {
        return new \WP_Error('invalid_palette', 'The settings contain unrecognised fields.');
    }
    foreach (['navigation', 'accent', 'action', 'canvas'] as $key) {
        if (!array_key_exists($key, $input)) { return new \WP_Error('invalid_palette', 'The settings are missing a main colour.'); }
    }
    $result = defaults();
    foreach ($input as $key => $value) {
        $field = fields()[$key];
        if (!valid_value($value, $field)) { return new \WP_Error('invalid_palette', 'Please check ' . $field['label'] . '. ' . ($field['help'] ?? 'Enter a valid hex colour.')); }
        $result[$key] = $field['type'] === 'number' ? (int) $value : ($value === '' ? '' : normalise_colour($value));
    }
    return $result;
}

function saved_presets(): array
{
    $saved = get_option('native_admin_colours_presets', []);
    return is_array($saved) ? array_slice($saved, 0, 20, true) : [];
}

function appearance_authorise(string $action): void
{
    if (!current_user_can('manage_options')) {
        wp_die('You do not have permission to change these settings.', '', ['response' => 403]);
    }
    check_admin_referer($action, '_nac_nonce');
}

function appearance_return(string $notice): void
{
    wp_safe_redirect(add_query_arg('nac_notice', $notice, admin_url('themes.php?page=native-admin-colours')));
    exit;
}

function save_preset(): void
{
    appearance_authorise('native_admin_colours_presets');
    $name = isset($_POST['preset_name']) && is_string($_POST['preset_name']) ? sanitize_text_field(wp_unslash($_POST['preset_name'])) : '';
    if ($name === '' || strlen($name) > 80) { wp_die('Give the palette a name of up to 80 characters. Use your browser’s Back button to keep editing.', '', ['response' => 400]); }
    $palette = validate_palette(isset($_POST['native_admin_colours']) ? wp_unslash($_POST['native_admin_colours']) : null);
    if (is_wp_error($palette)) { wp_die(esc_html($palette->get_error_message()), '', ['response' => 400]); }
    $presets = saved_presets();
    $id = substr(hash('sha256', strtolower($name)), 0, 24);
    if (!isset($presets[$id]) && count($presets) >= 20) { wp_die('You can save up to 20 palettes. Delete one before adding another.', '', ['response' => 400]); }
    $presets[$id] = ['name' => $name, 'settings' => $palette];
    update_option('native_admin_colours_presets', $presets, false);
    update_option('native_admin_colours', $palette);
    appearance_return('preset_saved');
}
add_action('admin_post_native_admin_colours_save_preset', __NAMESPACE__ . '\\save_preset');

function manage_preset(): void
{
    appearance_authorise('native_admin_colours_presets');
    $presets = saved_presets();
    $id = isset($_POST['preset_id']) && is_string($_POST['preset_id']) ? sanitize_key(wp_unslash($_POST['preset_id'])) : '';
    if (!isset($presets[$id])) { wp_die('Please choose a saved palette.', '', ['response' => 400]); }
    if (isset($_POST['preset_operation']) && $_POST['preset_operation'] === 'delete') {
        unset($presets[$id]);
        update_option('native_admin_colours_presets', $presets, false);
        appearance_return('preset_deleted');
    }
    $palette = validate_palette($presets[$id]['settings'] ?? null);
    if (is_wp_error($palette)) { wp_die('This palette contains invalid settings.', '', ['response' => 400]); }
    update_option('native_admin_colours', $palette);
    appearance_return('preset_loaded');
}
add_action('admin_post_native_admin_colours_manage_preset', __NAMESPACE__ . '\\manage_preset');

function export_settings(): void
{
    appearance_authorise('native_admin_colours_export');
    nocache_headers();
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="native-admin-colours.json"');
    header('X-Content-Type-Options: nosniff');
    echo wp_json_encode(['plugin' => 'native-admin-colours', 'format_version' => 1, 'settings' => colours()], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}
add_action('admin_post_native_admin_colours_export', __NAMESPACE__ . '\\export_settings');

function parse_import($json)
{
    if (!is_string($json) || strlen($json) > 65536) { return new \WP_Error('invalid_import', 'Choose a Native Admin Colours JSON file smaller than 64 KB.'); }
    $data = json_decode($json, true);
    if (!is_array($data) || ($data['plugin'] ?? '') !== 'native-admin-colours' || ($data['format_version'] ?? null) !== 1) {
        return new \WP_Error('invalid_import', 'This is not a supported Native Admin Colours settings file.');
    }
    return validate_palette($data['settings'] ?? null);
}

function import_settings(): void
{
    appearance_authorise('native_admin_colours_import');
    $palette = parse_import(isset($_POST['settings_json']) ? wp_unslash($_POST['settings_json']) : null);
    if (is_wp_error($palette)) { wp_die(esc_html($palette->get_error_message()) . ' No settings were changed.', '', ['response' => 400]); }
    update_option('native_admin_colours', $palette);
    appearance_return('imported');
}
add_action('admin_post_native_admin_colours_import', __NAMESPACE__ . '\\import_settings');

function render_settings_page(): void
{
    if (!current_user_can('manage_options')) { return; }
    $values = colours();
    $presets = saved_presets();
    $post = admin_url('admin-post.php');
    $notices = ['preset_saved' => 'Palette saved and applied.', 'preset_loaded' => 'Saved palette applied.', 'preset_deleted' => 'Saved palette deleted. Current colours are unchanged.', 'imported' => 'Settings imported and applied.', 'icon_saved' => 'Toolbar icon saved. Your site favicon is unchanged.', 'icon_removed' => 'Default toolbar icon restored.'];
    $notice = isset($_GET['nac_notice']) && is_string($_GET['nac_notice']) ? sanitize_key(wp_unslash($_GET['nac_notice'])) : '';
    ?>
    <div class="wrap nac-colour-settings">
        <h1>Native Admin Colours</h1>
        <p>Personalise your WordPress workspace. These settings apply to everyone who selects <strong>Native Admin Colours</strong> in their profile.</p>
        <?php settings_errors(); ?>
        <?php if (isset($notices[$notice])) : ?><div class="notice notice-success is-dismissible"><p><?php echo esc_html($notices[$notice]); ?></p></div><?php endif; ?>
        <div class="nac-colour-layout">
            <div class="nac-colour-controls">
                <form action="options.php" method="post" id="nac-settings-form">
                    <?php settings_fields('native_admin_colours_settings'); ?>
                    <?php wp_nonce_field('native_admin_colours_presets', '_nac_nonce', false); ?>
                    <?php foreach (['Main colours', 'Extra colours', 'Sizing'] as $group) : ?>
                    <details class="nac-panel" <?php if ($group === 'Main colours') { echo 'open'; } ?>>
                        <summary><?php echo esc_html($group); ?></summary>
                        <?php if ($group === 'Extra colours') : ?><p class="description">Leave a field blank to use its automatic colour. Clear a colour to return to Automatic.</p><?php endif; ?>
                        <div class="nac-fields">
                        <?php foreach (fields() as $key => $field) : if ($field['group'] !== $group) { continue; } ?>
                            <div class="nac-colour-field">
                                <label for="nac-<?php echo esc_attr($key); ?>"><?php echo esc_html($field['label']); ?></label>
                                <?php if ($field['type'] === 'number') : ?>
                                <input id="nac-<?php echo esc_attr($key); ?>" name="native_admin_colours[<?php echo esc_attr($key); ?>]" data-nac-key="<?php echo esc_attr($key); ?>" type="number" min="<?php echo (int) $field['min']; ?>" max="<?php echo (int) $field['max']; ?>" step="1" value="<?php echo (int) $values[$key]; ?>" required aria-describedby="nac-help-<?php echo esc_attr($key); ?>"> <span>px</span>
                                <?php else : ?>
                                <input class="nac-colour-picker" id="nac-<?php echo esc_attr($key); ?>" name="native_admin_colours[<?php echo esc_attr($key); ?>]" data-nac-key="<?php echo esc_attr($key); ?>" data-default-color="<?php echo esc_attr($field['default']); ?>" value="<?php echo esc_attr($values[$key]); ?>" type="text" placeholder="<?php echo $field['default'] === '' ? 'Automatic' : '#rrggbb'; ?>" aria-describedby="nac-help-<?php echo esc_attr($key); ?>">
                                <?php endif; ?>
                                <p class="description" id="nac-help-<?php echo esc_attr($key); ?>"><?php echo esc_html($field['help'] ?? 'Choose a colour or enter a hex code.'); ?></p>
                            </div>
                        <?php endforeach; ?>
                        </div>
                    </details>
                    <?php endforeach; ?>
                    <?php submit_button('Save Colours'); ?>
                    <details class="nac-panel">
                        <summary>Save a named palette</summary>
                        <p class="description">Save and apply the colours and sizing currently shown above. Using an existing name replaces that saved palette.</p>
                        <p><label for="nac-preset-name">Palette name</label><br><input type="text" id="nac-preset-name" name="preset_name" maxlength="80" placeholder="e.g. Ocean blue"></p>
                        <button type="submit" class="button" name="action" value="native_admin_colours_save_preset" formaction="<?php echo esc_url($post); ?>">Save and Apply Palette</button>
                    </details>
                </form>
                <?php render_toolbar_icon_settings(); ?>
                <details class="nac-panel">
                    <summary>Saved palettes</summary>
                    <?php if (!$presets) : ?><p>No palettes saved yet. Use “Save a named palette” above.</p><?php else : ?>
                    <form action="<?php echo esc_url($post); ?>" method="post">
                        <input type="hidden" name="action" value="native_admin_colours_manage_preset">
                        <?php wp_nonce_field('native_admin_colours_presets', '_nac_nonce'); ?>
                        <p><label for="nac-preset-id">Choose a palette</label><br><select name="preset_id" id="nac-preset-id"><?php foreach ($presets as $id => $preset) : ?><option value="<?php echo esc_attr($id); ?>"><?php echo esc_html($preset['name']); ?></option><?php endforeach; ?></select></p>
                        <button type="submit" class="button" name="preset_operation" value="load">Apply Palette</button>
                        <button type="submit" class="button" name="preset_operation" value="delete">Delete Palette</button>
                        <p class="description">Applying a palette replaces the current saved settings. Deleting a palette leaves the current settings in place.</p>
                    </form>
                    <?php endif; ?>
                </details>
                <details class="nac-panel">
                    <summary>Import and export</summary>
                    <p>Move your saved colours and sizing between sites.</p>
                    <p><a class="button" href="<?php echo esc_url(wp_nonce_url(add_query_arg('action', 'native_admin_colours_export', $post), 'native_admin_colours_export', '_nac_nonce')); ?>">Export Saved Settings</a></p>
                    <form action="<?php echo esc_url($post); ?>" method="post" id="nac-import-form">
                        <input type="hidden" name="action" value="native_admin_colours_import">
                        <?php wp_nonce_field('native_admin_colours_import', '_nac_nonce'); ?>
                        <p><label for="nac-import-file">Choose an exported JSON file</label><br><input type="file" id="nac-import-file" accept=".json,application/json"></p>
                        <p><label for="nac-import-json">Or paste exported settings</label><br><textarea id="nac-import-json" name="settings_json" rows="5" maxlength="65536" required spellcheck="false"></textarea></p>
                        <p class="description" id="nac-import-status" role="status">Up to 64 KB. Importing replaces the current settings; saved palettes are kept.</p>
                        <button type="submit" class="button" id="nac-import-button">Import and Apply</button>
                    </form>
                </details>
                <form action="<?php echo esc_url($post); ?>" method="post" class="nac-reset-form">
                    <input type="hidden" name="action" value="native_admin_colours_reset">
                    <?php wp_nonce_field('native_admin_colours_reset'); ?>
                    <?php submit_button('Reset to Defaults', 'secondary', 'submit', false); ?>
                    <p class="description">Resets colours and sizing. Your saved palettes are kept.</p>
                </form>
                <p>Choose the scheme in <a href="<?php echo esc_url(admin_url('profile.php')); ?>">your profile</a>.</p>
            </div>
            <div class="nac-preview-column">
                <h2>Live preview</h2>
                <div class="nac-colour-preview" aria-label="Example of your admin appearance">
                    <div class="nac-preview-bar"><?php echo esc_html(get_bloginfo('name')); ?> <span class="nac-preview-toolbar-focus">Focused item</span></div>
                    <div class="nac-preview-body">
                        <div class="nac-preview-nav"><strong>WordPress</strong><span>Dashboard</span><span class="is-current">Contacts</span><div class="nac-preview-submenu"><span>All contacts</span><span>Lists and tags</span></div><span>Plugins</span><span class="is-focused">Focused item</span><span>Profile</span></div>
                        <div class="nac-preview-content"><h3>Your workspace</h3><div class="nac-preview-card"><strong>Your colours, familiar tools.</strong><p>A native WordPress dashboard with your branding.</p><span class="nac-preview-button">Add Contact</span><p class="nac-preview-link">View all contacts →</p></div><h4>Plugins</h4><div class="nac-preview-plugin"><strong>Example active plugin</strong><br><span class="nac-preview-link">Deactivate</span></div><div class="nac-preview-inactive">Example inactive plugin</div></div>
                    </div>
                </div>
                <p class="description">Preview only. Save Colours to apply. Third-party screens may use their own styles. Collapsed and mobile navigation keep WordPress’s native width.</p>
                <p class="nac-preview-warning" role="status"></p>
            </div>
        </div>
    </div>
    <?php
}
