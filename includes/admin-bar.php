<?php
namespace Leapfrog\Admin;

if (!defined('ABSPATH')) { exit; }

/**
 * The Admin bar feature: hide toolbar items, add your own links and a Bricks
 * menu, for everyone, a role or one person (layers.php), in the admin and on
 * the site.
 *
 * The editor lists the toolbar items Leapfrog has seen while administrators
 * browse (option leapfrog_admin_bar_seen), because many only appear on certain
 * screens or on the site.
 */

const OPTION_BAR = 'leapfrog_admin_bar';
const OPTION_BAR_SEEN = 'leapfrog_admin_bar_seen';

/** Never offered for hiding: the phone menu button needs it. */
const BAR_KEEP = ['menu-toggle'];

function admin_bar_boot(): void
{
    add_action('admin_bar_menu', __NAMESPACE__ . '\\admin_bar_add', 999);
    add_action('wp_before_admin_bar_render', __NAMESPACE__ . '\\admin_bar_record', 998);
    add_action('wp_before_admin_bar_render', __NAMESPACE__ . '\\admin_bar_hide', 999);
    add_action('admin_post_leapfrog_admin_bar', __NAMESPACE__ . '\\save_admin_bar');
    add_action('admin_enqueue_scripts', __NAMESPACE__ . '\\admin_bar_assets');
}

function merge_bar(array $stack): array
{
    $merged = ['hidden' => merge_hidden($stack, 'items'), 'links' => [], 'bricks' => false];
    foreach (layer_list($stack) as $layer) {
        foreach ((array) ($layer['links'] ?? []) as $link) {
            if (!empty($link['id'])) { $merged['links'][$link['id']] = $link; }
        }
        if (isset($layer['bricks']) && is_bool($layer['bricks'])) { $merged['bricks'] = $layer['bricks']; }
    }
    return $merged;
}

function my_bar(): array
{
    static $merged = null;
    if ($merged === null) {
        $user = wp_get_current_user();
        $merged = $user->exists() ? merge_bar(layers_for(layers_get(OPTION_BAR), $user)) : merge_bar(['everyone' => [], 'roles' => [], 'user' => null]);
    }
    return $merged;
}

/* -------------------------------------------------------------------------
 * Toolbar
 * ---------------------------------------------------------------------- */

function admin_bar_add(\WP_Admin_Bar $bar): void
{
    $m = my_bar();
    foreach ($m['links'] as $link) {
        $where = $link['where'] ?? 'both';
        if (($where === 'admin' && !is_admin()) || ($where === 'site' && is_admin())) { continue; }
        $bar->add_node([
            'id' => $link['id'],
            'title' => esc_html($link['title']),
            'href' => strpos($link['url'], '/') === 0 ? home_url($link['url']) : $link['url'],
            'meta' => !empty($link['new_tab']) ? ['target' => '_blank', 'rel' => 'noopener'] : [],
        ]);
    }
    if ($m['bricks']) {
        bricks_bar_menu($bar);
    }
}

/** Bricks menu: edit this page with Bricks, templates, fonts and settings. */
function bricks_bar_menu(\WP_Admin_Bar $bar): void
{
    if (get_template() !== 'bricks' || !class_exists('\\Bricks\\Helpers')) { return; }
    $children = [];
    $postId = 0;
    if (is_admin()) {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ($screen && $screen->base === 'post' && isset($_GET['post'])) { $postId = absint($_GET['post']); }
    } elseif (is_singular()) {
        $postId = (int) get_queried_object_id();
    }
    if ($postId && class_exists('\\Bricks\\Capabilities') && method_exists('\\Bricks\\Capabilities', 'current_user_can_use_builder') && \Bricks\Capabilities::current_user_can_use_builder($postId)) {
        $children['edit'] = ['Edit this page with Bricks', \Bricks\Helpers::get_builder_edit_link($postId)];
    }
    if (current_user_can('edit_posts')) {
        $children['templates'] = ['Templates', admin_url('edit.php?post_type=bricks_template')];
    }
    if (current_user_can('manage_options')) {
        $children['fonts'] = ['Custom fonts', admin_url('edit.php?post_type=bricks_fonts')];
        $children['settings'] = ['Settings', admin_url('admin.php?page=bricks-settings')];
    }
    if (!$children) { return; }
    $first = reset($children);
    $bar->add_node(['id' => 'lfa-bricks', 'title' => '<span class="ab-icon dashicons dashicons-layout" aria-hidden="true"></span><span class="ab-label">Bricks</span>', 'href' => $first[1]]);
    foreach ($children as $key => [$title, $href]) {
        $bar->add_node(['parent' => 'lfa-bricks', 'id' => 'lfa-bricks-' . $key, 'title' => esc_html($title), 'href' => $href]);
    }
}

function admin_bar_hide(): void
{
    global $wp_admin_bar;
    if (!($wp_admin_bar instanceof \WP_Admin_Bar)) { return; }
    foreach (my_bar()['hidden'] as $id => $hidden) {
        if ($hidden && !in_array($id, BAR_KEEP, true)) { $wp_admin_bar->remove_node((string) $id); }
    }
}

/** Names for toolbar items that have no text of their own. */
function bar_names(): array
{
    return [
        'wp-logo' => 'WordPress logo menu', 'site-name' => 'Site name', 'updates' => 'Updates', 'comments' => 'Comments',
        'new-content' => '+ New', 'my-account' => 'Howdy and your account', 'search' => 'Search', 'customize' => 'Customise',
        'edit' => 'Edit page', 'top-secondary' => 'Right side', 'user-actions' => 'Account links', 'command-palette' => 'Command search (Ctrl+K)',
        'user-info' => 'Your name and Edit Profile',
    ];
}

/** Remember which toolbar items exist, for the editor. Writes only when something new appears. */
function admin_bar_record(): void
{
    global $wp_admin_bar;
    if (!($wp_admin_bar instanceof \WP_Admin_Bar) || !current_user_can('manage_options')) { return; }
    $nodes = (array) $wp_admin_bar->get_nodes();
    $seen = get_option(OPTION_BAR_SEEN, []);
    $seen = is_array($seen) ? $seen : [];
    $context = is_admin() ? 'admin' : 'site';
    $changed = false;
    foreach ($nodes as $id => $node) {
        if (!empty($node->group) || strpos($id, 'lfa-') === 0 || in_array($id, BAR_KEEP, true)) { continue; }
        // Find the nearest parent that is an item rather than a group, and which side it is on.
        $parent = '';
        $depth = 0;
        $side = 'left';
        for ($p = $node->parent; $p && isset($nodes[$p]); $p = $nodes[$p]->parent) {
            if ($p === 'top-secondary') { $side = 'right'; }
            if (empty($nodes[$p]->group)) {
                if ($parent === '') { $parent = $p; }
                $depth++;
            }
        }
        if ($depth > 1) { continue; }
        if (!isset($seen[$id])) {
            $title = trim(preg_replace('/\s+/', ' ', wp_strip_all_tags((string) $node->title)));
            $title = bar_names()[$id] ?? ($title !== '' && !is_numeric($title) ? $title : ucfirst(str_replace(['-', '_'], ' ', $id)));
            $seen[$id] = ['title' => function_exists('mb_substr') ? mb_substr($title, 0, 60) : substr($title, 0, 60), 'parent' => $parent, 'side' => $side, 'where' => []];
            $changed = true;
        }
        if (!in_array($context, $seen[$id]['where'], true)) {
            $seen[$id]['where'][] = $context;
            $changed = true;
        }
    }
    if ($changed && count($seen) <= 400) {
        update_option(OPTION_BAR_SEEN, $seen, false);
    }
}

/* -------------------------------------------------------------------------
 * Saving
 * ---------------------------------------------------------------------- */

function clean_bar_id($id): ?string
{
    return is_string($id) && preg_match('/^[A-Za-z0-9_\-:.]{1,100}$/D', $id) ? $id : null;
}

function save_admin_bar(): void
{
    authorise('leapfrog_admin_bar');
    $layer = parse_layer(isset($_POST['layer']) && is_string($_POST['layer']) ? sanitize_text_field(wp_unslash($_POST['layer'])) : '');
    $args = ['tab' => 'admin-bar', 'layer' => layer_param($layer)];
    $isEveryone = $layer['type'] === 'everyone';
    if (!empty($_POST['lfa_reset'])) {
        layers_set(OPTION_BAR, $layer, []);
        return_to_page('bar_reset', $args);
    }
    $data = ['items' => [], 'links' => [], 'bricks' => null];
    foreach (isset($_POST['hidden']) && is_array($_POST['hidden']) ? wp_unslash($_POST['hidden']) : [] as $id => $value) {
        $id = clean_bar_id((string) $id);
        $hidden = posted_visibility($value, $isEveryone);
        if ($id !== null && $hidden !== null) { $data['items'][$id] = ['hidden' => $hidden]; }
    }
    foreach (array_slice(isset($_POST['links']) && is_array($_POST['links']) ? wp_unslash($_POST['links']) : [], 0, 30) as $link) {
        if (!is_array($link)) { continue; }
        $title = trim(sanitize_text_field((string) ($link['title'] ?? '')));
        $url = trim((string) ($link['url'] ?? ''));
        if ($title === '' && $url === '') { continue; }
        // "/something" is a page on this site; anything else must be a full web address.
        $url = strpos($url, '/') === 0 ? (preg_match('#^/[^\s<>"\'\\\\]*$#D', $url) ? $url : '') : esc_url_raw($url, ['http', 'https', 'mailto', 'tel']);
        if ($title === '' || $url === '') {
            return_to_page('bar_link', $args);
        }
        $id = clean_bar_id((string) ($link['id'] ?? ''));
        $data['links'][] = [
            'id' => $id && strpos($id, 'lfa-link-') === 0 ? $id : 'lfa-link-' . strtolower(wp_generate_password(8, false)),
            'title' => function_exists('mb_substr') ? mb_substr($title, 0, 60) : substr($title, 0, 60),
            'url' => $url,
            'new_tab' => !empty($link['new_tab']),
            'where' => in_array($link['where'] ?? '', ['admin', 'site'], true) ? $link['where'] : 'both',
        ];
    }
    $bricks = isset($_POST['bricks']) && is_string($_POST['bricks']) ? $_POST['bricks'] : '';
    if ($bricks === '1') { $data['bricks'] = true; } elseif ($bricks === '0' && !$isEveryone) { $data['bricks'] = false; }
    layers_set(OPTION_BAR, $layer, $data);
    return_to_page('bar_saved', $args);
}

/* -------------------------------------------------------------------------
 * Editor
 * ---------------------------------------------------------------------- */

function admin_bar_assets(string $hook): void
{
    if ($hook !== PAGE_HOOK || current_tab() !== 'admin-bar') { return; }
    wp_enqueue_script('lfa-admin-bar', asset_url('assets/admin-bar.js'), ['lfa-page'], asset_version('assets/admin-bar.js'), true);
}

function render_bar_link_row(string $index, array $link): void
{
    $name = 'links[' . $index . ']';
    ?>
    <tr class="lfa-link-row">
        <td><input type="hidden" name="<?php echo esc_attr($name); ?>[id]" value="<?php echo esc_attr($link['id'] ?? ''); ?>">
            <input type="text" name="<?php echo esc_attr($name); ?>[title]" value="<?php echo esc_attr($link['title'] ?? ''); ?>" maxlength="60" placeholder="Name" aria-label="Link name"></td>
        <td><input type="text" class="regular-text code" name="<?php echo esc_attr($name); ?>[url]" value="<?php echo esc_attr($link['url'] ?? ''); ?>" placeholder="https://… or /page/" aria-label="Web address"></td>
        <td><select name="<?php echo esc_attr($name); ?>[where]" aria-label="Where it shows">
            <?php foreach (['both' => 'Admin and site', 'admin' => 'Admin only', 'site' => 'Site only'] as $value => $label) : ?>
                <option value="<?php echo esc_attr($value); ?>" <?php selected($link['where'] ?? 'both', $value); ?>><?php echo esc_html($label); ?></option>
            <?php endforeach; ?>
        </select></td>
        <td><label><input type="checkbox" name="<?php echo esc_attr($name); ?>[new_tab]" value="1" <?php checked(!empty($link['new_tab'])); ?>> New tab</label></td>
        <td><button type="button" class="button-link lfa-remove" data-lfa-remove-link>Remove</button></td>
    </tr>
    <?php
}

function render_admin_bar_tab(): void
{
    $layer = current_layer();
    $all = layers_get(OPTION_BAR);
    $data = layer_data($all, $layer);
    $isEveryone = $layer['type'] === 'everyone';
    $target = $layer['type'] === 'user' ? get_userdata((int) $layer['id']) : null;
    $inherited = merge_bar(layers_for($all, $target ?: null, $layer));
    $seen = get_option(OPTION_BAR_SEEN, []);
    $seen = is_array($seen) ? $seen : [];
    $own = (array) ($data['items'] ?? []);
    $ownLinks = array_values((array) ($data['links'] ?? []));
    render_layer_picker('admin-bar', OPTION_BAR, $layer);
    ?>
    <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" class="lfa-track">
        <input type="hidden" name="action" value="leapfrog_admin_bar">
        <input type="hidden" name="layer" value="<?php echo esc_attr(layer_param($layer)); ?>">
        <?php wp_nonce_field('leapfrog_admin_bar', '_lfa_nonce', false); ?>

        <h2>Toolbar items</h2>
        <p class="description lfa-narrow-text">Items appear here once Leapfrog has seen them, so visit the site and a few admin screens to list items that only show there. Hiding an item hides what is inside it too. Hiding “Howdy and your account” also hides the Log Out link.</p>
        <?php foreach (['left' => 'Left side', 'right' => 'Right side'] as $side => $sideLabel) :
            $tops = array_filter($seen, static function ($node) use ($side) { return $node['parent'] === '' && $node['side'] === $side; });
            if (!$tops) { continue; }
            ?>
            <table class="widefat striped lfa-bar-table">
                <thead><tr><th scope="col"><?php echo esc_html($sideLabel); ?></th><th scope="col">Seen in</th><th scope="col">Show</th></tr></thead>
                <tbody>
                <?php foreach ($tops as $id => $node) :
                    foreach (array_merge([$id => $node], array_filter($seen, static function ($child) use ($id) { return $child['parent'] === (string) $id; })) as $rowId => $row) :
                        $isChild = $rowId !== $id;
                        ?>
                        <tr<?php echo $isChild ? ' class="lfa-bar-child"' : ''; ?>>
                            <td><?php echo esc_html($row['title']); ?> <code><?php echo esc_html((string) $rowId); ?></code></td>
                            <td><?php echo esc_html(implode(', ', array_map(static function ($where) { return $where === 'admin' ? 'Admin' : 'Site'; }, $row['where']))); ?></td>
                            <td><?php render_visibility_select('hidden[' . $rowId . ']', isset($own[$rowId]['hidden']) ? (bool) $own[$rowId]['hidden'] : null, !empty($inherited['hidden'][$rowId]), $isEveryone, 'Show or hide ' . $row['title']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endforeach; ?>

        <h2>Your links</h2>
        <?php if ($inherited['links']) : ?>
            <table class="widefat striped lfa-bar-table">
                <thead><tr><th scope="col">Links from the layer above</th><th scope="col">Address</th><th scope="col">Show</th></tr></thead>
                <tbody>
                <?php foreach ($inherited['links'] as $link) : ?>
                    <tr>
                        <td><?php echo esc_html($link['title']); ?></td>
                        <td><code><?php echo esc_html($link['url']); ?></code></td>
                        <td><?php render_visibility_select('hidden[' . $link['id'] . ']', isset($own[$link['id']]['hidden']) ? (bool) $own[$link['id']]['hidden'] : null, !empty($inherited['hidden'][$link['id']]), $isEveryone, 'Show or hide ' . $link['title']); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        <p class="description">Links added here go on the left of the toolbar. Start an address with / for a page on this site.</p>
        <table class="widefat lfa-links-table">
            <tbody id="lfa-links">
                <?php foreach ($ownLinks as $index => $link) { render_bar_link_row((string) $index, (array) $link); } ?>
            </tbody>
        </table>
        <template id="lfa-link-template"><?php render_bar_link_row('__i__', []); ?></template>
        <p><button type="button" class="button" id="lfa-add-link">Add Link</button></p>

        <?php if (get_template() === 'bricks') :
            $ownBricks = isset($data['bricks']) && is_bool($data['bricks']) ? $data['bricks'] : null;
            ?>
            <h2>Bricks menu</h2>
            <p class="description lfa-narrow-text">A Bricks menu in the toolbar, in the admin and on the site: edit this page with Bricks, Templates, Custom fonts and Settings (each only for people allowed to use it). Bricks’ own “Edit with Bricks” menu on the site is listed with the toolbar items above.</p>
            <?php if ($isEveryone) : ?>
                <p><label><input type="checkbox" name="bricks" value="1" <?php checked($ownBricks === true); ?>> Show the Bricks menu</label></p>
            <?php else : ?>
                <p><select name="bricks" aria-label="Bricks menu">
                    <option value="" <?php selected($ownBricks === null); ?>>As above (<?php echo $inherited['bricks'] ? 'shown' : 'not shown'; ?>)</option>
                    <option value="1" <?php selected($ownBricks === true); ?>>Show</option>
                    <option value="0" <?php selected($ownBricks === false); ?>>Don’t show</option>
                </select></p>
            <?php endif; ?>
        <?php endif; ?>

        <?php render_layer_buttons('bar', $layer, (bool) $data); ?>
    </form>
    <?php
}
