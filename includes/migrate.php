<?php
namespace Leapfrog\Admin;

if (!defined('ABSPATH')) { exit; }

/** Scheme ids used by earlier versions of this plugin. */
const OLD_SCHEMES = ['native-admin-colours', 'praxis-living'];

/**
 * Carry settings and scheme choices forward from Native Admin Colours (and the
 * older Praxis Living plugin). Runs on activation and once after each update.
 * Old options are copied, not deleted, so the old plugin still works if it is
 * switched back on.
 */
function migrate(): void
{
    // Colour helpers, even while the Colours feature is switched off (this adds no hooks).
    require_once __DIR__ . '/colours.php';
    require_once __DIR__ . '/colours-settings.php';

    if (get_option(OPTION_COLOURS, false) === false) {
        $old = get_option('native_admin_colours', false);
        if (is_array($old)) {
            add_option(OPTION_COLOURS, $old);
        } else {
            $legacy = get_option('praxis_living_admin_colours', false);
            if (is_array($legacy)) {
                $palette = defaults();
                foreach (['navigation', 'accent', 'action', 'canvas'] as $key) {
                    $palette[$key] = normalise_colour($legacy[$key] ?? '') ?: $palette[$key];
                }
                add_option(OPTION_COLOURS, $palette);
            }
        }
    }

    if (get_option(OPTION_PRESETS, false) === false) {
        $old = get_option('native_admin_colours_presets', false);
        if (is_array($old)) {
            add_option(OPTION_PRESETS, $old, '', false);
        }
    }

    if (get_option(OPTION_TOOLBAR_ICON, false) === false) {
        $old = absint(get_option('native_admin_toolbar_icon', 0));
        if ($old) {
            add_option(OPTION_TOOLBAR_ICON, $old);
        }
    }

    // admin_color is network-wide user meta, so include users from every site.
    $users = get_users(['blog_id' => 0, 'fields' => 'ID', 'meta_query' => [['key' => 'admin_color', 'value' => OLD_SCHEMES, 'compare' => 'IN']]]);
    foreach ($users as $userId) {
        update_user_meta((int) $userId, 'admin_color', SCHEME);
    }

    update_option(OPTION_VERSION, VERSION);
}

/** Updates do not run the activation hook, so check the stored version too. */
function maybe_migrate(): void
{
    if (get_option(OPTION_VERSION) !== VERSION) {
        migrate();
    }
}
add_action('admin_init', __NAMESPACE__ . '\\maybe_migrate', 5);

/** Both plugins would add a toolbar icon and a second scheme, so suggest switching the old one off. */
function old_plugin_notice(): void
{
    if (!current_user_can('activate_plugins') || !function_exists('NativeAdminColours\\register_scheme')) {
        return;
    }
    $screen = get_current_screen();
    if (!in_array($screen->base ?? '', ['plugins', PAGE_HOOK], true)) {
        return;
    }
    echo '<div class="notice notice-warning"><p>' . esc_html__('Native Admin Colours is still active. Leapfrog Admin has copied its settings, so you can deactivate Native Admin Colours now.', 'leapfrog-admin') . '</p></div>';
}
add_action('admin_notices', __NAMESPACE__ . '\\old_plugin_notice');
