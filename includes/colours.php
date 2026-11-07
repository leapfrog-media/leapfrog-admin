<?php
namespace Leapfrog\Admin;

if (!defined('ABSPATH')) { exit; }

/** Register a native, per-user scheme; no permissions or preferences are forced. */
function register_scheme(): void
{
    $colours = colours();
    wp_admin_css_color(
        SCHEME,
        __('Leapfrog', 'leapfrog-admin'),
        add_query_arg('v', asset_version('assets/colors.min.css'), asset_url('assets/colors.min.css')),
        [$colours['navigation'], shade($colours['navigation'], 0.15), $colours['accent'], $colours['action']],
        ['base' => $colours['menu_text'] ?: ink($colours['navigation']), 'focus' => $colours['menu_focus_text'] ?: ink($colours['accent']), 'current' => $colours['menu_selected_text'] ?: ink($colours['accent'])]
    );
}
add_action('admin_init', __NAMESPACE__ . '\\register_scheme', 20);

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
    $saved = get_option(OPTION_COLOURS, []);
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
        '--lfa-nav' => $c['navigation'],
        '--lfa-submenu' => $submenu,
        '--lfa-submenu-text' => $c['submenu_text'] ?: ink($submenu),
        '--lfa-submenu-hover' => contrast($c['accent'], $submenu) >= 4.5 ? $c['accent'] : ink($submenu),
        '--lfa-toolbar' => $toolbar,
        '--lfa-toolbar-text' => $c['toolbar_text'] ?: ink($toolbar),
        '--lfa-toolbar-hover' => contrast($c['accent'], $toolbar) >= 4.5 ? $c['accent'] : ink($toolbar),
        '--lfa-toolbar-focus-text' => $c['toolbar_focus_text'] ?: ink($toolbarSurface),
        '--lfa-toolbar-surface' => $toolbarSurface,
        '--lfa-nav-lighter' => shade($c['navigation'], 0.10),
        '--lfa-menu-text' => $navigationInk,
        '--lfa-menu-selected-text' => $c['menu_selected_text'] ?: ink($c['accent']),
        '--lfa-menu-focus-text' => $c['menu_focus_text'] ?: ink($c['accent']),
        '--lfa-accent' => $c['accent'],
        '--lfa-accent-ink' => ink($c['accent']),
        '--lfa-accent-hover' => contrast($c['accent'], $c['navigation']) >= 4.5 ? $c['accent'] : $navigationInk,
        '--lfa-accent-muted' => shade($c['accent'], -0.10),
        '--lfa-action' => $c['action'],
        '--lfa-action-ink' => ink($c['action']),
        '--lfa-action-hover-10' => shade($c['action'], -0.10),
        '--lfa-action-hover-20' => shade($c['action'], -0.20),
        '--lfa-action-bright' => shade($c['action'], 0.15),
        '--lfa-action-surface' => shade($c['action'], -0.10),
        '--lfa-link' => $link,
        '--lfa-link-focus' => shade($link, luminance($c['canvas']) > 0.2 ? -0.15 : 0.15),
        '--lfa-canvas' => $c['canvas'],
        '--lfa-canvas-ink' => ink($c['canvas']),
        '--lfa-active-plugin' => $c['active_plugin'],
        '--lfa-active-plugin-ink' => ink($c['active_plugin']),
        '--lfa-menu-width' => $c['menu_width'] . 'px',
        '--lfa-menu-font-size' => $c['menu_font_size'] . 'px',
        '--lfa-button-radius' => $c['button_radius'] . 'px',
        '--lfa-action-rgb' => implode(', ', sscanf($c['action'], '#%02x%02x%02x')),
    ];
}

function colour_variables(): void
{
    $screen = get_current_screen();
    if (get_user_option('admin_color') !== SCHEME && !in_array($screen->base ?? '', ['profile', 'user-edit', PAGE_HOOK], true)) {
        return;
    }
    // Profile previews need the values available, but they must only take
    // effect while this scheme is selected for the current viewer.
    $css = 'body.admin-color-' . SCHEME . '{';
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
    wp_enqueue_script('lfa-profile-scheme', asset_url('assets/profile-scheme.js'), ['user-profile'], asset_version('assets/profile-scheme.js'), true);
}
add_action('admin_enqueue_scripts', __NAMESPACE__ . '\\profile_scheme_assets');
