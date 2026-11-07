<?php
/**
 * Plugin Name: Native Admin Colours
 * Description: A native WordPress admin colour scheme with editable colours under Appearance > Native Admin Colours.
 * Version: 1.3.3
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 6.3
 * Requires PHP: 7.4
 * Text Domain: native-admin-colours
 */

namespace NativeAdminColours;

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/toolbar-icon.php';

/** Register a native, per-user scheme; no permissions or preferences are forced. */
function register_scheme(): void
{
    $colours = colours();
    wp_admin_css_color(
        'native-admin-colours',
        __('Native Admin Colours', 'native-admin-colours'),
        add_query_arg('v', (string) filemtime(__DIR__ . '/assets/colors.min.css'), plugins_url('assets/colors.min.css', __FILE__)),
        [$colours['navigation'], shade($colours['navigation'], 0.15), $colours['accent'], $colours['action']],
        ['base' => $colours['menu_text'] ?: ink($colours['navigation']), 'focus' => $colours['menu_focus_text'] ?: ink($colours['accent']), 'current' => $colours['menu_selected_text'] ?: ink($colours['accent'])]
    );
}

add_action('admin_init', __NAMESPACE__ . '\\register_scheme', 20);

/** Carry settings and opted-in users forward from the original branded plugin. */
function migrate_legacy_settings(): void
{
    if (get_option('native_admin_colours', false) === false) {
        $legacy = get_option('praxis_living_admin_colours', false);
        if (is_array($legacy)) {
            $palette = defaults();
            foreach (['navigation', 'accent', 'action', 'canvas'] as $key) {
                $palette[$key] = normalise_colour($legacy[$key] ?? '') ?: $palette[$key];
            }
            add_option('native_admin_colours', $palette);
        }
    }
    $users = get_users(['meta_key' => 'admin_color', 'meta_value' => 'praxis-living', 'fields' => 'ID']);
    foreach ($users as $userId) {
        update_user_option((int) $userId, 'admin_color', 'native-admin-colours', true);
    }
}
register_activation_hook(__FILE__, __NAMESPACE__ . '\\migrate_legacy_settings');

function defaults(): array
{
    $values = [];
    foreach (fields() as $key => $field) {
        $values[$key] = $field['default'];
    }
    return $values;
}

function colours(): array
{
    $saved = get_option('native_admin_colours', []);
    $saved = is_array($saved) ? $saved : [];
    $result = defaults();
    foreach (fields() as $key => $field) {
        if (array_key_exists($key, $saved) && valid_value($saved[$key], $field)) {
            $result[$key] = $field['type'] === 'number' ? (int) $saved[$key] : ($saved[$key] === '' ? '' : normalise_colour($saved[$key]));
        }
    }
    return $result;
}

function normalise_colour($value): ?string
{
    if (!is_string($value)) {
        return null;
    }
    $value = sanitize_hex_color($value);
    if (!$value) {
        return null;
    }
    if (strlen($value) === 4) {
        $value = '#' . $value[1] . $value[1] . $value[2] . $value[2] . $value[3] . $value[3];
    }
    return strtolower($value);
}

/** Mix toward white for positive amounts and black for negative amounts. */
function shade(string $hex, float $amount): string
{
    $rgb = sscanf($hex, '#%02x%02x%02x');
    $target = $amount >= 0 ? 255 : 0;
    return sprintf('#%02x%02x%02x', ...array_map(static function ($value) use ($target, $amount) {
        return (int) round($value + ($target - $value) * abs($amount));
    }, $rgb));
}

function luminance(string $hex): float
{
    $rgb = array_map(static function ($value) {
        $value /= 255;
        return $value <= 0.04045 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
    }, sscanf($hex, '#%02x%02x%02x'));
    return $rgb[0] * 0.2126 + $rgb[1] * 0.7152 + $rgb[2] * 0.0722;
}

function contrast(string $one, string $two): float
{
    $a = luminance($one);
    $b = luminance($two);
    return (max($a, $b) + 0.05) / (min($a, $b) + 0.05);
}

function ink(string $background): string
{
    return contrast('#ffffff', $background) >= contrast('#161616', $background) ? '#ffffff' : '#161616';
}

/** Generate safe values only: every input has been reduced to a hex colour. */
function variables(): array
{
    $c = colours();
    $link = $c['link'] ?: $c['action'];
    for ($step = 0; $step < 25 && (contrast($link, '#ffffff') < 4.5 || contrast($link, $c['canvas']) < 4.5); $step++) {
        $link = shade($link, luminance($c['canvas']) > 0.2 ? -0.10 : 0.10);
    }
    $navigationInk = $c['menu_text'] ?: ink($c['navigation']);
    $toolbar = $c['toolbar'] ?: $c['navigation'];
    $toolbarSurface = shade($toolbar, -0.22);
    $submenu = $c['submenu'] ?: shade($c['navigation'], -0.22);
    return [
        '--nac-nav' => $c['navigation'],
        '--nac-submenu' => $submenu,
        '--nac-submenu-text' => $c['submenu_text'] ?: ink($submenu),
        '--nac-submenu-hover' => contrast($c['accent'], $submenu) >= 4.5 ? $c['accent'] : ink($submenu),
        '--nac-toolbar' => $toolbar,
        '--nac-toolbar-text' => $c['toolbar_text'] ?: ink($toolbar),
        '--nac-toolbar-hover' => contrast($c['accent'], $toolbar) >= 4.5 ? $c['accent'] : ink($toolbar),
        '--nac-toolbar-focus-text' => $c['toolbar_focus_text'] ?: ink($toolbarSurface),
        '--nac-toolbar-surface' => $toolbarSurface,
        '--nac-nav-lighter' => shade($c['navigation'], 0.10),
        '--nac-menu-text' => $navigationInk,
        '--nac-menu-selected-text' => $c['menu_selected_text'] ?: ink($c['accent']),
        '--nac-menu-focus-text' => $c['menu_focus_text'] ?: ink($c['accent']),
        '--nac-accent' => $c['accent'],
        '--nac-accent-ink' => ink($c['accent']),
        '--nac-accent-hover' => contrast($c['accent'], $c['navigation']) >= 4.5 ? $c['accent'] : $navigationInk,
        '--nac-accent-muted' => shade($c['accent'], -0.10),
        '--nac-action' => $c['action'],
        '--nac-action-ink' => ink($c['action']),
        '--nac-action-hover-10' => shade($c['action'], -0.10),
        '--nac-action-hover-20' => shade($c['action'], -0.20),
        '--nac-action-bright' => shade($c['action'], 0.15),
        '--nac-action-surface' => shade($c['action'], -0.10),
        '--nac-link' => $link,
        '--nac-link-focus' => shade($link, luminance($c['canvas']) > 0.2 ? -0.15 : 0.15),
        '--nac-canvas' => $c['canvas'],
        '--nac-canvas-ink' => ink($c['canvas']),
        '--nac-active-plugin' => $c['active_plugin'],
        '--nac-active-plugin-ink' => ink($c['active_plugin']),
        '--nac-menu-width' => $c['menu_width'] . 'px',
        '--nac-menu-font-size' => $c['menu_font_size'] . 'px',
        '--nac-button-radius' => $c['button_radius'] . 'px',
        '--nac-action-rgb' => implode(', ', sscanf($c['action'], '#%02x%02x%02x')),
    ];
}

function colour_variables(): void
{
    $screen = get_current_screen();
    if (get_user_option('admin_color') !== 'native-admin-colours' && !in_array($screen->base ?? '', ['profile', 'user-edit', 'appearance_page_native-admin-colours'], true)) {
        return;
    }
    // Profile previews need the values available, but they must only take
    // effect while this scheme is selected for the current viewer.
    $css = 'body.admin-color-native-admin-colours{';
    foreach (variables() as $key => $value) {
        $css .= $key . ':' . $value . ';';
    }
    wp_add_inline_style('colors', $css . '}');
}
add_action('admin_enqueue_scripts', __NAMESPACE__ . '\\colour_variables', 100);

/** Keep scheme scoping in sync with WordPress's live self-profile preview. */
function profile_scheme_assets(string $hook): void
{
    if ($hook !== 'profile.php') { return; }
    wp_enqueue_script('nac-profile-scheme', plugins_url('assets/profile-scheme.js', __FILE__), ['user-profile'], (string) filemtime(__DIR__ . '/assets/profile-scheme.js'), true);
}
add_action('admin_enqueue_scripts', __NAMESPACE__ . '\\profile_scheme_assets');

function settings_menu(): void
{
    add_theme_page('Native Admin Colours', 'Native Admin Colours', 'manage_options', 'native-admin-colours', __NAMESPACE__ . '\\settings_page');
}
add_action('admin_menu', __NAMESPACE__ . '\\settings_menu');

function register_settings(): void
{
    register_setting('native_admin_colours_settings', 'native_admin_colours', [
        'type' => 'array',
        'default' => defaults(),
        'sanitize_callback' => __NAMESPACE__ . '\\sanitize_colours',
    ]);

    // An already-open v1.0 form still carries this group, option and nonce.
    // Register the precise legacy option so WordPress retains its normal
    // manage_options check, nonce verification and settings allow-list.
    register_setting('praxis_living_colours', 'praxis_living_admin_colours', [
        'type' => 'array',
        'sanitize_callback' => __NAMESPACE__ . '\\save_legacy_colours',
    ]);
}
add_action('admin_init', __NAMESPACE__ . '\\register_settings');

function save_legacy_colours($input): array
{
    $palette = sanitize_colours($input);
    update_option('native_admin_colours', $palette);
    return $palette;
}

/** Existing bookmarks and post-save redirects should open the renamed screen. */
function redirect_legacy_settings_page(): void
{
    if (!isset($_GET['page']) || !is_string($_GET['page']) || $_GET['page'] !== 'praxis-living-colours' || !current_user_can('manage_options')) {
        return;
    }
    $url = admin_url('themes.php?page=native-admin-colours');
    if (isset($_GET['settings-updated'])) {
        $url = add_query_arg('settings-updated', 'true', $url);
    }
    wp_safe_redirect($url);
    exit;
}
// menu.php checks access before admin_init, so redirect before that check.
add_action('admin_menu', __NAMESPACE__ . '\\redirect_legacy_settings_page', 100);

function sanitize_colours($input): array
{
    $result = colours();
    foreach (fields() as $key => $field) {
        // Old, already-open forms must not reset the newly added controls.
        if (!is_array($input) || !array_key_exists($key, $input)) {
            continue;
        }
        if (valid_value($input[$key], $field)) {
            $result[$key] = $field['type'] === 'number' ? (int) $input[$key] : ($input[$key] === '' ? '' : normalise_colour($input[$key]));
        } else {
            add_settings_error('native_admin_colours', 'invalid_' . $key, 'Please check ' . $field['label'] . '. ' . ($field['help'] ?? 'Enter a valid hex colour.'));
        }
    }
    return $result;
}

function settings_assets(string $hook): void
{
    if ($hook !== 'appearance_page_native-admin-colours') {
        return;
    }
    wp_enqueue_style('wp-color-picker');
    if (current_user_can('upload_files')) {
        wp_enqueue_media();
    }
    wp_enqueue_style('nac-colour-settings', plugins_url('assets/settings.css', __FILE__), [], (string) filemtime(__DIR__ . '/assets/settings.css'));
    $dependencies = current_user_can('upload_files') ? ['wp-color-picker', 'media-views'] : ['wp-color-picker'];
    wp_enqueue_script('nac-colour-settings', plugins_url('assets/settings.js', __FILE__), $dependencies, (string) filemtime(__DIR__ . '/assets/settings.js'), true);
}
add_action('admin_enqueue_scripts', __NAMESPACE__ . '\\settings_assets');

function settings_page(): void
{
    render_settings_page();
}

function reset_colours(): void
{
    if (!current_user_can('manage_options')) {
        wp_die('You do not have permission to change these colours.', '', ['response' => 403]);
    }
    check_admin_referer('native_admin_colours_reset');
    update_option('native_admin_colours', defaults());
    wp_safe_redirect(admin_url('themes.php?page=native-admin-colours&settings-updated=true'));
    exit;
}
add_action('admin_post_native_admin_colours_reset', __NAMESPACE__ . '\\reset_colours');

/** Keep the Reset button in a pre-rename tab functional as well. */
function reset_legacy_colours(): void
{
    if (!current_user_can('manage_options')) {
        wp_die('You do not have permission to change these colours.', '', ['response' => 403]);
    }
    check_admin_referer('praxis_living_reset_colours');
    update_option('native_admin_colours', defaults());
    update_option('praxis_living_admin_colours', defaults());
    wp_safe_redirect(admin_url('themes.php?page=native-admin-colours&settings-updated=true'));
    exit;
}
add_action('admin_post_praxis_living_reset_colours', __NAMESPACE__ . '\\reset_legacy_colours');
