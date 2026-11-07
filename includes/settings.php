<?php
namespace Leapfrog\Admin;

if (!defined('ABSPATH')) { exit; }

/** Export files carry this id. Files from Native Admin Colours still import. */
const EXPORT_ID = 'leapfrog-admin';
const IMPORT_IDS = ['leapfrog-admin', 'native-admin-colours'];

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
    $saved = get_option(OPTION_PRESETS, []);
    return is_array($saved) ? array_slice($saved, 0, 20, true) : [];
}

function authorise(string $action): void
{
    if (!current_user_can('manage_options')) {
        wp_die('You do not have permission to change these settings.', '', ['response' => 403]);
    }
    check_admin_referer($action, '_lfa_nonce');
}

function return_to_page(string $notice): void
{
    wp_safe_redirect(page_url(['lfa_notice' => $notice]));
    exit;
}

function settings_menu(): void
{
    add_options_page('Leapfrog Admin', 'Leapfrog Admin', 'manage_options', PAGE, __NAMESPACE__ . '\\render_settings_page');
}
add_action('admin_menu', __NAMESPACE__ . '\\settings_menu');

/** Old bookmarks (Appearance > Native Admin Colours) open the new screen. */
function redirect_old_settings_pages(): void
{
    $page = isset($_GET['page']) && is_string($_GET['page']) ? $_GET['page'] : '';
    if (!in_array($page, ['native-admin-colours', 'praxis-living-colours'], true) || !current_user_can('manage_options')) {
        return;
    }
    // Leave the old plugin's own screen alone while it is still active.
    if ($page === 'native-admin-colours' && function_exists('NativeAdminColours\\register_scheme')) {
        return;
    }
    wp_safe_redirect(page_url());
    exit;
}
// menu.php checks access before admin_init, so redirect before that check.
add_action('admin_menu', __NAMESPACE__ . '\\redirect_old_settings_pages', 100);

function register_settings(): void
{
    register_setting('leapfrog_admin_colours_settings', OPTION_COLOURS, [
        'type' => 'array',
        'default' => defaults(),
        'sanitize_callback' => __NAMESPACE__ . '\\sanitize_colours',
    ]);
}
add_action('admin_init', __NAMESPACE__ . '\\register_settings');

function sanitize_colours($input): array
{
    $result = colours();
    foreach (fields() as $key => $field) {
        // Partial input (an older form, for example) must not reset other controls.
        if (!is_array($input) || !array_key_exists($key, $input)) {
            continue;
        }
        if (valid_value($input[$key], $field)) {
            $result[$key] = $field['type'] === 'number' ? (int) $input[$key] : ($input[$key] === '' ? '' : normalise_colour($input[$key]));
        } else {
            add_settings_error(OPTION_COLOURS, 'invalid_' . $key, 'Please check ' . $field['label'] . '. ' . ($field['help'] ?? 'Enter a valid hex colour.'));
        }
    }
    return $result;
}

function settings_assets(string $hook): void
{
    if ($hook !== PAGE_HOOK) {
        return;
    }
    wp_enqueue_style('wp-color-picker');
    if (current_user_can('upload_files')) {
        wp_enqueue_media();
    }
    wp_enqueue_style('lfa-settings', asset_url('assets/settings.css'), [], asset_version('assets/settings.css'));
    $dependencies = current_user_can('upload_files') ? ['wp-color-picker', 'media-views'] : ['wp-color-picker'];
    wp_enqueue_script('lfa-settings', asset_url('assets/settings.js'), $dependencies, asset_version('assets/settings.js'), true);
}
add_action('admin_enqueue_scripts', __NAMESPACE__ . '\\settings_assets');

function reset_colours(): void
{
    authorise('leapfrog_admin_reset');
    update_option(OPTION_COLOURS, defaults());
    return_to_page('reset');
}
add_action('admin_post_leapfrog_admin_reset', __NAMESPACE__ . '\\reset_colours');

function save_preset(): void
{
    authorise('leapfrog_admin_presets');
    $name = isset($_POST['preset_name']) && is_string($_POST['preset_name']) ? sanitize_text_field(wp_unslash($_POST['preset_name'])) : '';
    if ($name === '' || strlen($name) > 80) { wp_die('Give the palette a name of up to 80 characters. Use your browser’s Back button to keep editing.', '', ['response' => 400]); }
    $palette = validate_palette(isset($_POST[OPTION_COLOURS]) ? wp_unslash($_POST[OPTION_COLOURS]) : null);
    if (is_wp_error($palette)) { wp_die(esc_html($palette->get_error_message()), '', ['response' => 400]); }
    $presets = saved_presets();
    $id = substr(hash('sha256', strtolower($name)), 0, 24);
    if (!isset($presets[$id]) && count($presets) >= 20) { wp_die('You can save up to 20 palettes. Delete one before adding another.', '', ['response' => 400]); }
    $presets[$id] = ['name' => $name, 'settings' => $palette];
    update_option(OPTION_PRESETS, $presets, false);
    update_option(OPTION_COLOURS, $palette);
    return_to_page('preset_saved');
}
add_action('admin_post_leapfrog_admin_save_preset', __NAMESPACE__ . '\\save_preset');

function manage_preset(): void
{
    authorise('leapfrog_admin_presets');
    $presets = saved_presets();
    $id = isset($_POST['preset_id']) && is_string($_POST['preset_id']) ? sanitize_key(wp_unslash($_POST['preset_id'])) : '';
    if (!isset($presets[$id])) { wp_die('Please choose a saved palette.', '', ['response' => 400]); }
    if (isset($_POST['preset_operation']) && $_POST['preset_operation'] === 'delete') {
        unset($presets[$id]);
        update_option(OPTION_PRESETS, $presets, false);
        return_to_page('preset_deleted');
    }
    $palette = validate_palette($presets[$id]['settings'] ?? null);
    if (is_wp_error($palette)) { wp_die('This palette contains invalid settings.', '', ['response' => 400]); }
    update_option(OPTION_COLOURS, $palette);
    return_to_page('preset_loaded');
}
add_action('admin_post_leapfrog_admin_manage_preset', __NAMESPACE__ . '\\manage_preset');

function export_settings(): void
{
    authorise('leapfrog_admin_export');
    nocache_headers();
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="leapfrog-admin-colours.json"');
    header('X-Content-Type-Options: nosniff');
    echo wp_json_encode(['plugin' => EXPORT_ID, 'type' => 'colours', 'format_version' => 1, 'settings' => colours()], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}
add_action('admin_post_leapfrog_admin_export', __NAMESPACE__ . '\\export_settings');

function parse_import($json)
{
    if (!is_string($json) || strlen($json) > 65536) { return new \WP_Error('invalid_import', 'Choose a Leapfrog Admin colours file smaller than 64 KB.'); }
    $data = json_decode($json, true);
    if (!is_array($data) || !in_array($data['plugin'] ?? '', IMPORT_IDS, true) || ($data['format_version'] ?? null) !== 1 || ($data['type'] ?? 'colours') !== 'colours') {
        return new \WP_Error('invalid_import', 'This is not a Leapfrog Admin (or Native Admin Colours) colours file.');
    }
    return validate_palette($data['settings'] ?? null);
}

function import_settings(): void
{
    authorise('leapfrog_admin_import');
    $palette = parse_import(isset($_POST['settings_json']) ? wp_unslash($_POST['settings_json']) : null);
    if (is_wp_error($palette)) { wp_die(esc_html($palette->get_error_message()) . ' No settings were changed.', '', ['response' => 400]); }
    update_option(OPTION_COLOURS, $palette);
    return_to_page('imported');
}
add_action('admin_post_leapfrog_admin_import', __NAMESPACE__ . '\\import_settings');

function render_settings_page(): void
{
    if (!current_user_can('manage_options')) { return; }
    $values = colours();
    $presets = saved_presets();
    $post = admin_url('admin-post.php');
    $notices = ['preset_saved' => 'Palette saved and applied.', 'preset_loaded' => 'Saved palette applied.', 'preset_deleted' => 'Saved palette deleted. Current colours are unchanged.', 'imported' => 'Settings imported and applied.', 'reset' => 'Colours and sizing reset to the defaults. Saved palettes are kept.', 'icon_saved' => 'Toolbar icon saved. Your site favicon is unchanged.', 'icon_removed' => 'Default toolbar icon restored.'];
    $notice = isset($_GET['lfa_notice']) && is_string($_GET['lfa_notice']) ? sanitize_key(wp_unslash($_GET['lfa_notice'])) : '';
    $usingScheme = get_user_option('admin_color') === SCHEME;
    // Settings pages print settings_errors() themselves (wp-admin/options-head.php).
    ?>
    <div class="wrap lfa-settings">
        <h1>Leapfrog Admin</h1>
        <h2 class="lfa-section-title">Admin colours</h2>
        <p>Personalise your WordPress workspace. These settings apply to everyone who chooses the <strong>Leapfrog</strong> colour scheme in their profile.</p>
        <?php if (!$usingScheme) : ?><div class="notice notice-info inline"><p>You are not using the Leapfrog scheme yet. Choose it under <a href="<?php echo esc_url(admin_url('profile.php')); ?>">Profile &gt; Admin Colour Scheme</a> to see these colours across the dashboard.</p></div><?php endif; ?>
        <?php if (isset($notices[$notice])) : ?><div class="notice notice-success is-dismissible"><p><?php echo esc_html($notices[$notice]); ?></p></div><?php endif; ?>
        <div class="lfa-colour-layout">
            <div class="lfa-colour-controls">
                <form action="options.php" method="post" id="lfa-settings-form">
                    <?php settings_fields('leapfrog_admin_colours_settings'); ?>
                    <?php wp_nonce_field('leapfrog_admin_presets', '_lfa_nonce', false); ?>
                    <?php foreach (['Main colours', 'Extra colours', 'Sizing'] as $group) : ?>
                    <details class="lfa-panel" <?php if ($group === 'Main colours') { echo 'open'; } ?>>
                        <summary><?php echo esc_html($group); ?></summary>
                        <?php if ($group === 'Extra colours') : ?><p class="description">Leave a field blank to use its automatic colour. Clear a colour to return to Automatic.</p><?php endif; ?>
                        <div class="lfa-fields">
                        <?php foreach (fields() as $key => $field) : if ($field['group'] !== $group) { continue; } ?>
                            <div class="lfa-colour-field">
                                <label for="lfa-<?php echo esc_attr($key); ?>"><?php echo esc_html($field['label']); ?></label>
                                <?php if ($field['type'] === 'number') : ?>
                                <input id="lfa-<?php echo esc_attr($key); ?>" name="<?php echo esc_attr(OPTION_COLOURS); ?>[<?php echo esc_attr($key); ?>]" data-lfa-key="<?php echo esc_attr($key); ?>" type="number" min="<?php echo (int) $field['min']; ?>" max="<?php echo (int) $field['max']; ?>" step="1" value="<?php echo (int) $values[$key]; ?>" required aria-describedby="lfa-help-<?php echo esc_attr($key); ?>"> <span>px</span>
                                <?php else : ?>
                                <input class="lfa-colour-picker" id="lfa-<?php echo esc_attr($key); ?>" name="<?php echo esc_attr(OPTION_COLOURS); ?>[<?php echo esc_attr($key); ?>]" data-lfa-key="<?php echo esc_attr($key); ?>" data-default-color="<?php echo esc_attr($field['default']); ?>" value="<?php echo esc_attr($values[$key]); ?>" type="text" placeholder="<?php echo $field['default'] === '' ? 'Automatic' : '#rrggbb'; ?>" aria-describedby="lfa-help-<?php echo esc_attr($key); ?>">
                                <?php endif; ?>
                                <p class="description" id="lfa-help-<?php echo esc_attr($key); ?>"><?php echo esc_html($field['help'] ?? 'Choose a colour or enter a hex code.'); ?></p>
                            </div>
                        <?php endforeach; ?>
                        </div>
                    </details>
                    <?php endforeach; ?>
                    <?php submit_button('Save Colours'); ?>
                    <details class="lfa-panel">
                        <summary>Save a named palette</summary>
                        <p class="description">Save and apply the colours and sizing currently shown above. Using an existing name replaces that saved palette.</p>
                        <p><label for="lfa-preset-name">Palette name</label><br><input type="text" id="lfa-preset-name" name="preset_name" maxlength="80" placeholder="e.g. Ocean blue"></p>
                        <button type="submit" class="button" name="action" value="leapfrog_admin_save_preset" formaction="<?php echo esc_url($post); ?>">Save and Apply Palette</button>
                    </details>
                </form>
                <?php render_toolbar_icon_settings(); ?>
                <details class="lfa-panel">
                    <summary>Saved palettes</summary>
                    <?php if (!$presets) : ?><p>No palettes saved yet. Use “Save a named palette” above.</p><?php else : ?>
                    <form action="<?php echo esc_url($post); ?>" method="post">
                        <input type="hidden" name="action" value="leapfrog_admin_manage_preset">
                        <?php wp_nonce_field('leapfrog_admin_presets', '_lfa_nonce'); ?>
                        <p><label for="lfa-preset-id">Choose a palette</label><br><select name="preset_id" id="lfa-preset-id"><?php foreach ($presets as $id => $preset) : ?><option value="<?php echo esc_attr($id); ?>"><?php echo esc_html($preset['name']); ?></option><?php endforeach; ?></select></p>
                        <button type="submit" class="button" name="preset_operation" value="load">Apply Palette</button>
                        <button type="submit" class="button" name="preset_operation" value="delete">Delete Palette</button>
                        <p class="description">Applying a palette replaces the current saved settings. Deleting a palette leaves the current settings in place.</p>
                    </form>
                    <?php endif; ?>
                </details>
                <details class="lfa-panel">
                    <summary>Import and export</summary>
                    <p>Move your saved colours and sizing between sites. Files exported from Native Admin Colours can be imported too.</p>
                    <p><a class="button" href="<?php echo esc_url(wp_nonce_url(add_query_arg('action', 'leapfrog_admin_export', $post), 'leapfrog_admin_export', '_lfa_nonce')); ?>">Export Saved Settings</a></p>
                    <form action="<?php echo esc_url($post); ?>" method="post" id="lfa-import-form">
                        <input type="hidden" name="action" value="leapfrog_admin_import">
                        <?php wp_nonce_field('leapfrog_admin_import', '_lfa_nonce'); ?>
                        <p><label for="lfa-import-file">Choose an exported JSON file</label><br><input type="file" id="lfa-import-file" accept=".json,application/json"></p>
                        <p><label for="lfa-import-json">Or paste exported settings</label><br><textarea id="lfa-import-json" name="settings_json" rows="5" maxlength="65536" required spellcheck="false"></textarea></p>
                        <p class="description" id="lfa-import-status" role="status">Up to 64 KB. Importing replaces the current settings; saved palettes are kept.</p>
                        <button type="submit" class="button" id="lfa-import-button">Import and Apply</button>
                    </form>
                </details>
                <form action="<?php echo esc_url($post); ?>" method="post" class="lfa-reset-form">
                    <input type="hidden" name="action" value="leapfrog_admin_reset">
                    <?php wp_nonce_field('leapfrog_admin_reset', '_lfa_nonce'); ?>
                    <?php submit_button('Reset to Defaults', 'secondary', 'submit', false); ?>
                    <p class="description">Resets colours and sizing. Your saved palettes are kept.</p>
                </form>
                <p>Choose the scheme in <a href="<?php echo esc_url(admin_url('profile.php')); ?>">your profile</a>.</p>
            </div>
            <div class="lfa-preview-column">
                <h2>Live preview</h2>
                <div class="lfa-colour-preview" aria-label="Example of your admin appearance">
                    <div class="lfa-preview-bar"><?php echo esc_html(get_bloginfo('name')); ?> <span class="lfa-preview-toolbar-focus">Focused item</span></div>
                    <div class="lfa-preview-body">
                        <div class="lfa-preview-nav"><strong>WordPress</strong><span>Dashboard</span><span class="is-current">Contacts</span><div class="lfa-preview-submenu"><span>All contacts</span><span>Lists and tags</span></div><span>Plugins</span><span class="is-focused">Focused item</span><span>Profile</span></div>
                        <div class="lfa-preview-content"><h3>Your workspace</h3><div class="lfa-preview-card"><strong>Your colours, familiar tools.</strong><p>A native WordPress dashboard with your branding.</p><span class="lfa-preview-button">Add Contact</span><p class="lfa-preview-link">View all contacts →</p></div><h4>Plugins</h4><div class="lfa-preview-plugin"><strong>Example active plugin</strong><br><span class="lfa-preview-link">Deactivate</span></div><div class="lfa-preview-inactive">Example inactive plugin</div></div>
                    </div>
                </div>
                <p class="description">Preview only. Save Colours to apply. Third-party screens may use their own styles. Collapsed and mobile navigation keep WordPress’s native width.</p>
                <p class="lfa-preview-warning" role="status"></p>
            </div>
        </div>
    </div>
    <?php
}
