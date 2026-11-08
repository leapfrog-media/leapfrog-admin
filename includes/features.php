<?php
namespace Leapfrog\Admin;

if (!defined('ABSPATH')) { exit; }

/**
 * Every switchable feature. Each one is a tab on Leapfrog > Admin; its files are
 * only loaded, and its boot function only runs, while it is switched on.
 */
function features(): array
{
    return [
        'colours' => [
            'label' => 'Colours',
            'description' => 'The Leapfrog admin colour scheme: colours, sizing, saved palettes, import and export.',
            'off' => 'People who chose the Leapfrog scheme see WordPress’s default colours. Your colours and palettes are kept.',
            'files' => ['colours.php', 'colours-settings.php'],
            'boot' => __NAMESPACE__ . '\\colours_boot',
            'render' => __NAMESPACE__ . '\\render_colours_tab',
        ],
        'toolbar-icon' => [
            'label' => 'Toolbar icon',
            'description' => 'Your own image beside the site name in the WordPress toolbar.',
            'off' => 'The toolbar shows the site icon again. Your chosen image is kept.',
            'files' => ['toolbar-icon.php'],
            'boot' => __NAMESPACE__ . '\\toolbar_icon_boot',
            'render' => __NAMESPACE__ . '\\render_toolbar_icon_tab',
        ],
        'admin-menu' => [
            'label' => 'Admin menu',
            'description' => 'Reorder, rename, hide and re-icon menu items, and add separators, for everyone, a role or one person.',
            'off' => 'Everyone sees WordPress’s own menu again. Your changes are kept.',
            'files' => ['layers.php', 'admin-menu.php'],
            'boot' => __NAMESPACE__ . '\\admin_menu_boot',
            'render' => __NAMESPACE__ . '\\render_admin_menu_tab',
        ],
        'admin-bar' => [
            'label' => 'Admin bar',
            'description' => 'Hide toolbar items, add your own links and a Bricks menu, for everyone, a role or one person.',
            'off' => 'Everyone sees WordPress’s own toolbar again. Your changes are kept.',
            'files' => ['layers.php', 'admin-bar.php'],
            'boot' => __NAMESPACE__ . '\\admin_bar_boot',
            'render' => __NAMESPACE__ . '\\render_admin_bar_tab',
        ],
        'dashboard' => [
            'label' => 'Dashboard',
            'description' => 'Hide dashboard widgets and the welcome panel, and add a help box with your own text, for everyone or a role.',
            'off' => 'The dashboard shows WordPress’s own widgets again. Your changes are kept.',
            'files' => ['layers.php', 'dashboard.php'],
            'boot' => __NAMESPACE__ . '\\dashboard_boot',
            'render' => __NAMESPACE__ . '\\render_dashboard_tab',
        ],
        'notices' => [
            'label' => 'Notices',
            'description' => 'Gather admin notices behind one Notices button in the toolbar, and hide the Help tab, for everyone or a role.',
            'off' => 'Notices show across the top of screens again. Your choices are kept.',
            'files' => ['layers.php', 'notices.php'],
            'boot' => __NAMESPACE__ . '\\notices_boot',
            'render' => __NAMESPACE__ . '\\render_notices_tab',
        ],
        'login' => [
            'label' => 'Login page',
            'description' => 'Your logo, background and button colour on the log-in page, and where people land after logging in and out.',
            'off' => 'The log-in page and redirects go back to WordPress’s own. Your settings are kept.',
            'files' => ['colours.php', 'colours-settings.php', 'login.php'],
            'boot' => __NAMESPACE__ . '\\login_boot',
            'render' => __NAMESPACE__ . '\\render_login_tab',
        ],
        'roles' => [
            'label' => 'User roles',
            'description' => 'Create, copy, rename and delete roles, and choose what each role is allowed to do.',
            'off' => 'Only the editor is hidden. Roles and permissions stay exactly as you set them, because WordPress stores them itself.',
            'files' => ['roles.php'],
            'boot' => __NAMESPACE__ . '\\roles_boot',
            'render' => __NAMESPACE__ . '\\render_roles_tab',
        ],
    ];
}

/** Features planned for later versions, listed on the Features tab. */
function coming_features(): array
{
    return [
        '2.4' => 'List columns and filters, page tree and profile pictures',
    ];
}

function disabled_features(): array
{
    $disabled = get_option(OPTION_DISABLED, []);
    return is_array($disabled) ? $disabled : [];
}

function feature_enabled(string $id): bool
{
    return isset(features()[$id]) && !in_array($id, disabled_features(), true);
}

function save_features(): void
{
    authorise('leapfrog_admin_features');
    $on = isset($_POST['features']) && is_array($_POST['features']) ? array_map('sanitize_key', wp_unslash($_POST['features'])) : [];
    $disabled = array_values(array_diff(array_keys(features()), $on));
    update_option(OPTION_DISABLED, $disabled);
    return_to_page('features_saved', ['tab' => 'features']);
}
add_action('admin_post_leapfrog_admin_features', __NAMESPACE__ . '\\save_features');

function render_features_tab(): void
{
    ?>
    <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" class="lfa-features">
        <input type="hidden" name="action" value="leapfrog_admin_features">
        <?php wp_nonce_field('leapfrog_admin_features', '_lfa_nonce'); ?>
        <p>Switch off anything a site doesn’t need. A feature that is off adds nothing to the site, and its tab is hidden.</p>
        <table class="widefat striped lfa-feature-table">
            <thead><tr><th scope="col" class="lfa-feature-switch">On</th><th scope="col">Feature</th><th scope="col">When it is off</th></tr></thead>
            <tbody>
            <?php foreach (features() as $id => $feature) : ?>
                <tr>
                    <td class="lfa-feature-switch"><input type="checkbox" class="lfa-toggle" id="lfa-feature-<?php echo esc_attr($id); ?>" name="features[]" value="<?php echo esc_attr($id); ?>" <?php checked(feature_enabled($id)); ?>></td>
                    <td><label for="lfa-feature-<?php echo esc_attr($id); ?>"><strong><?php echo esc_html($feature['label']); ?></strong></label><br><span class="description"><?php echo esc_html($feature['description']); ?></span></td>
                    <td class="description"><?php echo esc_html($feature['off']); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php submit_button('Save Features'); ?>
    </form>
    <h2>Coming next</h2>
    <ul class="lfa-coming">
        <?php foreach (coming_features() as $version => $text) : ?>
            <li><strong><?php echo esc_html($version); ?></strong> <?php echo esc_html($text); ?></li>
        <?php endforeach; ?>
    </ul>
    <?php
}
