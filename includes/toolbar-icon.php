<?php
namespace Leapfrog\Admin;

if (!defined('ABSPATH')) { exit; }

/** The Toolbar icon feature. */
function toolbar_icon_boot(): void
{
    add_action('admin_post_leapfrog_admin_toolbar_icon', __NAMESPACE__ . '\\save_toolbar_icon');
    add_action('wp_before_admin_bar_render', __NAMESPACE__ . '\\replace_toolbar_icon', PHP_INT_MAX);
    add_action('admin_enqueue_scripts', __NAMESPACE__ . '\\toolbar_icon_assets');
    add_action('wp_enqueue_scripts', __NAMESPACE__ . '\\toolbar_icon_assets');
    add_action('admin_enqueue_scripts', __NAMESPACE__ . '\\toolbar_icon_settings_assets');
}

/** Use an existing Media Library image; no favicon filters or remote lookups. */
function toolbar_icon_url(int $id = 0): string
{
    $id = $id ?: absint(get_option(OPTION_TOOLBAR_ICON, 0));
    if (!$id || get_post_status($id) === 'trash' || !wp_attachment_is_image($id)) { return ''; }
    $url = wp_get_attachment_image_url($id, 'thumbnail');
    return $url ? esc_url_raw($url) : '';
}

function save_toolbar_icon(): void
{
    authorise('leapfrog_admin_toolbar_icon');
    if (isset($_POST['remove_toolbar_icon'])) {
        delete_option(OPTION_TOOLBAR_ICON);
        return_to_page('icon_removed', ['tab' => 'toolbar-icon']);
    }
    $value = $_POST['toolbar_icon_id'] ?? null;
    if (!is_string($value) || !preg_match('/^[1-9]\d{0,9}$/D', $value) || !toolbar_icon_url((int) $value)) {
        wp_die('Choose an image from this site’s Media Library. No settings were changed.', '', ['response' => 400]);
    }
    update_option(OPTION_TOOLBAR_ICON, (int) $value);
    return_to_page('icon_saved', ['tab' => 'toolbar-icon']);
}

/** Preserve the site's label, link, permissions and all child menu items. */
function replace_toolbar_icon(): void
{
    global $wp_admin_bar;
    if (!is_admin_bar_showing() || !($wp_admin_bar instanceof \WP_Admin_Bar)) { return; }
    $url = toolbar_icon_url();
    $node = $wp_admin_bar->get_node('site-name');
    if (!$url || !$node) { return; }
    $tags = new \WP_HTML_Tag_Processor($node->title);
    $replaced = false;
    while ($tags->next_tag(['tag_name' => 'IMG'])) {
        if (!$tags->has_class('site-icon')) { continue; }
        $tags->set_attribute('src', $url);
        $tags->remove_attribute('srcset');
        $tags->remove_attribute('sizes');
        $tags->set_attribute('alt', '');
        $tags->set_attribute('width', '20');
        $tags->set_attribute('height', '20');
        $tags->add_class('lfa-toolbar-icon');
        $replaced = true;
        break;
    }
    $node->title = $replaced ? $tags->get_updated_html() : '<img class="site-icon lfa-toolbar-icon" src="' . esc_url($url) . '" alt="" width="20" height="20">' . $node->title;
    $node->meta = is_array($node->meta) ? $node->meta : [];
    $classes = preg_split('/\s+/', trim(($node->meta['class'] ?? '') . ' has-site-icon lfa-custom-toolbar-icon'));
    $node->meta['class'] = implode(' ', array_unique($classes));
    $wp_admin_bar->add_node((array) $node);
}

function toolbar_icon_assets(): void
{
    if (!is_admin_bar_showing() || !toolbar_icon_url()) { return; }
    wp_enqueue_style('lfa-toolbar-icon', asset_url('assets/toolbar-icon.css'), [], asset_version('assets/toolbar-icon.css'));
}

function toolbar_icon_settings_assets(string $hook): void
{
    if ($hook !== PAGE_HOOK || current_tab() !== 'toolbar-icon' || !current_user_can('upload_files')) {
        return;
    }
    wp_enqueue_media();
    wp_enqueue_script('lfa-toolbar-icon-settings', asset_url('assets/toolbar-icon-settings.js'), ['media-views'], asset_version('assets/toolbar-icon-settings.js'), true);
}

function render_toolbar_icon_tab(): void
{
    $id = absint(get_option(OPTION_TOOLBAR_ICON, 0));
    $url = toolbar_icon_url($id);
    ?>
    <div class="lfa-panel lfa-narrow" id="lfa-toolbar-icon-panel">
        <p>Choose an image for the icon beside the site name in the WordPress toolbar. A light icon with a transparent background works well on a dark toolbar.</p>
        <p>This is shared by all users and appears in the toolbar in the dashboard and when viewing the site. Your browser favicon stays as it is.</p>
        <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" id="lfa-toolbar-icon-form">
            <input type="hidden" name="action" value="leapfrog_admin_toolbar_icon">
            <?php wp_nonce_field('leapfrog_admin_toolbar_icon', '_lfa_nonce'); ?>
            <input type="hidden" id="lfa-toolbar-icon-id" name="toolbar_icon_id" value="<?php echo $url ? (int) $id : 0; ?>">
            <div class="lfa-toolbar-icon-sample"><img id="lfa-toolbar-icon-sample" <?php if ($url) { echo 'src="' . esc_url($url) . '"'; } ?> alt="Selected toolbar icon" <?php if (!$url) { echo 'hidden'; } ?>><span id="lfa-toolbar-icon-default" <?php if ($url) { echo 'hidden'; } ?>>Default site icon</span></div>
            <p id="lfa-toolbar-icon-status" role="status"><?php echo $url ? 'Saved toolbar icon.' : 'Currently using the default site icon.'; ?></p>
            <?php if (current_user_can('upload_files')) : ?>
            <p><button type="button" class="button" id="lfa-choose-toolbar-icon">Choose Image</button></p>
            <button type="submit" class="button button-primary" id="lfa-save-toolbar-icon" <?php disabled(!$url); ?>>Save Toolbar Icon</button>
            <?php else : ?><p>You need permission to upload files to choose an image.</p><?php endif; ?>
            <button type="submit" class="button" name="remove_toolbar_icon" value="1">Use Default Icon</button>
        </form>
        <p class="description">This image is kept separately from saved palettes and colour exports. Select it independently on each site.</p>
    </div>
    <?php
}
