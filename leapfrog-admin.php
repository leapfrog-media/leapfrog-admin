<?php
/**
 * Plugin Name:       Leapfrog Admin
 * Plugin URI:        https://github.com/leapfrog-media/leapfrog-admin
 * Description:       The Leapfrog Media admin: colour scheme, toolbar icon, admin menu and admin bar editors, dashboard, notices, login page, list columns, page tree, profile pictures, code snippets and a user role editor, each switchable and many set per role. Leapfrog > Leapfrog Admin.
 * Version:           2.6.0
 * Requires at least: 6.3
 * Requires PHP:      7.4
 * Author:            Leapfrog Media
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       leapfrog-admin
 * Update URI:        https://github.com/leapfrog-media/leapfrog-admin
 */

namespace Leapfrog\Admin;

if (!defined('ABSPATH')) {
    exit;
}

const VERSION = '2.6.0';
const FILE = __FILE__;

/** Admin colour scheme id. Users who pick it get body.admin-color-leapfrog-admin. */
const SCHEME = 'leapfrog-admin';

/** Settings screen: Leapfrog > Admin (admin.php?page=leapfrog-admin). */
const PAGE = 'leapfrog-admin';
const PAGE_HOOK = 'leapfrog_page_leapfrog-admin';

const OPTION_COLOURS = 'leapfrog_admin_colours';
const OPTION_PRESETS = 'leapfrog_admin_presets';
const OPTION_TOOLBAR_ICON = 'leapfrog_admin_toolbar_icon';
const OPTION_VERSION = 'leapfrog_admin_version';
const OPTION_DISABLED = 'leapfrog_admin_disabled_features';
const OPTION_ROLE_BACKUPS = 'leapfrog_admin_role_backups';

require_once __DIR__ . '/includes/menu.php';
require_once __DIR__ . '/includes/page.php';
require_once __DIR__ . '/includes/features.php';
require_once __DIR__ . '/includes/migrate.php';
require_once __DIR__ . '/includes/github-updater.php';
require_once __DIR__ . '/includes/updates.php';

// A feature that is switched off is not loaded at all.
foreach (features() as $id => $feature) {
    if (feature_enabled($id)) {
        foreach ($feature['files'] as $file) {
            require_once __DIR__ . '/includes/' . $file;
        }
        ($feature['boot'])();
    }
}

register_activation_hook(__FILE__, __NAMESPACE__ . '\\migrate');

function page_url(array $args = []): string
{
    return add_query_arg($args, admin_url('admin.php?page=' . PAGE));
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
