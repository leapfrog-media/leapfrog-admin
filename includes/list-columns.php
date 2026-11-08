<?php
namespace Leapfrog\Admin;

if (!defined('ABSPATH')) { exit; }

/**
 * The List columns feature: for each post type's list (Posts, Pages, …), show,
 * hide and reorder columns, add ID and Last modified columns, and add filters
 * for its categories and tags. Settings are per post type, for everyone.
 *
 * The editor lists columns Leapfrog has seen on each list screen (option
 * leapfrog_admin_columns_seen), because plugins add theirs only there.
 * People can still hide columns for themselves under Screen Options.
 */

const OPTION_COLUMNS = 'leapfrog_admin_columns';
const OPTION_COLUMNS_SEEN = 'leapfrog_admin_columns_seen';
const EXTRA_COLUMNS = ['lfa_id' => 'ID', 'lfa_modified' => 'Last modified'];
/** The tick box and the title (which holds Edit, Trash and View) always stay. */
const FIXED_COLUMNS = ['cb', 'title'];

function list_columns_boot(): void
{
    add_action('current_screen', __NAMESPACE__ . '\\list_columns_screen');
    add_action('restrict_manage_posts', __NAMESPACE__ . '\\list_filters', 20, 2);
    add_action('pre_get_posts', __NAMESPACE__ . '\\list_filter_query');
    add_action('admin_post_leapfrog_admin_columns', __NAMESPACE__ . '\\save_list_columns');
    add_action('admin_enqueue_scripts', __NAMESPACE__ . '\\list_columns_assets');
}

function column_settings(string $postType): array
{
    $all = get_option(OPTION_COLUMNS, []);
    $s = is_array($all) && is_array($all[$postType] ?? null) ? $all[$postType] : [];
    return [
        'order' => array_values((array) ($s['order'] ?? [])),
        'hidden' => array_values((array) ($s['hidden'] ?? [])),
        'extras' => array_values((array) ($s['extras'] ?? [])),
        'filters' => array_values((array) ($s['filters'] ?? [])),
    ];
}

/** Post types with a list screen in the admin menu. */
function list_post_types(): array
{
    $types = [];
    foreach (get_post_types(['show_ui' => true], 'objects') as $type) {
        if ($type->name !== 'attachment' && $type->show_in_menu && strpos($type->name, 'wp_') !== 0) {
            $types[$type->name] = $type;
        }
    }
    return $types;
}

/* -------------------------------------------------------------------------
 * List screens
 * ---------------------------------------------------------------------- */

function list_columns_screen(\WP_Screen $screen): void
{
    if ($screen->base !== 'edit' || !$screen->post_type) { return; }
    $type = $screen->post_type;
    add_filter("manage_{$type}_posts_columns", __NAMESPACE__ . '\\list_columns_filter', PHP_INT_MAX);
    add_filter("manage_{$screen->id}_sortable_columns", __NAMESPACE__ . '\\list_sortable_columns');
    add_action("manage_{$type}_posts_custom_column", __NAMESPACE__ . '\\list_column_content', 10, 2);
    add_action('admin_head', static function () {
        echo '<style>.fixed .column-lfa_id{width:5em}.fixed .column-lfa_modified{width:11em}</style>';
    });
}

function list_columns_filter($columns)
{
    if (!is_array($columns)) { return $columns; }
    $screen = get_current_screen();
    $type = $screen ? (string) $screen->post_type : '';
    if ($type === '') { return $columns; }
    if (current_user_can('manage_options')) {
        record_columns($type, $columns);
    }
    $s = column_settings($type);
    foreach (EXTRA_COLUMNS as $key => $label) {
        if (in_array($key, $s['extras'], true)) { $columns[$key] = $label; }
    }
    foreach ($s['hidden'] as $key) {
        if (!in_array($key, FIXED_COLUMNS, true)) { unset($columns[$key]); }
    }
    if ($s['order']) {
        $pairs = sort_by_order(array_map(null, array_keys($columns), array_values($columns)), $s['order'], static function ($pair) { return $pair[0]; });
        $sorted = isset($columns['cb']) ? ['cb' => $columns['cb']] : [];
        foreach ($pairs as [$key, $label]) { $sorted[$key] = $label; }
        $columns = $sorted;
    }
    return $columns;
}

function list_sortable_columns($columns)
{
    $columns = is_array($columns) ? $columns : [];
    $columns['lfa_id'] = 'ID';
    $columns['lfa_modified'] = ['modified', true];
    return $columns;
}

function list_column_content($column, $postId): void
{
    if ($column === 'lfa_id') {
        echo (int) $postId;
    } elseif ($column === 'lfa_modified') {
        $post = get_post($postId);
        if (!$post) { return; }
        echo esc_html(mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $post->post_modified));
        $editor = get_userdata((int) get_post_meta($postId, '_edit_last', true));
        if ($editor) { echo '<br><span class="description">by ' . esc_html($editor->display_name) . '</span>'; }
    }
}

/** Remember each list's columns for the editor. Writes only when something new appears. */
function record_columns(string $type, array $columns): void
{
    $seen = get_option(OPTION_COLUMNS_SEEN, []);
    $seen = is_array($seen) ? $seen : [];
    $known = (array) ($seen[$type] ?? []);
    $names = ['cb' => 'Tick box', 'comments' => 'Comments'];
    $changed = false;
    foreach ($columns as $key => $label) {
        $key = (string) $key;
        if (isset($known[$key]) || isset(EXTRA_COLUMNS[$key])) { continue; }
        $text = trim(wp_strip_all_tags((string) $label));
        $known[$key] = $names[$key] ?? ($text !== '' ? $text : ucfirst(str_replace(['_', '-'], ' ', $key)));
        $changed = true;
    }
    if ($changed) {
        $seen[$type] = array_slice($known, 0, 60, true);
        update_option(OPTION_COLUMNS_SEEN, $seen, false);
    }
}

/* -------------------------------------------------------------------------
 * Category and tag filters
 * ---------------------------------------------------------------------- */

/** Taxonomies a list can filter by. Posts already have a category filter. */
function filter_taxonomies(string $type): array
{
    $taxonomies = [];
    foreach (get_object_taxonomies($type, 'objects') as $taxonomy) {
        if ($taxonomy->show_ui && $taxonomy->name !== 'post_format' && !($type === 'post' && $taxonomy->name === 'category')) {
            $taxonomies[$taxonomy->name] = $taxonomy;
        }
    }
    return $taxonomies;
}

function list_filters($postType, $which = 'top'): void
{
    if ($which !== 'top' || !is_string($postType)) { return; }
    $taxonomies = filter_taxonomies($postType);
    foreach (column_settings($postType)['filters'] as $name) {
        if (!isset($taxonomies[$name])) { continue; }
        // No categories or tags yet: nothing to filter by.
        $any = get_terms(['taxonomy' => $name, 'hide_empty' => false, 'number' => 1, 'fields' => 'ids']);
        if (!$any || is_wp_error($any)) { continue; }
        $taxonomy = $taxonomies[$name];
        $field = 'lfa_tax_' . $name;
        echo '<label class="screen-reader-text" for="' . esc_attr($field) . '">' . esc_html('Filter by ' . $taxonomy->labels->singular_name) . '</label>';
        wp_dropdown_categories([
            'taxonomy' => $name,
            'name' => $field,
            'id' => $field,
            'show_option_all' => $taxonomy->labels->all_items,
            'hide_empty' => false,
            'hierarchical' => $taxonomy->hierarchical,
            'value_field' => 'slug',
            'selected' => isset($_GET[$field]) && is_string($_GET[$field]) ? sanitize_title(wp_unslash($_GET[$field])) : '',
            'show_count' => true,
            'orderby' => 'name',
        ]);
    }
}

function list_filter_query(\WP_Query $query): void
{
    global $pagenow;
    if (!is_admin() || $pagenow !== 'edit.php' || !$query->is_main_query()) { return; }
    $type = $query->get('post_type') ?: 'post';
    if (!is_string($type)) { return; }
    $taxQuery = (array) $query->get('tax_query');
    $added = false;
    foreach (column_settings($type)['filters'] as $name) {
        $value = isset($_GET['lfa_tax_' . $name]) && is_string($_GET['lfa_tax_' . $name]) ? sanitize_title(wp_unslash($_GET['lfa_tax_' . $name])) : '';
        if ($value !== '' && $value !== '0' && taxonomy_exists($name)) {
            $taxQuery[] = ['taxonomy' => $name, 'field' => 'slug', 'terms' => $value];
            $added = true;
        }
    }
    if ($added) { $query->set('tax_query', $taxQuery); }
}

/* -------------------------------------------------------------------------
 * Editor
 * ---------------------------------------------------------------------- */

function save_list_columns(): void
{
    authorise('leapfrog_admin_columns');
    $types = list_post_types();
    $type = isset($_POST['post_type']) && is_string($_POST['post_type']) ? sanitize_key($_POST['post_type']) : '';
    if (!isset($types[$type])) { return_to_page('columns_type', ['tab' => 'list-columns']); }
    $args = ['tab' => 'list-columns', 'type' => $type];
    $all = get_option(OPTION_COLUMNS, []);
    $all = is_array($all) ? $all : [];
    if (!empty($_POST['lfa_reset'])) {
        unset($all[$type]);
        update_option(OPTION_COLUMNS, $all);
        return_to_page('columns_reset', $args);
    }
    $clean = static function ($list) {
        $keys = [];
        foreach (is_array($list) ? wp_unslash($list) : [] as $key) {
            if (is_string($key) && preg_match('/^[A-Za-z0-9_\-]{1,100}$/D', $key)) { $keys[$key] = $key; }
        }
        return array_values($keys);
    };
    $order = $clean($_POST['order'] ?? []);
    $shown = $clean($_POST['show'] ?? []);
    $filters = array_values(array_intersect($clean($_POST['filters'] ?? []), array_keys(filter_taxonomies($type))));
    $all[$type] = [
        'order' => $order,
        'hidden' => array_values(array_diff($order, $shown, FIXED_COLUMNS, array_keys(EXTRA_COLUMNS))),
        'extras' => array_values(array_intersect(array_keys(EXTRA_COLUMNS), $shown)),
        'filters' => $filters,
    ];
    update_option(OPTION_COLUMNS, $all);
    return_to_page('columns_saved', $args);
}

function list_columns_assets(string $hook): void
{
    if ($hook !== PAGE_HOOK || current_tab() !== 'list-columns') { return; }
    wp_enqueue_script('lfa-list-columns', asset_url('assets/list-columns.js'), ['jquery', 'jquery-ui-sortable', 'lfa-page'], asset_version('assets/list-columns.js'), true);
}

function render_list_columns_tab(): void
{
    $types = list_post_types();
    if (!$types) { echo '<p>No lists to change.</p>'; return; }
    $type = isset($_GET['type']) && is_string($_GET['type']) ? sanitize_key($_GET['type']) : '';
    $type = isset($types[$type]) ? $type : (string) array_key_first($types);
    $s = column_settings($type);
    $seen = get_option(OPTION_COLUMNS_SEEN, []);
    $columns = (array) (is_array($seen) ? ($seen[$type] ?? []) : []);
    $columns += EXTRA_COLUMNS;
    $rows = sort_by_order(array_map(null, array_keys($columns), array_values($columns)), $s['order'], static function ($pair) { return $pair[0]; });
    $listUrl = admin_url('edit.php' . ($type === 'post' ? '' : '?post_type=' . $type));
    ?>
    <ul class="subsubsub lfa-type-links">
        <?php $links = []; foreach ($types as $name => $object) {
            $links[] = '<li><a href="' . esc_url(page_url(['tab' => 'list-columns', 'type' => $name])) . '"' . ($name === $type ? ' class="current" aria-current="page"' : '') . '>' . esc_html($object->labels->name) . '</a>';
        } echo implode(' | </li>', $links) . '</li>'; // phpcs:ignore -- escaped above ?>
    </ul>
    <div class="clear"></div>
    <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" class="lfa-track">
        <input type="hidden" name="action" value="leapfrog_admin_columns">
        <input type="hidden" name="post_type" value="<?php echo esc_attr($type); ?>">
        <?php wp_nonce_field('leapfrog_admin_columns', '_lfa_nonce', false); ?>
        <h2>Columns in the <?php echo esc_html($types[$type]->labels->name); ?> list</h2>
        <p class="description lfa-narrow-text">Drag to reorder; untick to remove a column for everyone. Columns appear here once Leapfrog has seen the <a href="<?php echo esc_url($listUrl); ?>"><?php echo esc_html($types[$type]->labels->name); ?> list</a>. People can still hide columns for themselves under Screen Options. The tick box and Title always stay.</p>
        <table class="widefat striped lfa-bar-table lfa-columns-table">
            <thead><tr><th scope="col" class="lfa-col-handle"><span class="screen-reader-text">Move</span></th><th scope="col">Column</th><th scope="col">Show</th></tr></thead>
            <tbody id="lfa-columns">
            <?php foreach ($rows as [$key, $label]) :
                $key = (string) $key;
                $fixed = in_array($key, FIXED_COLUMNS, true);
                $shown = isset(EXTRA_COLUMNS[$key]) ? in_array($key, $s['extras'], true) : !in_array($key, $s['hidden'], true);
                ?>
                <tr>
                    <td class="lfa-col-handle"><?php if ($key !== 'cb') : ?><span class="lfa-drag dashicons dashicons-menu" title="Drag to move" aria-hidden="true"></span><?php endif; ?><input type="hidden" name="order[]" value="<?php echo esc_attr($key); ?>"></td>
                    <td><label for="lfa-col-<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label> <code><?php echo esc_html($key); ?></code><?php if (isset(EXTRA_COLUMNS[$key])) { echo ' <span class="lfa-badge">Leapfrog</span>'; } ?></td>
                    <td><input type="checkbox" id="lfa-col-<?php echo esc_attr($key); ?>" name="show[]" value="<?php echo esc_attr($key); ?>" <?php checked($shown || $fixed); ?> <?php disabled($fixed); ?>></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <?php $taxonomies = filter_taxonomies($type); if ($taxonomies) : ?>
        <h2>Filters</h2>
        <p class="description">Drop-down filters above the list, to show only items in one category or tag.</p>
        <fieldset>
            <?php foreach ($taxonomies as $name => $taxonomy) : ?>
                <label class="lfa-filter-choice"><input type="checkbox" name="filters[]" value="<?php echo esc_attr($name); ?>" <?php checked(in_array($name, $s['filters'], true)); ?>> <?php echo esc_html($taxonomy->labels->name); ?> <code><?php echo esc_html($name); ?></code></label>
            <?php endforeach; ?>
        </fieldset>
        <?php endif; ?>

        <?php render_layer_buttons_simple(isset(get_option(OPTION_COLUMNS, [])[$type]), 'Reset to WordPress’s columns for ' . $types[$type]->labels->name . '?'); ?>
    </form>
    <?php
}

/** Save, and Reset when there is something to reset. */
function render_layer_buttons_simple(bool $hasData, string $confirm): void
{
    ?>
    <div class="lfa-save-bar">
        <?php submit_button('Save Changes', 'primary', 'submit', false); ?>
        <?php if ($hasData) : ?><button type="submit" class="button" name="lfa_reset" value="1" data-lfa-confirm="<?php echo esc_attr($confirm); ?>">Reset</button><?php endif; ?>
        <span class="lfa-unsaved" hidden>Unsaved changes</span>
    </div>
    <?php
}
