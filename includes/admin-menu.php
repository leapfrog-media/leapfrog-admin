<?php
namespace Leapfrog\Admin;

if (!defined('ABSPATH')) { exit; }

/**
 * The Admin menu feature: reorder, rename, hide and re-icon menu items and
 * sub-items, and add separators, for everyone, a role or one person (layers.php).
 * Hiding only removes the link; the page still opens from its address.
 *
 * Item ids: a top-level item's menu slug; a sub-item is "parent slug>sub slug".
 */

const OPTION_MENU = 'leapfrog_admin_menu';

/** Leapfrog and Leapfrog Admin can't be hidden from the person making the change. */
const PROTECTED_MENU_ITEMS = ['leapfrog', 'leapfrog>leapfrog-admin'];

function admin_menu_boot(): void
{
    add_action('admin_menu', __NAMESPACE__ . '\\apply_admin_menu', PHP_INT_MAX);
    add_filter('menu_order', __NAMESPACE__ . '\\admin_menu_order_guard', PHP_INT_MAX);
    add_action('admin_post_leapfrog_admin_menu', __NAMESPACE__ . '\\save_admin_menu');
    add_action('admin_enqueue_scripts', __NAMESPACE__ . '\\admin_menu_assets');
}

function on_tab(string $tab): bool
{
    global $pagenow;
    return $pagenow === 'admin.php' && ($_GET['page'] ?? '') === PAGE && current_tab() === $tab;
}

/* -------------------------------------------------------------------------
 * Merging layers
 * ---------------------------------------------------------------------- */

/** One merged result from a stack of layers (see layers_for()). */
function merge_menu(array $stack): array
{
    $merged = ['hidden' => merge_hidden($stack, 'items'), 'titles' => [], 'icons' => [], 'order' => null, 'sub_order' => [], 'separators' => []];
    foreach (layer_list($stack) as $layer) {
        foreach ((array) ($layer['items'] ?? []) as $id => $item) {
            if (($item['title'] ?? '') !== '') { $merged['titles'][$id] = $item['title']; }
            if (($item['icon'] ?? '') !== '') { $merged['icons'][$id] = $item['icon']; }
        }
        if (!empty($layer['order'])) { $merged['order'] = array_values($layer['order']); }
        foreach ((array) ($layer['sub_order'] ?? []) as $parent => $order) {
            $merged['sub_order'][$parent] = array_values((array) $order);
        }
        foreach ((array) ($layer['separators'] ?? []) as $separator) {
            $merged['separators'][$separator] = $separator;
        }
    }
    $merged['separators'] = array_values($merged['separators']);
    return $merged;
}

/**
 * Put items in a saved order. Items the order doesn't know (a plugin installed
 * since) go after the item they followed in WordPress's own order.
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

/** Keep counters (comments waiting, plugin updates) when an item is renamed. */
function title_with_badges(string $title, string $original): string
{
    // Counters are markup after the text, e.g. "Comments <span class="awaiting-mod">…".
    $at = stripos($original, '<span');
    $badges = $at !== false && trim(wp_strip_all_tags(substr($original, 0, $at))) !== '' ? ' ' . substr($original, $at) : '';
    return esc_html($title) . $badges;
}

/** Menu text without counters or markup, for the editor. */
function plain_title(string $title): string
{
    $before = trim(wp_strip_all_tags((string) preg_replace('/<span\b.*$/s', '', $title)));
    $text = $before !== '' ? $before : trim(wp_strip_all_tags($title));
    return html_entity_decode($text, ENT_QUOTES, 'UTF-8');
}

/* -------------------------------------------------------------------------
 * Applying to the menu
 * ---------------------------------------------------------------------- */

function apply_admin_menu(): void
{
    global $menu, $submenu;
    if (!is_array($menu)) { return; }
    if (on_tab('admin-menu')) {
        // The editor shows WordPress's menu as it was before any changes.
        $GLOBALS['lfa_menu_snapshot'] = [$menu, is_array($submenu) ? $submenu : []];
    }
    $user = wp_get_current_user();
    if (!$user->exists()) { return; }
    $m = merge_menu(layers_for(layers_get(OPTION_MENU), $user));
    if (!$m['hidden'] && !$m['titles'] && !$m['icons'] && !$m['order'] && !$m['sub_order'] && !$m['separators']) { return; }

    foreach ($m['separators'] as $separator) {
        $menu[] = ['', 'read', $separator, '', 'wp-menu-separator'];
    }
    foreach ($menu as $key => $item) {
        $id = (string) ($item[2] ?? '');
        if (isset($m['titles'][$id])) { $menu[$key][0] = title_with_badges($m['titles'][$id], (string) $item[0]); }
        if (isset($m['icons'][$id])) { $menu[$key][6] = $m['icons'][$id]; }
    }
    foreach ((array) $submenu as $parent => $subs) {
        foreach ((array) $subs as $index => $sub) {
            $id = $parent . '>' . $sub[2];
            if (!empty($m['hidden'][$id])) {
                unset($submenu[$parent][$index]);
            } elseif (isset($m['titles'][$id])) {
                $submenu[$parent][$index][0] = title_with_badges($m['titles'][$id], (string) $sub[0]);
            }
        }
        if (isset($m['sub_order'][$parent]) && !empty($submenu[$parent])) {
            $submenu[$parent] = sort_by_order($submenu[$parent], $m['sub_order'][$parent], static function ($sub) use ($parent) { return $parent . '>' . $sub[2]; });
        }
    }
    foreach ($menu as $key => $item) {
        if (!empty($m['hidden'][(string) ($item[2] ?? '')])) { unset($menu[$key]); }
    }
    if ($m['order']) {
        uksort($menu, 'strnatcasecmp');
        // New keys in the saved order; WordPress sorts the menu by key next.
        $menu = array_values(sort_by_order($menu, $m['order'], static function ($item) { return $item[2]; }));
        $GLOBALS['lfa_menu_final_order'] = array_column($menu, 2);
    }
}

/** If another plugin also reorders the menu, keep the saved order. */
function admin_menu_order_guard($order)
{
    $final = $GLOBALS['lfa_menu_final_order'] ?? null;
    if (!$final || !is_array($order)) { return $order; }
    return array_column(sort_by_order(array_map(static function ($slug) { return [$slug]; }, $order), $final, static function ($item) { return $item[0]; }), 0);
}

/* -------------------------------------------------------------------------
 * Saving
 * ---------------------------------------------------------------------- */

function clean_menu_id($value): ?string
{
    return is_string($value) && $value !== '' && strlen($value) <= 300 && wp_check_invalid_utf8($value) === $value ? $value : null;
}

function clean_menu_ids($list, int $max = 300): array
{
    $ids = [];
    foreach (is_array($list) ? $list : [] as $value) {
        $id = clean_menu_id($value);
        if ($id !== null) { $ids[$id] = $id; }
    }
    return array_slice(array_values($ids), 0, $max);
}

function sanitize_menu_layer(array $data, bool $isEveryone): array
{
    $clean = [];
    $order = clean_menu_ids($data['order'] ?? null);
    if ($order) { $clean['order'] = $order; }
    foreach (is_array($data['items'] ?? null) ? $data['items'] : [] as $id => $item) {
        $id = clean_menu_id((string) $id);
        if ($id === null || !is_array($item)) { continue; }
        $entry = [];
        if (isset($item['hidden']) && is_bool($item['hidden']) && ($item['hidden'] || !$isEveryone)) {
            $entry['hidden'] = $item['hidden'];
        }
        $title = isset($item['title']) && is_string($item['title']) ? trim(sanitize_text_field($item['title'])) : '';
        if ($title !== '') { $entry['title'] = function_exists('mb_substr') ? mb_substr($title, 0, 60) : substr($title, 0, 60); }
        if (isset($item['icon']) && is_string($item['icon']) && preg_match('/^dashicons-[a-z0-9-]{1,60}$/D', $item['icon'])) {
            $entry['icon'] = $item['icon'];
        }
        if ($entry) { $clean['items'][$id] = $entry; }
    }
    foreach (is_array($data['sub_order'] ?? null) ? $data['sub_order'] : [] as $parent => $list) {
        $parent = clean_menu_id((string) $parent);
        $list = clean_menu_ids($list, 100);
        if ($parent !== null && $list) { $clean['sub_order'][$parent] = $list; }
    }
    foreach (is_array($data['separators'] ?? null) ? $data['separators'] : [] as $separator) {
        if (is_string($separator) && preg_match('/^separator-lfa-[a-z0-9]{4,16}$/D', $separator)) {
            $clean['separators'][] = $separator;
        }
    }
    if (isset($clean['separators'])) { $clean['separators'] = array_slice(array_values(array_unique($clean['separators'])), 0, 20); }
    return $clean;
}

function save_admin_menu(): void
{
    authorise('leapfrog_admin_menu');
    $layer = parse_layer(isset($_POST['layer']) && is_string($_POST['layer']) ? sanitize_text_field(wp_unslash($_POST['layer'])) : '');
    $args = ['tab' => 'admin-menu', 'layer' => layer_param($layer)];
    $previous = get_option(OPTION_MENU, []);
    if (!empty($_POST['lfa_reset'])) {
        layers_set(OPTION_MENU, $layer, []);
        $notice = 'menu_reset';
    } else {
        $data = isset($_POST['menu_json']) && is_string($_POST['menu_json']) ? json_decode(wp_unslash($_POST['menu_json']), true) : null;
        if (!is_array($data)) {
            return_to_page('menu_invalid', $args);
        }
        layers_set(OPTION_MENU, $layer, sanitize_menu_layer($data, $layer['type'] === 'everyone'));
        $notice = 'menu_saved';
    }
    // Never leave the person saving without Leapfrog in their own menu.
    $mine = merge_menu(layers_for(layers_get(OPTION_MENU), wp_get_current_user()));
    foreach (PROTECTED_MENU_ITEMS as $id) {
        if (!empty($mine['hidden'][$id])) {
            update_option(OPTION_MENU, $previous);
            return_to_page('menu_self_lock', $args);
        }
    }
    return_to_page($notice, $args);
}

/* -------------------------------------------------------------------------
 * Editor
 * ---------------------------------------------------------------------- */

function admin_menu_assets(string $hook): void
{
    if ($hook !== PAGE_HOOK || current_tab() !== 'admin-menu') { return; }
    wp_enqueue_script('lfa-admin-menu', asset_url('assets/admin-menu.js'), ['jquery', 'jquery-ui-sortable', 'lfa-page'], asset_version('assets/admin-menu.js'), true);
}

/** Dashicons names, read from WordPress's own stylesheet. */
function dashicon_names(): array
{
    $css = (string) @file_get_contents(ABSPATH . WPINC . '/css/dashicons.css');
    preg_match_all('/\.dashicons-([a-z0-9-]+):before/', $css, $m);
    $names = array_values(array_unique($m[1] ?? []));
    sort($names);
    return $names;
}

/** WordPress's menu (before changes) as editor rows. */
function original_menu(): array
{
    [$menu, $submenu] = $GLOBALS['lfa_menu_snapshot'] ?? [[], []];
    uksort($menu, 'strnatcasecmp');
    $rows = [];
    foreach ($menu as $item) {
        $id = (string) ($item[2] ?? '');
        if ($id === '') { continue; }
        $separator = strpos((string) ($item[4] ?? ''), 'wp-menu-separator') !== false;
        $subs = [];
        // Like WordPress, leave out what the editing person can't open (such as Links
        // while the link manager is off).
        foreach ((array) ($submenu[$id] ?? []) as $sub) {
            if (current_user_can((string) $sub[1])) {
                $subs[] = ['id' => $id . '>' . $sub[2], 'title' => plain_title((string) $sub[0])];
            }
        }
        $onlySelf = count($subs) === 1 && $subs[0]['id'] === $id . '>' . $id;
        if (!$separator && (!$subs || $onlySelf) && !current_user_can((string) ($item[1] ?? 'do_not_allow'))) {
            continue;
        }
        $rows[] = ['id' => $id, 'title' => $separator ? '' : plain_title((string) $item[0]), 'icon' => (string) ($item[6] ?? ''), 'separator' => $separator, 'subs' => $subs];
    }
    return $rows;
}

function render_admin_menu_tab(): void
{
    $layer = current_layer();
    $all = layers_get(OPTION_MENU);
    $data = layer_data($all, $layer);
    $target = $layer['type'] === 'user' ? get_userdata((int) $layer['id']) : null;
    $inherited = merge_menu(layers_for($all, $target ?: null, $layer));
    $payload = [
        'original' => original_menu(),
        'inherited' => $inherited,
        'layer' => (object) $data,
        'everyone' => $layer['type'] === 'everyone',
        'dashicons' => dashicon_names(),
        'protected' => PROTECTED_MENU_ITEMS,
    ];
    render_layer_picker('admin-menu', OPTION_MENU, $layer);
    ?>
    <p class="description lfa-narrow-text">Drag to reorder. Type a new name to rename (leave it empty to keep the name above). Click an icon to change it. Hiding only removes the link: the page still opens from its address. To stop someone using a page, change their role’s permissions on the User roles tab.</p>
    <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" id="lfa-menu-form" class="lfa-track">
        <input type="hidden" name="action" value="leapfrog_admin_menu">
        <input type="hidden" name="layer" value="<?php echo esc_attr(layer_param($layer)); ?>">
        <input type="hidden" name="menu_json" id="lfa-menu-json" value="">
        <?php wp_nonce_field('leapfrog_admin_menu', '_lfa_nonce', false); ?>
        <div class="lfa-menu-actions">
            <button type="button" class="button" id="lfa-add-separator">Add Separator</button>
            <button type="button" class="button-link" id="lfa-toggle-subs" aria-expanded="false">Show all sub-items</button>
            <span id="lfa-order-note" class="lfa-order-note"></span>
        </div>
        <ul id="lfa-menu-tree" class="lfa-menu-tree" aria-label="Admin menu items"></ul>
        <?php render_layer_buttons('menu', $layer, (bool) $data); ?>
    </form>
    <div id="lfa-icon-picker" class="lfa-icon-picker" hidden>
        <input type="search" class="regular-text" placeholder="Find an icon" aria-label="Find an icon" data-lfa-ignore>
        <div class="lfa-icon-grid" role="listbox" aria-label="Icons"></div>
        <p><button type="button" class="button-link" data-lfa-icon-reset>Use the icon from above</button></p>
    </div>
    <script type="application/json" id="lfa-menu-data"><?php echo wp_json_encode($payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE); ?></script>
    <?php
}
