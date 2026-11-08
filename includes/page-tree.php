<?php
namespace Leapfrog\Admin;

if (!defined('ABSPATH')) { exit; }

/**
 * The Page tree feature: a "Tree view" of pages (and other hierarchical post
 * types) where you drag to reorder and to move a page under another. It sits
 * under Pages in the menu and as a "Tree view" link above the Pages list.
 *
 * Saving changes only the parent and order of pages that moved, written
 * directly (no "last modified" change, no save hooks), then clears their cache.
 */

function page_tree_boot(): void
{
    add_action('admin_menu', __NAMESPACE__ . '\\page_tree_menu');
    add_action('admin_post_leapfrog_admin_page_tree', __NAMESPACE__ . '\\save_page_tree');
    add_action('admin_enqueue_scripts', __NAMESPACE__ . '\\page_tree_assets');
}

function tree_post_types(): array
{
    $types = [];
    foreach (get_post_types(['show_ui' => true, 'hierarchical' => true], 'objects') as $type) {
        if ($type->name !== 'attachment' && $type->show_in_menu && strpos($type->name, 'wp_') !== 0) {
            $types[$type->name] = $type;
        }
    }
    return $types;
}

function tree_slug(string $type): string
{
    return 'lfa-tree-' . $type;
}

function tree_parent_file(\WP_Post_Type $type): string
{
    if (is_string($type->show_in_menu)) { return $type->show_in_menu; }
    return $type->name === 'post' ? 'edit.php' : 'edit.php?post_type=' . $type->name;
}

function tree_url(string $type, array $args = []): string
{
    $object = get_post_type_object($type);
    $base = $object ? tree_parent_file($object) : 'admin.php';
    // Same address as the menu link, so the menu highlights Tree view.
    return add_query_arg($args + ['page' => tree_slug($type)], admin_url(strpos($base, '.php') !== false ? $base : 'admin.php'));
}

function page_tree_menu(): void
{
    foreach (tree_post_types() as $type) {
        add_submenu_page(tree_parent_file($type), $type->labels->name . ' tree', 'Tree view', $type->cap->edit_posts, tree_slug($type->name), static function () use ($type) { render_page_tree($type->name); });
        add_filter('views_edit-' . $type->name, static function ($views) use ($type) {
            $views = is_array($views) ? $views : [];
            $views['lfa-tree'] = '<a href="' . esc_url(tree_url($type->name)) . '">Tree view</a>';
            return $views;
        });
    }
}

function on_tree_page(): string
{
    $page = isset($_GET['page']) && is_string($_GET['page']) ? sanitize_key($_GET['page']) : '';
    foreach (array_keys(tree_post_types()) as $type) {
        if ($page === tree_slug($type)) { return $type; }
    }
    return '';
}

function page_tree_assets(): void
{
    if (on_tree_page() === '') { return; }
    wp_enqueue_style('lfa-page', asset_url('assets/page.css'), [], asset_version('assets/page.css'));
    wp_enqueue_script('lfa-page', asset_url('assets/page.js'), [], asset_version('assets/page.js'), true);
    wp_enqueue_script('lfa-page-tree', asset_url('assets/page-tree.js'), ['jquery', 'jquery-ui-sortable', 'lfa-page'], asset_version('assets/page-tree.js'), true);
}

/** Every item of the type, for the tree: id, parent, order, title, status. */
function tree_items(string $type): array
{
    return get_posts([
        'post_type' => $type,
        'post_status' => ['publish', 'future', 'draft', 'pending', 'private'],
        'numberposts' => -1,
        'orderby' => ['menu_order' => 'ASC', 'title' => 'ASC'],
        'no_found_rows' => true,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
    ]);
}

function render_tree_branch(array $children, int $parent, \WP_Post_Type $type): void
{
    echo '<ul class="lfa-tree">';
    foreach ($children[$parent] ?? [] as $post) {
        $status = get_post_status_object($post->post_status);
        $title = $post->post_title !== '' ? $post->post_title : '(no title)';
        $hasChildren = !empty($children[$post->ID]);
        echo '<li class="lfa-tree-item" data-id="' . (int) $post->ID . '">';
        echo '<div class="lfa-tree-row">';
        echo '<span class="lfa-drag dashicons dashicons-menu" title="Drag to move" aria-hidden="true"></span>';
        echo '<button type="button" class="lfa-tree-toggle" aria-expanded="true" aria-label="' . esc_attr('Show or hide pages under ' . $title) . '"' . ($hasChildren ? '' : ' hidden') . '><span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span></button>';
        $edit = get_edit_post_link($post->ID);
        echo $edit ? '<a class="lfa-tree-title" href="' . esc_url($edit) . '">' . esc_html($title) . '</a>' : '<span class="lfa-tree-title">' . esc_html($title) . '</span>';
        if ($post->post_status !== 'publish' && $status) { echo ' <span class="lfa-badge">' . esc_html($status->label) . '</span>'; }
        if ((int) get_option('page_on_front') === $post->ID) { echo ' <span class="lfa-badge">Front page</span>'; }
        $view = get_permalink($post);
        if ($view && $post->post_status !== 'trash') { echo ' <a class="lfa-tree-view" href="' . esc_url($view) . '">View</a>'; }
        echo '</div>';
        render_tree_branch($children, (int) $post->ID, $type);
        echo '</li>';
    }
    echo '</ul>';
}

function render_page_tree(string $typeName): void
{
    $type = get_post_type_object($typeName);
    if (!$type || !current_user_can($type->cap->edit_posts)) { return; }
    $items = tree_items($typeName);
    $ids = array_flip(array_map(static function ($post) { return (int) $post->ID; }, $items));
    $children = [];
    foreach ($items as $post) {
        // A page whose parent is in the bin or gone shows at the top level.
        $parent = isset($ids[(int) $post->post_parent]) ? (int) $post->post_parent : 0;
        $children[$parent][] = $post;
    }
    $notice = isset($_GET['lfa_tree']) && is_string($_GET['lfa_tree']) ? sanitize_key($_GET['lfa_tree']) : '';
    $moved = isset($_GET['moved']) ? absint($_GET['moved']) : 0;
    $skipped = isset($_GET['skipped']) ? absint($_GET['skipped']) : 0;
    ?>
    <div class="wrap lfa-settings lfa-tree-wrap">
        <h1 class="wp-heading-inline"><?php echo esc_html($type->labels->name); ?>: tree view</h1>
        <a class="page-title-action" href="<?php echo esc_url(admin_url('edit.php?post_type=' . $typeName)); ?>">Back to the list</a>
        <hr class="wp-header-end">
        <?php if ($notice === 'saved') : ?>
            <div class="notice notice-success is-dismissible lfa-notice-keep"><p><?php echo esc_html($moved === 1 ? 'Order saved: 1 item changed.' : 'Order saved: ' . $moved . ' items changed.'); ?><?php echo $skipped ? esc_html(' ' . $skipped . ' you aren’t allowed to edit stayed where they were.') : ''; ?></p></div>
        <?php elseif ($notice === 'invalid') : ?>
            <div class="notice notice-error is-dismissible lfa-notice-keep"><p>The new order could not be read, or the pages changed while you were editing. Nothing was saved; please try again.</p></div>
        <?php endif; ?>
        <p class="lfa-narrow-text">Drag to reorder, or drag onto the space under another item to put it there. Moving an item under another changes its web address (for example /about/team/). Nothing changes until you click Save Order.</p>
        <?php if (!$items) : ?>
            <p>Nothing here yet.</p>
        <?php else : ?>
        <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" id="lfa-tree-form" class="lfa-track">
            <input type="hidden" name="action" value="leapfrog_admin_page_tree">
            <input type="hidden" name="post_type" value="<?php echo esc_attr($typeName); ?>">
            <input type="hidden" name="tree_json" id="lfa-tree-json" value="">
            <?php wp_nonce_field('leapfrog_admin_page_tree', '_lfa_nonce', false); ?>
            <p class="lfa-tree-actions"><button type="button" class="button-link" data-lfa-tree-all="1">Expand all</button> · <button type="button" class="button-link" data-lfa-tree-all="0">Collapse all</button></p>
            <div class="lfa-tree-root"><?php render_tree_branch($children, 0, $type); ?></div>
            <div class="lfa-save-bar"><?php submit_button('Save Order', 'primary', 'submit', false); ?> <span class="lfa-unsaved" hidden>Unsaved changes</span></div>
        </form>
        <?php endif; ?>
    </div>
    <?php
}

function save_page_tree(): void
{
    global $wpdb;
    check_admin_referer('leapfrog_admin_page_tree', '_lfa_nonce');
    $typeName = isset($_POST['post_type']) && is_string($_POST['post_type']) ? sanitize_key($_POST['post_type']) : '';
    $types = tree_post_types();
    if (!isset($types[$typeName]) || !current_user_can($types[$typeName]->cap->edit_posts)) {
        wp_die('You can’t change the order of these items.', '', ['response' => 403]);
    }
    $back = static function (array $args) use ($typeName) { wp_safe_redirect(tree_url($typeName, $args)); exit; };
    $rows = isset($_POST['tree_json']) && is_string($_POST['tree_json']) ? json_decode(wp_unslash($_POST['tree_json']), true) : null;
    if (!is_array($rows)) { $back(['lfa_tree' => 'invalid']); }

    $current = [];
    foreach (tree_items($typeName) as $post) {
        $current[(int) $post->ID] = ['parent' => (int) $post->post_parent, 'order' => (int) $post->menu_order];
    }
    $next = [];
    foreach ($rows as $row) {
        $id = (int) ($row['id'] ?? 0);
        $parent = (int) ($row['parent'] ?? -1);
        if (!isset($current[$id]) || ($parent !== 0 && !isset($current[$parent])) || $parent === $id) { $back(['lfa_tree' => 'invalid']); }
        $next[$id] = ['parent' => $parent, 'order' => max(0, (int) ($row['order'] ?? 0))];
    }
    // The tree must not loop (a page under its own child).
    foreach (array_keys($next) as $id) {
        for ($p = $next[$id]['parent'], $steps = 0; $p !== 0; $p = $next[$p]['parent'] ?? $current[$p]['parent'] ?? 0) {
            if ($p === $id || ++$steps > 1000) { $back(['lfa_tree' => 'invalid']); }
        }
    }
    $moved = 0;
    $skipped = 0;
    foreach ($next as $id => $place) {
        $was = $current[$id];
        // Pages whose parent is gone show at the top; keep that parent unless the page moved.
        if ($place['parent'] === 0 && $was['parent'] !== 0 && !isset($current[$was['parent']])) { $place['parent'] = $was['parent']; }
        if ($place === $was) { continue; }
        if (!current_user_can('edit_post', $id)) { $skipped++; continue; }
        $wpdb->update($wpdb->posts, ['post_parent' => $place['parent'], 'menu_order' => $place['order']], ['ID' => $id], ['%d', '%d'], ['%d']);
        clean_post_cache($id);
        $moved++;
    }
    if ($moved) { wp_cache_set_posts_last_changed(); }
    $back(['lfa_tree' => 'saved', 'moved' => $moved, 'skipped' => $skipped]);
}

function render_page_tree_tab(): void
{
    ?>
    <p class="lfa-narrow-text">A tree view for pages (and other content that can sit under a parent). Open it from <strong>Tree view</strong> in the menu under each one, or the Tree view link above its list.</p>
    <ul class="lfa-coming">
        <?php foreach (tree_post_types() as $type) : ?>
            <li><a href="<?php echo esc_url(tree_url($type->name)); ?>"><?php echo esc_html($type->labels->name); ?>: tree view</a></li>
        <?php endforeach; ?>
    </ul>
    <?php
}
