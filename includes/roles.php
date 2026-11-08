<?php
namespace Leapfrog\Admin;

if (!defined('ABSPATH')) { exit; }

/**
 * The User roles feature: create, copy, rename and delete roles and choose each
 * role's permissions (WordPress calls them capabilities). WordPress stores roles
 * itself (the wp_user_roles option), so they stay if this plugin is switched off.
 */

const BUILT_IN_ROLES = ['administrator', 'editor', 'author', 'contributor', 'subscriber'];

function roles_boot(): void
{
    add_action('admin_post_leapfrog_admin_role_add', __NAMESPACE__ . '\\role_add');
    add_action('admin_post_leapfrog_admin_role_save', __NAMESPACE__ . '\\role_save');
    add_action('admin_post_leapfrog_admin_role_delete', __NAMESPACE__ . '\\role_delete');
    add_action('admin_post_leapfrog_admin_role_restore', __NAMESPACE__ . '\\role_restore');
    add_action('admin_enqueue_scripts', __NAMESPACE__ . '\\roles_assets');
}

function roles_assets(string $hook): void
{
    if ($hook !== PAGE_HOOK || current_tab() !== 'roles' || empty($_GET['role'])) {
        return;
    }
    wp_enqueue_script('lfa-roles', asset_url('assets/roles.js'), ['lfa-page'], asset_version('assets/roles.js'), true);
}

/* -------------------------------------------------------------------------
 * Permissions in plain words
 * ---------------------------------------------------------------------- */

/** WordPress's own permissions, by area: [description, powerful]. */
function core_capabilities(): array
{
    return [
        'Media, comments and categories' => [
            'upload_files' => ['Upload files to the Media Library', false],
            'unfiltered_upload' => ['Upload any type of file, including types WordPress normally blocks', true],
            'moderate_comments' => ['Approve, edit and delete comments', false],
            'manage_categories' => ['Add, edit and delete categories and tags', false],
            'manage_links' => ['Use the old Links screen', false],
        ],
        'Appearance' => [
            'switch_themes' => ['Change the active theme', false],
            'edit_theme_options' => ['Use menus, widgets, the Customiser and the site editor', false],
            'install_themes' => ['Install new themes', true],
            'update_themes' => ['Update themes', false],
            'delete_themes' => ['Delete themes', true],
            'edit_themes' => ['Edit theme files in the theme file editor', true],
        ],
        'Plugins' => [
            'activate_plugins' => ['Switch plugins on and off', true],
            'install_plugins' => ['Install new plugins', true],
            'update_plugins' => ['Update plugins', false],
            'delete_plugins' => ['Delete plugins', true],
            'edit_plugins' => ['Edit plugin files in the plugin file editor', true],
        ],
        'Users' => [
            'list_users' => ['See the list of users', false],
            'create_users' => ['Add new users', true],
            'edit_users' => ['Edit other people’s profiles', true],
            'promote_users' => ['Change other people’s roles', true],
            'remove_users' => ['Remove people from the site', true],
            'delete_users' => ['Delete users', true],
        ],
        'Site and settings' => [
            'read' => ['Log in to the dashboard and edit their own profile', false],
            'edit_dashboard' => ['Customise the dashboard', false],
            'manage_options' => ['Change site settings (the Settings menu) and use Leapfrog', true],
            'import' => ['Import content', false],
            'export' => ['Export content', false],
            'update_core' => ['Update WordPress', true],
            'unfiltered_html' => ['Add any HTML or scripts to content', true],
            'edit_files' => ['Edit files (an old permission WordPress rarely uses)', true],
        ],
    ];
}

/** Permissions of plugins and themes Leapfrog knows: group => [cap => [description, powerful]]. */
function known_capabilities(): array
{
    $known = [];
    if (get_template() === 'bricks') {
        $known['Bricks'] = [
            'bricks_full_access' => ['Use the Bricks builder with full access', false],
            'bricks_edit_content' => ['Edit text and images in the Bricks builder (content only)', false],
            'bricks_execute_code' => ['Run PHP, JavaScript and HTML in Bricks code elements', true],
            'bricks_upload_svg' => ['Upload SVG files in Bricks', true],
            'bricks_form_submission_access' => ['See Bricks form submissions', false],
            'bricks_bypass_maintenance' => ['See the site while Bricks maintenance mode is on', false],
        ];
    }
    return $known;
}

function group_notes(): array
{
    return [
        'Bricks' => 'Bricks also sets builder access for each role under Bricks > Settings > Builder access.',
        'Other' => 'Added by plugins or themes Leapfrog doesn’t recognise. Names are shown as WordPress stores them.',
    ];
}

/** Sentences for a post type's permissions; %s is its plural name. */
function post_type_verbs(): array
{
    return [
        'create_posts' => 'Add new %s',
        'edit_posts' => 'Create and edit their own %s',
        'publish_posts' => 'Publish %s',
        'edit_published_posts' => 'Edit their own %s after publishing',
        'delete_posts' => 'Delete their own unpublished %s',
        'delete_published_posts' => 'Delete their own published %s',
        'edit_others_posts' => 'Edit other people’s %s',
        'delete_others_posts' => 'Delete other people’s %s',
        'read_private_posts' => 'See private %s',
        'edit_private_posts' => 'Edit private %s',
        'delete_private_posts' => 'Delete private %s',
    ];
}

/** Category and tag permissions that WordPress maps to manage_categories. */
const MAPPED_TERM_CAPABILITIES = ['edit_categories', 'delete_categories', 'assign_categories', 'manage_post_tags', 'edit_post_tags', 'delete_post_tags', 'assign_post_tags', 'manage_terms', 'edit_terms', 'delete_terms', 'assign_terms'];

/** Old "user levels" from WordPress 2.0. Kept on roles, never shown or changed. */
function is_level_capability(string $cap): bool
{
    return (bool) preg_match('/^level_(10|[0-9])$/', $cap);
}

/** Active plugins, as [search key => plugin name], to name unknown permissions. */
function plugin_keys(): array
{
    if (!function_exists('get_plugins')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    $plugins = get_plugins();
    $keys = [];
    foreach ((array) get_option('active_plugins', []) as $file) {
        $folder = dirname($file) === '.' ? basename($file, '.php') : dirname($file);
        $key = preg_replace('/(pro|lite|premium)$/', '', str_replace(['-', '_'], '', strtolower($folder)));
        if (strlen($key) >= 4) {
            $keys[$key] = $plugins[$file]['Name'] ?? $folder;
        }
    }
    return $keys;
}

/**
 * Every permission the editor shows, in groups:
 * [group label => [cap => ['text' => …, 'powerful' => bool]]].
 * Posts and Pages first, then other content types, WordPress's areas, known
 * plugins, plugins matched by name, and Other.
 */
function capability_groups(): array
{
    $core = core_capabilities();
    $coreCaps = [];
    foreach ($core as $caps) {
        $coreCaps += $caps;
    }
    $claimed = $coreCaps;
    $content = [];

    // Content types other than Posts and Pages only list permissions some role has:
    // many map theirs to other permissions, so the rest would do nothing.
    $held = [];
    foreach (wp_roles()->roles as $role) {
        $held += (array) $role['capabilities'];
    }

    // Content types: Posts, Pages, then the rest by name.
    $types = get_post_types(['show_ui' => true], 'objects');
    unset($types['attachment']);
    uasort($types, static function ($a, $b) {
        $rank = static function ($type) { return ['post' => 0, 'page' => 1][$type->name] ?? 2; };
        return [$rank($a), $a->labels->name] <=> [$rank($b), $b->labels->name];
    });
    foreach ($types as $type) {
        $plural = function_exists('mb_strtolower') ? mb_strtolower($type->labels->name) : strtolower($type->labels->name);
        foreach (post_type_verbs() as $key => $sentence) {
            $cap = (string) ($type->cap->$key ?? '');
            if ($cap === '' || isset($claimed[$cap]) || (!in_array($type->name, ['post', 'page'], true) && !array_key_exists($cap, $held))) {
                continue;
            }
            $claimed[$cap] = true;
            $content[$type->labels->name][$cap] = ['text' => sprintf($sentence, $plural), 'powerful' => false];
        }
    }

    // Custom category-style taxonomies with their own permissions.
    $termVerbs = ['manage_terms' => 'Manage %s', 'edit_terms' => 'Edit %s', 'delete_terms' => 'Delete %s', 'assign_terms' => 'Choose %s for content'];
    foreach (get_taxonomies(['show_ui' => true], 'objects') as $taxonomy) {
        foreach ($termVerbs as $key => $sentence) {
            $cap = (string) ($taxonomy->cap->$key ?? '');
            if ($cap === '' || isset($claimed[$cap]) || in_array($cap, MAPPED_TERM_CAPABILITIES, true) || !array_key_exists($cap, $held)) {
                continue;
            }
            $claimed[$cap] = true;
            $content[$taxonomy->labels->name][$cap] = ['text' => sprintf($sentence, strtolower($taxonomy->labels->name)), 'powerful' => false];
        }
    }

    $groups = $content;
    foreach ($core as $label => $caps) {
        foreach ($caps as $cap => [$text, $powerful]) {
            $groups[$label][$cap] = ['text' => $text, 'powerful' => $powerful];
        }
    }
    foreach (known_capabilities() as $label => $caps) {
        foreach ($caps as $cap => [$text, $powerful]) {
            if (!isset($claimed[$cap])) {
                $claimed[$cap] = true;
                $groups[$label][$cap] = ['text' => $text, 'powerful' => $powerful];
            }
        }
    }

    // Whatever roles have that is still unclaimed: match to a plugin, else Other.
    $rest = [];
    foreach (array_keys($held) as $cap) {
        $cap = (string) $cap;
        if (!isset($claimed[$cap]) && !is_level_capability($cap) && !in_array($cap, MAPPED_TERM_CAPABILITIES, true)) {
            $rest[$cap] = true;
        }
    }
    ksort($rest);
    $keys = plugin_keys();
    $other = [];
    foreach (array_keys($rest) as $cap) {
        $flat = str_replace(['_', '-'], '', strtolower($cap));
        $label = 'Other';
        foreach ($keys as $key => $name) {
            if (strpos($flat, $key) !== false) {
                $label = $name;
                break;
            }
        }
        $entry = ['text' => ucfirst(str_replace(['_', '-'], ' ', $cap)), 'powerful' => false];
        if ($label === 'Other') {
            $other[$cap] = $entry;
        } else {
            $groups[$label][$cap] = $entry;
        }
    }
    if ($other) {
        $groups['Other'] = $other;
    }
    return $groups;
}

/** Flat list of every permission the editor shows. */
function shown_capabilities(): array
{
    $caps = [];
    foreach (capability_groups() as $group) {
        $caps += $group;
    }
    return array_keys($caps);
}

/** Permissions that can't be removed from this role, with the reason. */
function locked_capabilities(string $slug): array
{
    if ($slug !== 'administrator' && !in_array($slug, (array) wp_get_current_user()->roles, true)) {
        return [];
    }
    $why = $slug === 'administrator' ? 'Administrators always keep this.' : 'You have this role, so this stays on to keep you from locking yourself out.';
    return ['read' => $why, 'manage_options' => $why];
}

/* -------------------------------------------------------------------------
 * Changing roles
 * ---------------------------------------------------------------------- */

function role_name(string $slug): string
{
    $roles = wp_roles()->roles;
    return isset($roles[$slug]) ? translate_user_role($roles[$slug]['name']) : $slug;
}

function role_deletable(string $slug): bool
{
    return !in_array($slug, BUILT_IN_ROLES, true)
        && $slug !== get_option('default_role')
        && !in_array($slug, (array) wp_get_current_user()->roles, true);
}

/** Save a role's name and permissions in one write. */
function write_role(string $slug, string $name, array $caps): void
{
    $wpRoles = wp_roles();
    $wpRoles->roles[$slug]['name'] = $name;
    $wpRoles->roles[$slug]['capabilities'] = $caps;
    $wpRoles->role_names[$slug] = $name;
    if ($wpRoles->use_db) {
        update_option($wpRoles->role_key, $wpRoles->roles);
    }
}

/** Keep each role as it was before Leapfrog first changed it, for "Undo all changes". */
function backup_role(string $slug): void
{
    $backups = role_backups();
    if (isset($backups[$slug])) {
        return;
    }
    $role = wp_roles()->roles[$slug];
    $backups[$slug] = ['name' => $role['name'], 'capabilities' => $role['capabilities'], 'time' => time()];
    update_option(OPTION_ROLE_BACKUPS, $backups, false);
}

function role_backups(): array
{
    $backups = get_option(OPTION_ROLE_BACKUPS, []);
    return is_array($backups) ? $backups : [];
}

function posted_slug(string $field = 'role'): string
{
    return isset($_POST[$field]) && is_string($_POST[$field]) ? sanitize_key(wp_unslash($_POST[$field])) : '';
}

function posted_role_name(): string
{
    $name = isset($_POST['role_name']) && is_string($_POST['role_name']) ? trim(sanitize_text_field(wp_unslash($_POST['role_name']))) : '';
    $length = function_exists('mb_strlen') ? mb_strlen($name) : strlen($name);
    return $length >= 1 && $length <= 60 ? $name : '';
}

function unique_role_slug(string $name): string
{
    $base = trim(sanitize_key(str_replace([' ', '-'], '_', remove_accents(strtolower($name)))), '_');
    $base = $base !== '' ? substr($base, 0, 40) : 'role';
    $slug = $base;
    for ($n = 2; get_role($slug); $n++) {
        $slug = $base . '_' . $n;
    }
    return $slug;
}

function role_add(): void
{
    authorise('leapfrog_admin_role_add');
    $name = posted_role_name();
    if ($name === '') {
        return_to_page('role_name', ['tab' => 'roles']);
    }
    $caps = ['read' => true];
    $from = posted_slug('copy_from');
    if ($from !== '') {
        if (!get_role($from)) {
            return_to_page('role_missing', ['tab' => 'roles']);
        }
        $caps = wp_roles()->roles[$from]['capabilities'];
    }
    $slug = unique_role_slug($name);
    add_role($slug, $name, $caps);
    return_to_page('role_added', ['tab' => 'roles', 'role' => $slug]);
}

function role_save(): void
{
    authorise('leapfrog_admin_role_save');
    $slug = posted_slug();
    if (!get_role($slug)) {
        return_to_page('role_missing', ['tab' => 'roles']);
    }
    $name = posted_role_name();
    if ($name === '') {
        return_to_page('role_name', ['tab' => 'roles', 'role' => $slug]);
    }
    $new = isset($_POST['new_permission']) && is_string($_POST['new_permission']) ? trim(wp_unslash($_POST['new_permission'])) : '';
    if ($new !== '' && !preg_match('/^[a-z0-9_]{2,64}$/D', $new)) {
        return_to_page('role_permission', ['tab' => 'roles', 'role' => $slug]);
    }

    backup_role($slug);
    $caps = (array) wp_roles()->roles[$slug]['capabilities'];
    $ticked = isset($_POST['caps']) && is_array($_POST['caps']) ? array_map('strval', wp_unslash($_POST['caps'])) : [];
    $locked = locked_capabilities($slug);
    foreach (shown_capabilities() as $cap) {
        // Locked boxes are disabled, so browsers don't send them: keep what the role has.
        if (in_array($cap, $ticked, true) || (isset($locked[$cap]) && !empty($caps[$cap]))) {
            $caps[$cap] = true;
        } elseif (!empty($caps[$cap])) {
            unset($caps[$cap]); // Not allowed any more. A "not allowed" (false) entry is left alone.
        }
    }
    if ($new !== '') {
        $caps[$new] = true;
    }
    // A role saved with its original name keeps the stored (untranslated) one.
    $stored = wp_roles()->roles[$slug]['name'];
    write_role($slug, $name === translate_user_role($stored) ? $stored : $name, $caps);
    return_to_page('role_saved', ['tab' => 'roles', 'role' => $slug]);
}

function role_restore(): void
{
    authorise('leapfrog_admin_role_restore');
    $slug = posted_slug();
    $backups = role_backups();
    if (!get_role($slug) || !isset($backups[$slug])) {
        return_to_page('role_missing', ['tab' => 'roles']);
    }
    $caps = (array) $backups[$slug]['capabilities'];
    foreach (locked_capabilities($slug) as $cap => $why) {
        if (!empty(wp_roles()->roles[$slug]['capabilities'][$cap])) {
            $caps[$cap] = true;
        }
    }
    write_role($slug, (string) $backups[$slug]['name'], $caps);
    unset($backups[$slug]);
    update_option(OPTION_ROLE_BACKUPS, $backups, false);
    return_to_page('role_restored', ['tab' => 'roles', 'role' => $slug]);
}

function role_delete(): void
{
    authorise('leapfrog_admin_role_delete');
    $slug = posted_slug();
    if (!get_role($slug)) {
        return_to_page('role_missing', ['tab' => 'roles']);
    }
    if (!role_deletable($slug)) {
        return_to_page('role_protected', ['tab' => 'roles', 'role' => $slug]);
    }
    $move = posted_slug('move_to');
    if ($move === $slug || !get_role($move)) {
        return_to_page('role_move', ['tab' => 'roles', 'role' => $slug]);
    }
    foreach (get_users(['role' => $slug]) as $user) {
        $user->remove_role($slug);
        if (!$user->roles) {
            $user->add_role($move);
        }
    }
    remove_role($slug);
    $backups = role_backups();
    unset($backups[$slug]);
    update_option(OPTION_ROLE_BACKUPS, $backups, false);
    return_to_page('role_deleted', ['tab' => 'roles']);
}

/* -------------------------------------------------------------------------
 * Screens
 * ---------------------------------------------------------------------- */

function render_roles_tab(): void
{
    $slug = isset($_GET['role']) && is_string($_GET['role']) ? sanitize_key($_GET['role']) : '';
    if ($slug !== '' && get_role($slug)) {
        render_role_editor($slug);
    } else {
        render_role_list();
    }
}

function role_badges(string $slug): string
{
    $badges = [];
    if (in_array($slug, BUILT_IN_ROLES, true)) {
        $badges[] = 'Built in';
    }
    if ($slug === get_option('default_role')) {
        $badges[] = 'New users';
    }
    if (in_array($slug, (array) wp_get_current_user()->roles, true)) {
        $badges[] = 'Your role';
    }
    $html = '';
    foreach ($badges as $badge) {
        $html .= ' <span class="lfa-badge">' . esc_html($badge) . '</span>';
    }
    return $html;
}

function people_link(string $slug, array $counts): string
{
    $count = (int) ($counts[$slug] ?? 0);
    $label = $count === 1 ? '1 person' : $count . ' people';
    return $count ? '<a href="' . esc_url(admin_url('users.php?role=' . $slug)) . '">' . esc_html($label) . '</a>' : esc_html($label);
}

function render_role_list(): void
{
    $roles = wp_roles()->roles;
    $counts = count_users()['avail_roles'];
    $post = admin_url('admin-post.php');
    ?>
    <p>Roles decide what people can do on this site. WordPress stores them itself, so they stay as they are if Leapfrog Admin is switched off or removed.</p>
    <table class="widefat striped lfa-roles-table">
        <thead><tr><th scope="col">Role</th><th scope="col">People</th><th scope="col">Permissions</th><th scope="col"><span class="screen-reader-text">Actions</span></th></tr></thead>
        <tbody>
        <?php foreach ($roles as $slug => $role) :
            $granted = count(array_filter((array) $role['capabilities'], static function ($value, $cap) { return $value && !is_level_capability((string) $cap); }, ARRAY_FILTER_USE_BOTH));
            $name = translate_user_role($role['name']);
            ?>
            <tr>
                <td><a class="row-title" href="<?php echo esc_url(page_url(['tab' => 'roles', 'role' => $slug])); ?>"><?php echo esc_html($name); ?></a><?php echo role_badges((string) $slug); // phpcs:ignore -- escaped in role_badges() ?><br><code><?php echo esc_html((string) $slug); ?></code></td>
                <td><?php echo people_link((string) $slug, $counts); // phpcs:ignore -- escaped in people_link() ?></td>
                <td><?php echo (int) $granted; ?></td>
                <td class="lfa-role-actions">
                    <a class="button" href="<?php echo esc_url(page_url(['tab' => 'roles', 'role' => $slug])); ?>">Edit</a>
                    <form action="<?php echo esc_url($post); ?>" method="post">
                        <input type="hidden" name="action" value="leapfrog_admin_role_add">
                        <input type="hidden" name="copy_from" value="<?php echo esc_attr((string) $slug); ?>">
                        <input type="hidden" name="role_name" value="<?php echo esc_attr((function_exists('mb_substr') ? mb_substr($name, 0, 53) : substr($name, 0, 53)) . ' (copy)'); ?>">
                        <?php wp_nonce_field('leapfrog_admin_role_add', '_lfa_nonce', false); ?>
                        <button type="submit" class="button">Copy</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <div class="lfa-panel lfa-narrow">
        <h2>Add a role</h2>
        <form action="<?php echo esc_url($post); ?>" method="post">
            <input type="hidden" name="action" value="leapfrog_admin_role_add">
            <?php wp_nonce_field('leapfrog_admin_role_add', '_lfa_nonce', false); ?>
            <p><label for="lfa-new-role-name">Role name</label><br><input type="text" id="lfa-new-role-name" name="role_name" maxlength="60" required class="regular-text" placeholder="e.g. Shop Manager"></p>
            <p><label for="lfa-new-role-from">Start with</label><br>
                <select id="lfa-new-role-from" name="copy_from">
                    <option value="">Only logging in (like Subscriber)</option>
                    <?php foreach ($roles as $slug => $role) : ?>
                        <option value="<?php echo esc_attr((string) $slug); ?>">The permissions of <?php echo esc_html(translate_user_role($role['name'])); ?></option>
                    <?php endforeach; ?>
                </select></p>
            <?php submit_button('Add Role', 'primary', 'submit', false); ?>
        </form>
    </div>
    <?php
}

function render_role_editor(string $slug): void
{
    $role = wp_roles()->roles[$slug];
    $caps = (array) $role['capabilities'];
    $groups = capability_groups();
    $notes = group_notes();
    $locked = locked_capabilities($slug);
    $backups = role_backups();
    $counts = count_users()['avail_roles'];
    $post = admin_url('admin-post.php');
    $name = translate_user_role($role['name']);
    ?>
    <p><a href="<?php echo esc_url(page_url(['tab' => 'roles'])); ?>">&larr; All roles</a></p>
    <h2 class="lfa-role-title"><?php echo esc_html($name); ?><?php echo role_badges($slug); // phpcs:ignore -- escaped in role_badges() ?></h2>
    <p><?php echo people_link($slug, $counts); // phpcs:ignore -- escaped in people_link() ?> with this role.</p>

    <form action="<?php echo esc_url($post); ?>" method="post" id="lfa-role-form" class="lfa-track">
        <input type="hidden" name="action" value="leapfrog_admin_role_save">
        <input type="hidden" name="role" value="<?php echo esc_attr($slug); ?>">
        <?php wp_nonce_field('leapfrog_admin_role_save', '_lfa_nonce', false); ?>
        <p><label for="lfa-role-name"><strong>Role name</strong></label><br>
            <input type="text" id="lfa-role-name" name="role_name" value="<?php echo esc_attr($name); ?>" maxlength="60" required class="regular-text">
            <span class="description">ID <code><?php echo esc_html($slug); ?></code> stays the same.</span></p>

        <div class="lfa-cap-toolbar">
            <label class="screen-reader-text" for="lfa-cap-search">Find a permission</label>
            <input type="search" id="lfa-cap-search" placeholder="Find a permission" class="regular-text" data-lfa-ignore>
            <label data-lfa-ignore><input type="checkbox" id="lfa-cap-ticked"> Only show ticked</label>
            <span id="lfa-cap-count" class="lfa-cap-count" role="status"></span>
        </div>

        <div class="lfa-cap-groups">
        <?php foreach ($groups as $label => $groupCaps) : ?>
            <fieldset class="lfa-cap-group">
                <legend><?php echo esc_html($label); ?> <span class="lfa-cap-group-count"></span></legend>
                <p class="lfa-cap-group-actions"><button type="button" class="button-link" data-lfa-tick="1">Tick all</button> · <button type="button" class="button-link" data-lfa-tick="0">Untick all</button></p>
                <?php if (isset($notes[$label])) : ?><p class="description"><?php echo esc_html($notes[$label]); ?></p><?php endif; ?>
                <ul class="lfa-cap-list">
                <?php foreach ($groupCaps as $cap => $info) :
                    $on = !empty($caps[$cap]);
                    $blocked = array_key_exists($cap, $caps) && !$caps[$cap];
                    $id = 'lfa-cap-' . md5($cap);
                    ?>
                    <li class="lfa-cap" data-lfa-search="<?php echo esc_attr(strtolower($info['text'] . ' ' . $cap)); ?>">
                        <input type="checkbox" id="<?php echo esc_attr($id); ?>" name="caps[]" value="<?php echo esc_attr($cap); ?>" <?php checked($on); ?> <?php disabled(isset($locked[$cap]) && $on); ?>>
                        <label for="<?php echo esc_attr($id); ?>"><?php echo esc_html($info['text']); ?>
                            <code><?php echo esc_html($cap); ?></code>
                            <?php if ($info['powerful']) : ?><span class="lfa-tag lfa-tag-powerful" title="Give this only to people you trust with the whole site.">Powerful</span><?php endif; ?>
                            <?php if (isset($locked[$cap]) && $on) : ?><span class="lfa-tag" title="<?php echo esc_attr($locked[$cap]); ?>">Locked</span><?php endif; ?>
                            <?php if ($blocked) : ?><span class="lfa-tag" title="Set to “not allowed” by another plugin. Ticking it allows it.">Blocked</span><?php endif; ?>
                        </label>
                    </li>
                <?php endforeach; ?>
                </ul>
            </fieldset>
        <?php endforeach; ?>
        </div>

        <details class="lfa-panel lfa-narrow">
            <summary>Add a permission</summary>
            <p>For a permission that a plugin’s instructions ask you to give a role. Lower-case letters, numbers and underscores.</p>
            <p><label for="lfa-new-permission">Permission name</label><br><input type="text" id="lfa-new-permission" name="new_permission" pattern="[a-z0-9_]{2,64}" maxlength="64" class="regular-text code" placeholder="e.g. manage_bookings"></p>
        </details>

        <div class="lfa-save-bar"><?php submit_button('Save Role', 'primary', 'submit', false); ?> <span class="lfa-unsaved" hidden>Unsaved changes</span></div>
    </form>

    <?php if (isset($backups[$slug])) : ?>
    <div class="lfa-panel lfa-narrow">
        <h2>Undo all changes</h2>
        <p>Put this role back to how it was before Leapfrog first changed it, on <?php echo esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), (int) $backups[$slug]['time'])); ?>.</p>
        <form action="<?php echo esc_url($post); ?>" method="post">
            <input type="hidden" name="action" value="leapfrog_admin_role_restore">
            <input type="hidden" name="role" value="<?php echo esc_attr($slug); ?>">
            <?php wp_nonce_field('leapfrog_admin_role_restore', '_lfa_nonce', false); ?>
            <button type="submit" class="button" data-lfa-confirm="Put <?php echo esc_attr($name); ?> back to how it was before Leapfrog first changed it?">Undo All Changes</button>
        </form>
    </div>
    <?php endif; ?>

    <div class="lfa-panel lfa-narrow">
        <h2>Delete this role</h2>
        <?php if (role_deletable($slug)) : ?>
        <form action="<?php echo esc_url($post); ?>" method="post">
            <input type="hidden" name="action" value="leapfrog_admin_role_delete">
            <input type="hidden" name="role" value="<?php echo esc_attr($slug); ?>">
            <?php wp_nonce_field('leapfrog_admin_role_delete', '_lfa_nonce', false); ?>
            <p><label for="lfa-move-to">People with this role get</label><br>
                <select id="lfa-move-to" name="move_to">
                    <?php foreach (wp_roles()->roles as $other => $otherRole) : if ($other === $slug) { continue; } ?>
                        <option value="<?php echo esc_attr((string) $other); ?>" <?php selected($other, get_option('default_role')); ?>><?php echo esc_html(translate_user_role($otherRole['name'])); ?></option>
                    <?php endforeach; ?>
                </select></p>
            <p class="description">People who also have another role just lose this one.</p>
            <button type="submit" class="button button-link-delete" data-lfa-confirm="Delete the <?php echo esc_attr($name); ?> role? This can’t be undone.">Delete Role</button>
        </form>
        <?php else : ?>
        <p class="description">This role can’t be deleted: it is built into WordPress, it is the role new users get, or you have it yourself.</p>
        <?php endif; ?>
    </div>
    <?php
}
