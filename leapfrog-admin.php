<?php
/**
 * Plugin Name:       Leapfrog Admin
 * Plugin URI:        https://github.com/leapfrog-media/leapfrog-admin
 * Description:       Branded, native WordPress admin: an editable admin colour scheme, saved palettes and a custom toolbar icon. Settings > Leapfrog Admin.
 * Version:           2.0.0
 * Requires at least: 6.3
 * Requires PHP:      7.4
 * Author:            Leapfrog Media
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       leapfrog-admin
 */

namespace Leapfrog\Admin;

if (!defined('ABSPATH')) {
    exit;
}

const VERSION = '2.0.0';
const FILE = __FILE__;

/** Admin colour scheme id. Users who pick it get body.admin-color-leapfrog-admin. */
const SCHEME = 'leapfrog-admin';

/** Settings screen: Settings > Leapfrog Admin. */
const PAGE = 'leapfrog-admin';
const PAGE_HOOK = 'settings_page_leapfrog-admin';

const OPTION_COLOURS = 'leapfrog_admin_colours';
const OPTION_PRESETS = 'leapfrog_admin_presets';
const OPTION_TOOLBAR_ICON = 'leapfrog_admin_toolbar_icon';
const OPTION_VERSION = 'leapfrog_admin_version';

require_once __DIR__ . '/includes/colours.php';
require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/toolbar-icon.php';
require_once __DIR__ . '/includes/migrate.php';

register_activation_hook(__FILE__, __NAMESPACE__ . '\\migrate');

function page_url(array $args = []): string
{
    return add_query_arg($args, admin_url('options-general.php?page=' . PAGE));
}

function asset_url(string $path): string
{
    return plugins_url($path, FILE);
}

/** File modification time, so browsers reload assets after an update. */
function asset_version(string $path): string
{
    return (string) filemtime(__DIR__ . '/' . $path);
}
