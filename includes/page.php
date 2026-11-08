<?php
namespace Leapfrog\Admin;

if (!defined('ABSPATH')) { exit; }

/** Leapfrog > Admin: one screen with a tab per switched-on feature, plus Features. */
function admin_menu(): void
{
    add_submenu_page(MENU_SLUG, 'Leapfrog Admin', 'Leapfrog Admin', 'manage_options', PAGE, __NAMESPACE__ . '\\render_page');
}
add_action('admin_menu', __NAMESPACE__ . '\\admin_menu');

/**
 * Old addresses open the new screen: Settings > Leapfrog Admin (2.0), and
 * Appearance > Native Admin Colours or Praxis Living colours (before that).
 * WordPress checks access to a page before admin_init, so this runs on admin_menu.
 */
function redirect_old_pages(): void
{
    global $pagenow;
    $page = isset($_GET['page']) && is_string($_GET['page']) ? $_GET['page'] : '';
    if (!current_user_can('manage_options')) {
        return;
    }
    if ($page === PAGE && $pagenow === 'options-general.php') {
        $args = array_map('sanitize_text_field', wp_unslash(array_diff_key($_GET, ['page' => 1])));
        wp_safe_redirect(page_url($args));
        exit;
    }
    if (!in_array($page, ['native-admin-colours', 'praxis-living-colours'], true)) {
        return;
    }
    // Leave the old plugin's own screen alone while it is still active.
    if ($page === 'native-admin-colours' && function_exists('NativeAdminColours\\register_scheme')) {
        return;
    }
    wp_safe_redirect(page_url(['tab' => 'colours']));
    exit;
}
add_action('admin_menu', __NAMESPACE__ . '\\redirect_old_pages', 100);

function authorise(string $action): void
{
    if (!current_user_can('manage_options')) {
        wp_die('You do not have permission to change these settings.', '', ['response' => 403]);
    }
    check_admin_referer($action, '_lfa_nonce');
}

/** Back to the screen with a message: $notice is a key of notices() or errors(). */
function return_to_page(string $notice, array $args = []): void
{
    $key = isset(errors()[$notice]) ? 'lfa_error' : 'lfa_notice';
    wp_safe_redirect(page_url($args + [$key => $notice]));
    exit;
}

function notices(): array
{
    return [
        'preset_saved' => 'Palette saved and applied.',
        'preset_loaded' => 'Saved palette applied.',
        'preset_deleted' => 'Saved palette deleted. Current colours are unchanged.',
        'imported' => 'Settings imported and applied.',
        'reset' => 'Colours and sizing reset to the defaults. Saved palettes are kept.',
        'icon_saved' => 'Toolbar icon saved. Your site favicon is unchanged.',
        'icon_removed' => 'Default toolbar icon restored.',
        'features_saved' => 'Features saved.',
        'role_added' => 'Role created. Choose what it is allowed to do, then save.',
        'role_saved' => 'Role saved.',
        'role_deleted' => 'Role deleted.',
        'role_restored' => 'Role restored to how it was before Leapfrog first changed it.',
        'menu_saved' => 'Admin menu saved.',
        'menu_reset' => 'Admin menu changes removed for this layer.',
        'bar_saved' => 'Admin bar saved.',
        'bar_reset' => 'Admin bar changes removed for this layer.',
        'dashboard_saved' => 'Dashboard saved.',
        'dashboard_reset' => 'Dashboard changes removed for this layer.',
        'notices_saved' => 'Notices saved.',
        'notices_reset' => 'Notices changes removed for this layer.',
        'login_saved' => 'Login page saved.',
        'columns_saved' => 'List columns saved.',
        'columns_reset' => 'This list shows WordPress’s own columns again.',
    ];
}

function errors(): array
{
    return [
        'role_name' => 'Give the role a name of up to 60 characters.',
        'role_missing' => 'That role no longer exists.',
        'role_protected' => 'This role can’t be deleted: it is built into WordPress, it is the role new users get, or you have it yourself.',
        'role_move' => 'Choose another role for the people who have this one.',
        'role_permission' => 'Permission names can use lower-case letters, numbers and underscores only.',
        'menu_invalid' => 'The menu changes could not be read. Nothing was saved; please try again.',
        'menu_self_lock' => 'Not saved: this would hide Leapfrog or Leapfrog Admin from you. Hide it for other roles or people instead.',
        'bar_link' => 'Each link needs a name and a web address. Nothing was saved.',
        'columns_type' => 'That list no longer exists. Nothing was saved.',
        'login_redirect' => 'A log-in or log-out address isn’t valid: start it with / or give a full web address. Nothing was saved.',
    ];
}

/**
 * Put items in a saved order (admin menu, list columns). Items the order doesn't
 * know (added by a plugin since) go after the item they followed originally.
 */
function sort_by_order(array $items, array $order, callable $idOf): array
{
    $byId = [];
    $originalIds = [];
    foreach ($items as $item) {
        $id = (string) $idOf($item);
        if (!isset($byId[$id])) {
            $byId[$id] = $item;
            $originalIds[] = $id;
        }
    }
    $result = array_values(array_unique(array_filter(array_map('strval', $order), static function ($id) use ($byId) { return isset($byId[$id]); })));
    $placed = array_flip($result);
    foreach ($originalIds as $index => $id) {
        if (isset($placed[$id])) { continue; }
        $position = 0;
        for ($j = $index - 1; $j >= 0; $j--) {
            $found = array_search($originalIds[$j], $result, true);
            if ($found !== false) { $position = $found + 1; break; }
        }
        array_splice($result, $position, 0, [$id]);
        $placed[$id] = true;
    }
    return array_map(static function ($id) use ($byId) { return $byId[$id]; }, $result);
}

/** Tabs for the switched-on features, in order, then Features. */
function tabs(): array
{
    $tabs = [];
    foreach (features() as $id => $feature) {
        if (feature_enabled($id)) {
            $tabs[$id] = ['label' => $feature['label'], 'render' => $feature['render']];
        }
    }
    $tabs['features'] = ['label' => 'Features', 'render' => __NAMESPACE__ . '\\render_features_tab'];
    return $tabs;
}

function current_tab(): string
{
    $tabs = tabs();
    $tab = isset($_GET['tab']) && is_string($_GET['tab']) ? sanitize_key($_GET['tab']) : '';
    return isset($tabs[$tab]) ? $tab : (string) array_key_first($tabs);
}

function page_assets(string $hook): void
{
    if ($hook !== PAGE_HOOK) {
        return;
    }
    wp_enqueue_style('lfa-page', asset_url('assets/page.css'), [], asset_version('assets/page.css'));
    wp_enqueue_script('lfa-page', asset_url('assets/page.js'), [], asset_version('assets/page.js'), true);
}
add_action('admin_enqueue_scripts', __NAMESPACE__ . '\\page_assets');

function render_page(): void
{
    if (!current_user_can('manage_options')) { return; }
    $tabs = tabs();
    $current = current_tab();
    $notice = isset($_GET['lfa_notice']) && is_string($_GET['lfa_notice']) ? sanitize_key($_GET['lfa_notice']) : '';
    $error = isset($_GET['lfa_error']) && is_string($_GET['lfa_error']) ? sanitize_key($_GET['lfa_error']) : '';
    ?>
    <div class="wrap lfa-settings">
        <h1>Leapfrog Admin <span class="lfa-version"><?php echo esc_html(VERSION); ?></span></h1>
        <nav class="nav-tab-wrapper lfa-tabs" aria-label="Leapfrog Admin sections">
            <?php foreach ($tabs as $id => $tab) : ?>
                <a href="<?php echo esc_url(page_url(['tab' => $id])); ?>" class="nav-tab<?php echo $id === $current ? ' nav-tab-active' : ''; ?>" <?php if ($id === $current) { echo 'aria-current="page"'; } ?>><?php echo esc_html($tab['label']); ?></a>
            <?php endforeach; ?>
        </nav>
        <?php
        // Pages outside the Settings menu print the Settings API messages themselves.
        settings_errors();
        if (isset(notices()[$notice])) {
            echo '<div class="notice notice-success is-dismissible lfa-notice-keep"><p>' . esc_html(notices()[$notice]) . '</p></div>';
        }
        if (isset(errors()[$error])) {
            echo '<div class="notice notice-error is-dismissible lfa-notice-keep"><p>' . esc_html(errors()[$error]) . '</p></div>';
        }
        ($tabs[$current]['render'])();
        ?>
    </div>
    <?php
}
