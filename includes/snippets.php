<?php
namespace Leapfrog\Admin;

if (!defined('ABSPATH')) { exit; }

/**
 * The Code snippets feature: small, site-specific PHP, CSS, JavaScript and HTML
 * snippets, managed with WordPress's own list and edit screens and code editor.
 * Snippets every site needs belong in a Leapfrog plugin instead.
 *
 * Each snippet is a post of the private type lfa_snippet; its code and settings
 * are post meta. The active snippets are copied into one option so running them
 * needs no database query. Only administrators (manage_options) see or edit them.
 *
 * Safety: PHP is syntax-checked when saved (a snippet with a mistake is saved
 * but switched off); a snippet that throws an error or crashes the page while
 * running is switched off and the error shown on its screen. Safe mode (the
 * LEAPFROG_SNIPPETS_SAFE_MODE constant, or ?lfa-safe-mode=1 for administrators)
 * runs no snippets at all.
 */

const SNIPPET_TYPE = 'lfa_snippet';
const OPTION_SNIPPETS = 'leapfrog_admin_snippets';
const SNIPPET_LANGUAGES = ['php' => 'PHP', 'css' => 'CSS', 'js' => 'JavaScript', 'html' => 'HTML'];
const SNIPPETS_EXPORT_ID = 'leapfrog-admin';

function snippets_boot(): void
{
    add_action('init', __NAMESPACE__ . '\\register_snippet_type');
    add_action('plugins_loaded', __NAMESPACE__ . '\\run_snippets', 20);
    add_action('add_meta_boxes_' . SNIPPET_TYPE, __NAMESPACE__ . '\\snippet_meta_boxes');
    add_action('save_post_' . SNIPPET_TYPE, __NAMESPACE__ . '\\save_snippet', 10, 2);
    foreach (['trashed_post', 'untrashed_post', 'deleted_post'] as $hook) {
        add_action($hook, __NAMESPACE__ . '\\snippet_changed');
    }
    add_filter('manage_' . SNIPPET_TYPE . '_posts_columns', __NAMESPACE__ . '\\snippet_columns');
    add_action('manage_' . SNIPPET_TYPE . '_posts_custom_column', __NAMESPACE__ . '\\snippet_column', 10, 2);
    add_filter('post_row_actions', __NAMESPACE__ . '\\snippet_row_actions', 10, 2);
    // Every snippet is "published"; only All and Bin mean anything here.
    add_filter('views_edit-' . SNIPPET_TYPE, static function ($views) { unset($views['publish'], $views['mine']); return $views; });
    add_action('admin_post_leapfrog_admin_snippet_toggle', __NAMESPACE__ . '\\toggle_snippet');
    add_filter('bulk_actions-edit-' . SNIPPET_TYPE, __NAMESPACE__ . '\\snippet_bulk_actions');
    add_filter('handle_bulk_actions-edit-' . SNIPPET_TYPE, __NAMESPACE__ . '\\snippet_bulk_handler', 10, 3);
    add_action('admin_menu', __NAMESPACE__ . '\\snippet_import_menu');
    add_action('admin_post_leapfrog_admin_snippet_import', __NAMESPACE__ . '\\import_snippets');
    add_action('current_screen', __NAMESPACE__ . '\\snippet_screen');
    add_filter('post_updated_messages', __NAMESPACE__ . '\\snippet_messages');
    add_action('admin_notices', __NAMESPACE__ . '\\snippet_notices');
}

/* -------------------------------------------------------------------------
 * Storage
 * ---------------------------------------------------------------------- */

function register_snippet_type(): void
{
    $admin = 'manage_options';
    register_post_type(SNIPPET_TYPE, [
        'labels' => [
            'name' => 'Code snippets', 'singular_name' => 'Code snippet', 'menu_name' => 'Snippets',
            'all_items' => 'All snippets', 'add_new' => 'Add Snippet', 'add_new_item' => 'Add snippet',
            'edit_item' => 'Edit snippet', 'new_item' => 'New snippet', 'search_items' => 'Search snippets',
            'not_found' => 'No snippets yet.', 'not_found_in_trash' => 'No snippets in the bin.',
        ],
        'public' => false,
        'show_ui' => true,
        'show_in_menu' => true,
        'show_in_admin_bar' => false,
        'show_in_rest' => false,
        'menu_position' => 81,
        'menu_icon' => 'dashicons-editor-code',
        'supports' => ['title'],
        'rewrite' => false,
        'query_var' => false,
        'map_meta_cap' => false,
        // Snippets can run PHP, so only administrators may see or change them.
        'capabilities' => [
            'edit_post' => $admin, 'read_post' => $admin, 'delete_post' => $admin, 'edit_posts' => $admin,
            'edit_others_posts' => $admin, 'publish_posts' => $admin, 'read_private_posts' => $admin,
            'delete_posts' => $admin, 'delete_others_posts' => $admin, 'delete_published_posts' => $admin,
            'delete_private_posts' => $admin, 'edit_published_posts' => $admin, 'edit_private_posts' => $admin,
            'create_posts' => $admin,
        ],
    ]);
}

/** One snippet's settings, with defaults. */
function snippet_settings(int $id): array
{
    $type = (string) get_post_meta($id, '_lfa_type', true);
    $type = isset(SNIPPET_LANGUAGES[$type]) ? $type : 'php';
    $where = (string) get_post_meta($id, '_lfa_where', true);
    $position = (string) get_post_meta($id, '_lfa_position', true);
    $priority = get_post_meta($id, '_lfa_priority', true);
    return [
        'code' => (string) get_post_meta($id, '_lfa_code', true),
        'type' => $type,
        'active' => (bool) get_post_meta($id, '_lfa_active', true),
        'where' => in_array($where, ['everywhere', 'site', 'admin', 'login'], true) ? $where : ($type === 'php' ? 'everywhere' : 'site'),
        'position' => in_array($position, ['head', 'body', 'footer'], true) ? $position : 'head',
        'priority' => $priority === '' ? 10 : (int) $priority,
        'who' => (string) (get_post_meta($id, '_lfa_who', true) ?: 'everyone'),
        'paths' => (string) get_post_meta($id, '_lfa_paths', true),
        'notes' => (string) get_post_meta($id, '_lfa_notes', true),
        'error' => (array) (get_post_meta($id, '_lfa_error', true) ?: []),
    ];
}

/** Copy the active snippets into one option, in running order. */
function rebuild_snippets(): void
{
    $active = [];
    foreach (get_posts(['post_type' => SNIPPET_TYPE, 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids', 'no_found_rows' => true]) as $id) {
        $s = snippet_settings((int) $id);
        if (!$s['active'] || trim($s['code']) === '') { continue; }
        $active[] = ['id' => (int) $id, 'type' => $s['type'], 'where' => $s['where'], 'position' => $s['position'], 'priority' => $s['priority'], 'who' => $s['who'], 'paths' => snippet_paths($s['paths']), 'code' => $s['code']];
    }
    usort($active, static function ($a, $b) { return [$a['priority'], $a['id']] <=> [$b['priority'], $b['id']]; });
    update_option(OPTION_SNIPPETS, $active, true);
}

function snippet_changed($postId): void
{
    if (get_post_type((int) $postId) === SNIPPET_TYPE) { rebuild_snippets(); }
}

/** "Only at these addresses": one per line, * matches anything. */
function snippet_paths(string $text): array
{
    $paths = [];
    foreach (preg_split('/\R/', $text) as $line) {
        $line = trim($line);
        if ($line === '') { continue; }
        $path = (string) wp_parse_url($line, PHP_URL_PATH);
        $paths[] = '/' . trim($path !== '' ? $path : $line, '/');
    }
    return array_values(array_unique($paths));
}

/** PHP code without an opening <?php or closing ?> tag. */
function snippet_php(string $code): string
{
    $code = preg_replace('/^\s*<\?(php)?/i', '', $code);
    return (string) preg_replace('/\?>\s*$/', '', (string) $code);
}

/** A syntax check that never runs the code. Returns the problem, or ''. */
function snippet_syntax_error(string $code): string
{
    if (!function_exists('token_get_all')) { return ''; }
    try {
        token_get_all('<?php ' . snippet_php($code), TOKEN_PARSE);
    } catch (\ParseError $e) {
        return $e->getMessage() . ' (line ' . $e->getLine() . ')';
    }
    return '';
}

/* -------------------------------------------------------------------------
 * Running
 * ---------------------------------------------------------------------- */

function snippets_safe_mode(): bool
{
    if (defined('LEAPFROG_SNIPPETS_SAFE_MODE') && LEAPFROG_SNIPPETS_SAFE_MODE) { return true; }
    return isset($_GET['lfa-safe-mode']) && current_user_can('manage_options');
}

function snippet_applies(array $s): bool
{
    global $pagenow;
    $isLogin = ($pagenow ?? '') === 'wp-login.php';
    $place = $isLogin ? 'login' : (is_admin() ? 'admin' : 'site');
    if (wp_doing_ajax() || wp_doing_cron()) {
        // Background requests run PHP snippets (they may add AJAX or scheduled tasks), nothing else.
        if ($s['type'] !== 'php' || $s['where'] === 'login') { return false; }
    } elseif ($s['where'] === 'everywhere') {
        if ($s['type'] !== 'php' && $place === 'login') { return false; }
    } elseif ($s['where'] !== $place) {
        return false;
    }
    if ($s['who'] === 'logged-in' && !is_user_logged_in()) { return false; }
    if ($s['who'] === 'logged-out' && is_user_logged_in()) { return false; }
    if (strpos($s['who'], 'role:') === 0 && !in_array(substr($s['who'], 5), (array) wp_get_current_user()->roles, true)) { return false; }
    if ($s['paths']) {
        $home = rtrim((string) wp_parse_url(home_url(), PHP_URL_PATH), '/');
        $path = (string) wp_parse_url(isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '/', PHP_URL_PATH);
        if ($home !== '' && strpos($path, $home) === 0) { $path = substr($path, strlen($home)); }
        $path = '/' . trim($path, '/');
        $match = false;
        foreach ($s['paths'] as $pattern) {
            if (preg_match('#^' . str_replace('\*', '.*', preg_quote($pattern, '#')) . '$#i', $path)) { $match = true; break; }
        }
        if (!$match) { return false; }
    }
    return true;
}

function run_snippets(): void
{
    if (snippets_safe_mode()) { return; }
    $snippets = get_option(OPTION_SNIPPETS, []);
    if (!is_array($snippets) || !$snippets) { return; }
    register_shutdown_function(__NAMESPACE__ . '\\snippet_shutdown');
    foreach ($snippets as $s) {
        if (!snippet_applies($s)) { continue; }
        if ($s['type'] === 'php') {
            run_php_snippet($s);
        } else {
            output_snippet($s);
        }
    }
}

function run_php_snippet(array $s): void
{
    $GLOBALS['lfa_running_snippet'] = $s['id'];
    try {
        (static function () use ($s) {
            eval(snippet_php($s['code'])); // phpcs:ignore -- Snippets are code written by administrators.
        })();
    } catch (\Throwable $e) {
        snippet_failed($s['id'], $e->getMessage() . ' (line ' . $e->getLine() . ')');
    }
    unset($GLOBALS['lfa_running_snippet']);
}

/** A snippet that stopped PHP with a fatal error is switched off for next time. */
function snippet_shutdown(): void
{
    $id = $GLOBALS['lfa_running_snippet'] ?? 0;
    $error = error_get_last();
    if ($id && $error && in_array($error['type'], [E_ERROR, E_PARSE, E_COMPILE_ERROR, E_CORE_ERROR], true)) {
        snippet_failed((int) $id, $error['message'] . ' (line ' . $error['line'] . ')');
    }
}

function snippet_failed(int $id, string $message): void
{
    update_post_meta($id, '_lfa_active', 0);
    update_post_meta($id, '_lfa_error', ['message' => wp_strip_all_tags($message), 'time' => time()]);
    rebuild_snippets();
}

/** CSS, JavaScript and HTML snippets go into the page head, body or footer. */
function output_snippet(array $s): void
{
    $hooks = [
        'site' => ['head' => 'wp_head', 'body' => 'wp_body_open', 'footer' => 'wp_footer'],
        'admin' => ['head' => 'admin_head', 'body' => 'admin_footer', 'footer' => 'admin_footer'],
        'login' => ['head' => 'login_head', 'body' => 'login_footer', 'footer' => 'login_footer'],
    ];
    $places = $s['where'] === 'everywhere' ? ['site', 'admin'] : [$s['where']];
    foreach ($places as $place) {
        add_action($hooks[$place][$s['position']], static function () use ($s) {
            $id = 'lfa-snippet-' . (int) $s['id'];
            if ($s['type'] === 'css') {
                echo "<style id=\"{$id}\">\n" . $s['code'] . "\n</style>\n"; // phpcs:ignore -- administrator-written CSS
            } elseif ($s['type'] === 'js') {
                echo "<script id=\"{$id}\">\n" . $s['code'] . "\n</script>\n"; // phpcs:ignore -- administrator-written script
            } else {
                echo $s['code'] . "\n"; // phpcs:ignore -- administrator-written HTML
            }
        }, (int) $s['priority']);
    }
}

/* -------------------------------------------------------------------------
 * Edit screen
 * ---------------------------------------------------------------------- */

function is_snippet_screen(): bool
{
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    return $screen && $screen->post_type === SNIPPET_TYPE;
}

/** Plainer words on the snippet screens ("Save Snippet" instead of "Publish"). */
function snippet_screen(\WP_Screen $screen): void
{
    if ($screen->post_type !== SNIPPET_TYPE) { return; }
    add_filter('gettext', static function ($text) {
        $words = ['Publish' => 'Save Snippet', 'Update' => 'Save Snippet', 'Published' => 'Saved', 'Move to Trash' => 'Move to Bin'];
        return $words[$text] ?? $text;
    });
    if ($screen->base === 'post') {
        add_action('admin_enqueue_scripts', __NAMESPACE__ . '\\snippet_editor_assets');
    }
    add_action('admin_head', static function () {
        echo '<style>.post-type-lfa_snippet #minor-publishing-actions,.post-type-lfa_snippet #misc-publishing-actions{display:none}'
            . '.post-type-lfa_snippet .column-lfa_type{width:8em}.post-type-lfa_snippet .column-lfa_order{width:5em}.post-type-lfa_snippet .column-lfa_active{width:7em}'
            . '.post-type-lfa_snippet .lfa-badge{display:inline-block;padding:1px 8px;border-radius:10px;background:#f0f0f1;color:#3c434a;font-size:11px}'
            . '.lfa-switch{display:inline-block;position:relative;width:36px;height:20px;border-radius:10px;background:#8c8f94;vertical-align:middle;transition:background .15s}'
            . '.lfa-switch span{position:absolute;top:3px;left:3px;width:14px;height:14px;border-radius:50%;background:#fff;transition:transform .15s}'
            . '.lfa-switch.is-on{background:var(--wp-admin-theme-color,#2271b1)}.lfa-switch.is-on span{transform:translateX(16px)}'
            . '.lfa-switch:focus{outline:2px solid var(--wp-admin-theme-color,#2271b1);outline-offset:2px;box-shadow:none}'
            . '.post-type-lfa_snippet .lfa-tag{display:inline-block;margin-top:4px;padding:0 6px;border-radius:3px;background:#fcf0e3;color:#8a4b00;font-size:11px;cursor:help}'
            . '#lfa-snippet-code-box .CodeMirror,#lfa-snippet-code + .CodeMirror{height:auto;min-height:320px;border:1px solid #dcdcde}</style>';
    });
}

function snippet_editor_assets(): void
{
    $settings = wp_enqueue_code_editor(['type' => 'text/html', 'codemirror' => ['lint' => false, 'gutters' => [], 'indentUnit' => 4, 'tabSize' => 4]]);
    wp_enqueue_style('lfa-page', asset_url('assets/page.css'), [], asset_version('assets/page.css'));
    wp_enqueue_script('lfa-snippet-editor', asset_url('assets/snippet-editor.js'), ['jquery', 'code-editor'], asset_version('assets/snippet-editor.js'), true);
    wp_add_inline_script('lfa-snippet-editor', 'window.lfaSnippetEditor = ' . wp_json_encode($settings ?: false) . ';', 'before');
}

function snippet_meta_boxes(): void
{
    remove_meta_box('slugdiv', SNIPPET_TYPE, 'normal');
    // Box ids differ from the field ids inside them.
    add_meta_box('lfa-snippet-code-box', 'Code', __NAMESPACE__ . '\\snippet_code_box', SNIPPET_TYPE, 'normal', 'high');
    add_meta_box('lfa-snippet-conditions-box', 'Where and for whom', __NAMESPACE__ . '\\snippet_conditions_box', SNIPPET_TYPE, 'normal', 'default');
    add_meta_box('lfa-snippet-settings-box', 'Snippet', __NAMESPACE__ . '\\snippet_settings_box', SNIPPET_TYPE, 'side', 'high');
}

function snippet_code_box(\WP_Post $post): void
{
    $s = snippet_settings($post->ID);
    wp_nonce_field('lfa_snippet_save', '_lfa_snippet_nonce');
    ?>
    <p class="description" id="lfa-code-help" data-php="PHP runs without an opening &lt;?php tag (one pasted in is removed). Add hooks as you would in functions.php." data-css="CSS only, without &lt;style&gt; tags." data-js="JavaScript only, without &lt;script&gt; tags." data-html="Any HTML, for example a tracking or verification tag."></p>
    <textarea id="lfa-snippet-code" name="lfa_code" rows="18" class="large-text code" spellcheck="false"><?php echo esc_textarea($s['code']); ?></textarea>
    <p><label for="lfa-snippet-notes"><strong>Notes</strong></label><br><textarea id="lfa-snippet-notes" name="lfa_notes" rows="2" class="large-text" placeholder="What this snippet is for, and where it came from"><?php echo esc_textarea($s['notes']); ?></textarea></p>
    <?php
}

function snippet_settings_box(\WP_Post $post): void
{
    $s = snippet_settings($post->ID);
    ?>
    <p><label><input type="checkbox" class="lfa-toggle-plain" name="lfa_active" value="1" <?php checked($s['active'] || $post->post_status === 'auto-draft'); ?>> <strong>Active</strong></label></p>
    <p><label for="lfa-snippet-type"><strong>Type</strong></label><br>
        <select id="lfa-snippet-type" name="lfa_type">
            <?php foreach (SNIPPET_LANGUAGES as $value => $label) : ?><option value="<?php echo esc_attr($value); ?>" <?php selected($s['type'], $value); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?>
        </select></p>
    <p><label for="lfa-snippet-where"><strong>Runs</strong></label><br>
        <select id="lfa-snippet-where" name="lfa_where">
            <?php foreach (['everywhere' => 'Everywhere (site and admin)', 'site' => 'On the site only', 'admin' => 'In the admin only', 'login' => 'On the log-in page only'] as $value => $label) : ?><option value="<?php echo esc_attr($value); ?>" <?php selected($s['where'], $value); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?>
        </select></p>
    <p class="lfa-snippet-position"><label for="lfa-snippet-position"><strong>Placed in</strong></label><br>
        <select id="lfa-snippet-position" name="lfa_position">
            <?php foreach (['head' => 'The page head', 'body' => 'The start of the body', 'footer' => 'The footer'] as $value => $label) : ?><option value="<?php echo esc_attr($value); ?>" <?php selected($s['position'], $value); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?>
        </select></p>
    <p><label for="lfa-snippet-priority"><strong>Order</strong></label><br>
        <input type="number" id="lfa-snippet-priority" name="lfa_priority" value="<?php echo (int) $s['priority']; ?>" min="-999" max="9999" step="1" class="small-text">
        <span class="description">Lower runs first (10 is normal).</span></p>
    <?php
}

function snippet_conditions_box(\WP_Post $post): void
{
    $s = snippet_settings($post->ID);
    ?>
    <table class="form-table" role="presentation">
        <tr>
            <th scope="row"><label for="lfa-snippet-who">For</label></th>
            <td><select id="lfa-snippet-who" name="lfa_who">
                <option value="everyone" <?php selected($s['who'], 'everyone'); ?>>Everyone</option>
                <option value="logged-in" <?php selected($s['who'], 'logged-in'); ?>>People who are logged in</option>
                <option value="logged-out" <?php selected($s['who'], 'logged-out'); ?>>Visitors who are not logged in</option>
                <optgroup label="People with the role">
                    <?php foreach (wp_roles()->roles as $slug => $role) : ?><option value="<?php echo esc_attr('role:' . $slug); ?>" <?php selected($s['who'], 'role:' . $slug); ?>><?php echo esc_html(translate_user_role($role['name'])); ?></option><?php endforeach; ?>
                </optgroup>
            </select></td>
        </tr>
        <tr>
            <th scope="row"><label for="lfa-snippet-paths">Only at these addresses</label></th>
            <td><textarea id="lfa-snippet-paths" name="lfa_paths" rows="3" class="large-text code" placeholder="/contact/&#10;/shop/*"><?php echo esc_textarea($s['paths']); ?></textarea>
                <p class="description">One per line, for example /contact/ or /blog/* (the * matches anything). Leave empty to run on every page.</p></td>
        </tr>
    </table>
    <?php
}

function save_snippet(int $postId, \WP_Post $post): void
{
    if (!isset($_POST['_lfa_snippet_nonce']) || !wp_verify_nonce(sanitize_key($_POST['_lfa_snippet_nonce']), 'lfa_snippet_save')) { return; }
    if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || wp_is_post_revision($postId) || !current_user_can('manage_options')) { return; }
    $type = isset($_POST['lfa_type']) && isset(SNIPPET_LANGUAGES[$_POST['lfa_type']]) ? (string) $_POST['lfa_type'] : 'php';
    // Code is kept exactly as typed: it is code, not text.
    $code = isset($_POST['lfa_code']) && is_string($_POST['lfa_code']) ? wp_unslash($_POST['lfa_code']) : '';
    $active = !empty($_POST['lfa_active']);
    $where = isset($_POST['lfa_where']) && in_array($_POST['lfa_where'], ['everywhere', 'site', 'admin', 'login'], true) ? (string) $_POST['lfa_where'] : 'site';
    $position = isset($_POST['lfa_position']) && in_array($_POST['lfa_position'], ['head', 'body', 'footer'], true) ? (string) $_POST['lfa_position'] : 'head';
    $who = isset($_POST['lfa_who']) && is_string($_POST['lfa_who']) ? sanitize_text_field(wp_unslash($_POST['lfa_who'])) : 'everyone';
    if (!in_array($who, ['everyone', 'logged-in', 'logged-out'], true) && !(strpos($who, 'role:') === 0 && get_role(substr($who, 5)))) { $who = 'everyone'; }

    delete_post_meta($postId, '_lfa_error');
    if ($type === 'php' && $active && ($problem = snippet_syntax_error($code)) !== '') {
        // Saved, but not run: a syntax mistake would stop the site.
        $active = false;
        update_post_meta($postId, '_lfa_error', ['message' => 'Not switched on because of a mistake in the code: ' . $problem, 'time' => time()]);
    }
    update_post_meta($postId, '_lfa_type', $type);
    update_post_meta($postId, '_lfa_code', wp_slash($code));
    update_post_meta($postId, '_lfa_active', $active ? 1 : 0);
    update_post_meta($postId, '_lfa_where', $where);
    update_post_meta($postId, '_lfa_position', $position);
    update_post_meta($postId, '_lfa_priority', max(-999, min(9999, (int) ($_POST['lfa_priority'] ?? 10))));
    update_post_meta($postId, '_lfa_who', $who);
    update_post_meta($postId, '_lfa_paths', isset($_POST['lfa_paths']) && is_string($_POST['lfa_paths']) ? sanitize_textarea_field(wp_unslash($_POST['lfa_paths'])) : '');
    update_post_meta($postId, '_lfa_notes', isset($_POST['lfa_notes']) && is_string($_POST['lfa_notes']) ? sanitize_textarea_field(wp_unslash($_POST['lfa_notes'])) : '');
    rebuild_snippets();
}

function snippet_messages(array $messages): array
{
    $messages[SNIPPET_TYPE] = array_fill(0, 11, 'Snippet saved.');
    $messages[SNIPPET_TYPE][0] = '';
    return $messages;
}

/** Errors on a snippet's screen, safe mode, and import results. */
function snippet_notices(): void
{
    if (!is_snippet_screen()) { return; }
    if (snippets_safe_mode()) {
        echo '<div class="notice notice-warning"><p><strong>Safe mode:</strong> no snippets are running on this page.</p></div>';
    }
    global $post;
    $screen = get_current_screen();
    if ($screen && $screen->base === 'post' && $post instanceof \WP_Post) {
        $error = snippet_settings($post->ID)['error'];
        if (!empty($error['message'])) {
            echo '<div class="notice notice-error"><p><strong>This snippet is switched off.</strong> ' . esc_html($error['message']) . ' — ' . esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), (int) ($error['time'] ?? time()))) . '. Fix the code, tick Active and save.</p></div>';
        }
    }
    if (isset($_GET['lfa_imported'])) {
        $count = absint($_GET['lfa_imported']);
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($count === 1 ? '1 snippet imported. It is switched off until you check it and switch it on.' : $count . ' snippets imported. They are switched off until you check them and switch them on.') . '</p></div>';
    }
}

/* -------------------------------------------------------------------------
 * List screen
 * ---------------------------------------------------------------------- */

function snippet_columns(array $columns): array
{
    return ['cb' => $columns['cb'] ?? '', 'title' => 'Snippet', 'lfa_type' => 'Type', 'lfa_runs' => 'Runs', 'lfa_order' => 'Order', 'lfa_active' => 'Active'];
}

function snippet_column(string $column, int $postId): void
{
    $s = snippet_settings($postId);
    if ($column === 'lfa_type') {
        echo '<span class="lfa-badge">' . esc_html(SNIPPET_LANGUAGES[$s['type']]) . '</span>';
    } elseif ($column === 'lfa_runs') {
        $where = ['everywhere' => 'Everywhere', 'site' => 'Site', 'admin' => 'Admin', 'login' => 'Log-in page'][$s['where']];
        $parts = [$where];
        if ($s['type'] !== 'php') { $parts[] = ['head' => 'head', 'body' => 'body start', 'footer' => 'footer'][$s['position']]; }
        if ($s['who'] !== 'everyone') {
            $roles = wp_roles()->roles;
            $role = substr($s['who'], 5);
            $parts[] = $s['who'] === 'logged-in' ? 'logged in' : ($s['who'] === 'logged-out' ? 'logged out' : (isset($roles[$role]) ? translate_user_role($roles[$role]['name']) : $role));
        }
        if (trim($s['paths']) !== '') { $parts[] = 'some addresses'; }
        echo esc_html(implode(' · ', $parts));
        if ($s['notes'] !== '') { echo '<br><span class="description">' . esc_html(wp_trim_words($s['notes'], 14)) . '</span>'; }
    } elseif ($column === 'lfa_order') {
        echo (int) $s['priority'];
    } elseif ($column === 'lfa_active') {
        $url = wp_nonce_url(add_query_arg(['action' => 'leapfrog_admin_snippet_toggle', 'snippet' => $postId], admin_url('admin-post.php')), 'lfa_snippet_toggle_' . $postId);
        echo '<a class="lfa-switch' . ($s['active'] ? ' is-on' : '') . '" href="' . esc_url($url) . '" role="switch" aria-checked="' . ($s['active'] ? 'true' : 'false') . '" aria-label="' . esc_attr(($s['active'] ? 'Switch off: ' : 'Switch on: ') . get_the_title($postId)) . '"><span></span></a>';
        if (!empty($s['error']['message'])) { echo '<br><span class="lfa-tag lfa-tag-powerful" title="' . esc_attr($s['error']['message']) . '">Error</span>'; }
    }
}

function snippet_row_actions(array $actions, \WP_Post $post): array
{
    if ($post->post_type === SNIPPET_TYPE) { unset($actions['inline hide-if-no-js'], $actions['view']); }
    return $actions;
}

function toggle_snippet(): void
{
    $id = isset($_GET['snippet']) ? absint($_GET['snippet']) : 0;
    check_admin_referer('lfa_snippet_toggle_' . $id);
    if (!current_user_can('manage_options') || get_post_type($id) !== SNIPPET_TYPE) { wp_die('You can’t change this snippet.', '', ['response' => 403]); }
    $s = snippet_settings($id);
    $on = !$s['active'];
    if ($on && $s['type'] === 'php' && ($problem = snippet_syntax_error($s['code'])) !== '') {
        update_post_meta($id, '_lfa_error', ['message' => 'Not switched on because of a mistake in the code: ' . $problem, 'time' => time()]);
        $on = false;
    } elseif ($on) {
        delete_post_meta($id, '_lfa_error');
    }
    update_post_meta($id, '_lfa_active', $on ? 1 : 0);
    rebuild_snippets();
    wp_safe_redirect(wp_get_referer() ?: admin_url('edit.php?post_type=' . SNIPPET_TYPE));
    exit;
}

/* -------------------------------------------------------------------------
 * Export and import
 * ---------------------------------------------------------------------- */

function snippet_bulk_actions(array $actions): array
{
    $actions['lfa_export'] = 'Export';
    return $actions;
}

function snippet_bulk_handler(string $redirect, string $action, array $ids): string
{
    if ($action !== 'lfa_export' || !current_user_can('manage_options')) { return $redirect; }
    $snippets = [];
    foreach ($ids as $id) {
        $post = get_post((int) $id);
        if (!$post || $post->post_type !== SNIPPET_TYPE) { continue; }
        $s = snippet_settings($post->ID);
        unset($s['active'], $s['error']);
        $snippets[] = ['title' => $post->post_title] + $s;
    }
    nocache_headers();
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="leapfrog-snippets.json"');
    header('X-Content-Type-Options: nosniff');
    echo wp_json_encode(['plugin' => SNIPPETS_EXPORT_ID, 'type' => 'snippets', 'format_version' => 1, 'snippets' => $snippets], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function snippet_import_menu(): void
{
    add_submenu_page('edit.php?post_type=' . SNIPPET_TYPE, 'Import snippets', 'Import', 'manage_options', 'lfa-snippet-import', __NAMESPACE__ . '\\render_snippet_import');
}

function render_snippet_import(): void
{
    $error = isset($_GET['lfa_import_error']);
    ?>
    <div class="wrap">
        <h1>Import snippets</h1>
        <?php if ($error) : ?><div class="notice notice-error"><p>That isn’t a Leapfrog snippets file. Nothing was imported.</p></div><?php endif; ?>
        <p>Choose a file exported from Snippets on another site (tick snippets in the list, then Bulk actions &gt; Export). Imported snippets start switched off, so you can check them first.</p>
        <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="leapfrog_admin_snippet_import">
            <?php wp_nonce_field('leapfrog_admin_snippet_import', '_lfa_nonce'); ?>
            <p><input type="file" name="snippets_file" accept=".json,application/json" required></p>
            <?php submit_button('Import Snippets'); ?>
        </form>
    </div>
    <?php
}

function import_snippets(): void
{
    check_admin_referer('leapfrog_admin_snippet_import', '_lfa_nonce');
    if (!current_user_can('manage_options')) { wp_die('You can’t import snippets.', '', ['response' => 403]); }
    $back = admin_url('edit.php?post_type=' . SNIPPET_TYPE . '&page=lfa-snippet-import&lfa_import_error=1');
    $file = $_FILES['snippets_file']['tmp_name'] ?? '';
    $json = $file && is_uploaded_file($file) && filesize($file) <= 1048576 ? (string) file_get_contents($file) : '';
    $data = json_decode($json, true);
    if (!is_array($data) || ($data['plugin'] ?? '') !== SNIPPETS_EXPORT_ID || ($data['type'] ?? '') !== 'snippets' || !is_array($data['snippets'] ?? null)) {
        wp_safe_redirect($back);
        exit;
    }
    $count = 0;
    foreach (array_slice($data['snippets'], 0, 200) as $item) {
        if (!is_array($item) || !is_string($item['code'] ?? null)) { continue; }
        $id = wp_insert_post(['post_type' => SNIPPET_TYPE, 'post_status' => 'publish', 'post_title' => sanitize_text_field((string) ($item['title'] ?? 'Imported snippet'))], true);
        if (is_wp_error($id)) { continue; }
        $type = isset(SNIPPET_LANGUAGES[$item['type'] ?? '']) ? $item['type'] : 'php';
        update_post_meta($id, '_lfa_type', $type);
        update_post_meta($id, '_lfa_code', wp_slash($item['code']));
        update_post_meta($id, '_lfa_active', 0);
        update_post_meta($id, '_lfa_where', in_array($item['where'] ?? '', ['everywhere', 'site', 'admin', 'login'], true) ? $item['where'] : 'site');
        update_post_meta($id, '_lfa_position', in_array($item['position'] ?? '', ['head', 'body', 'footer'], true) ? $item['position'] : 'head');
        update_post_meta($id, '_lfa_priority', max(-999, min(9999, (int) ($item['priority'] ?? 10))));
        $who = is_string($item['who'] ?? null) ? $item['who'] : 'everyone';
        update_post_meta($id, '_lfa_who', in_array($who, ['everyone', 'logged-in', 'logged-out'], true) || (strpos($who, 'role:') === 0 && get_role(substr($who, 5))) ? $who : 'everyone');
        update_post_meta($id, '_lfa_paths', sanitize_textarea_field((string) ($item['paths'] ?? '')));
        update_post_meta($id, '_lfa_notes', sanitize_textarea_field((string) ($item['notes'] ?? '')));
        $count++;
    }
    rebuild_snippets();
    wp_safe_redirect(admin_url('edit.php?post_type=' . SNIPPET_TYPE . '&lfa_imported=' . $count));
    exit;
}

/* -------------------------------------------------------------------------
 * Leapfrog Admin tab
 * ---------------------------------------------------------------------- */

function render_snippets_tab(): void
{
    $active = get_option(OPTION_SNIPPETS, []);
    $count = is_array($active) ? count($active) : 0;
    ?>
    <p class="lfa-narrow-text">Small, site-specific code: PHP, CSS, JavaScript and HTML. Snippets every site needs belong in a Leapfrog plugin instead. Manage them under <a href="<?php echo esc_url(admin_url('edit.php?post_type=' . SNIPPET_TYPE)); ?>">Snippets</a> in the menu (<?php echo esc_html($count === 1 ? '1 is switched on' : $count . ' are switched on'); ?>). Only administrators can see or change snippets.</p>
    <h2>If a snippet breaks something</h2>
    <ul class="lfa-coming">
        <li>A PHP snippet with a mistake in it is saved but not switched on.</li>
        <li>A snippet that causes an error while running is switched off, and the error is shown on its screen.</li>
        <li>To run no snippets while you look: add <code>?lfa-safe-mode=1</code> to an admin address while logged in as an administrator, for example <a href="<?php echo esc_url(admin_url('edit.php?post_type=' . SNIPPET_TYPE . '&lfa-safe-mode=1')); ?>">Snippets in safe mode</a>.</li>
        <li>If you can’t get into the admin at all, add <code>define( 'LEAPFROG_SNIPPETS_SAFE_MODE', true );</code> to wp-config.php, fix or switch off the snippet, then remove that line.</li>
    </ul>
    <?php
}
