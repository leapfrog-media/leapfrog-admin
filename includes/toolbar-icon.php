<?php
namespace NativeAdminColours;

if (!defined('ABSPATH')) { exit; }

/** Use an existing Media Library image; no favicon filters or remote lookups. */
function toolbar_icon_url(int $id = 0): string
{
    $id = $id ?: absint(get_option('native_admin_toolbar_icon', 0));
    if (!$id || get_post_status($id) === 'trash' || !wp_attachment_is_image($id)) { return ''; }
    $url = wp_get_attachment_image_url($id, 'thumbnail');
    return $url ? esc_url_raw($url) : '';
}

function save_toolbar_icon(): void
{
    appearance_authorise('native_admin_toolbar_icon');
    if (isset($_POST['remove_toolbar_icon'])) {
        delete_option('native_admin_toolbar_icon');
        appearance_return('icon_removed');
    }
    $value = $_POST['toolbar_icon_id'] ?? null;
    if (!is_string($value) || !preg_match('/^[1-9]\d{0,9}$/D', $value) || !toolbar_icon_url((int) $value)) {
        wp_die('Choose an image from this site’s Media Library. No settings were changed.', '', ['response' => 400]);
    }
    update_option('native_admin_toolbar_icon', (int) $value);
    appearance_return('icon_saved');
}
add_action('admin_post_native_admin_colours_toolbar_icon', __NAMESPACE__ . '\\save_toolbar_icon');

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
        $tags->add_class('nac-toolbar-icon');
        $replaced = true;
        break;
    }
    $node->title = $replaced ? $tags->get_updated_html() : '<img class="site-icon nac-toolbar-icon" src="' . esc_url($url) . '" alt="" width="20" height="20">' . $node->title;
    $node->meta = is_array($node->meta) ? $node->meta : [];
    $classes = preg_split('/\s+/', trim(($node->meta['class'] ?? '') . ' has-site-icon nac-custom-toolbar-icon'));
    $node->meta['class'] = implode(' ', array_unique($classes));
    $wp_admin_bar->add_node((array) $node);
}
add_action('wp_before_admin_bar_render', __NAMESPACE__ . '\\replace_toolbar_icon', PHP_INT_MAX);

function toolbar_icon_assets(): void
{
    if (!is_admin_bar_showing() || !toolbar_icon_url()) { return; }
    wp_enqueue_style('nac-toolbar-icon', plugins_url('assets/toolbar-icon.css', dirname(__DIR__) . '/native-admin-colours.php'), [], '1.3.0');
}
add_action('admin_enqueue_scripts', __NAMESPACE__ . '\\toolbar_icon_assets');
add_action('wp_enqueue_scripts', __NAMESPACE__ . '\\toolbar_icon_assets');

function render_toolbar_icon_settings(): void
{
    $id = absint(get_option('native_admin_toolbar_icon', 0));
    $url = toolbar_icon_url($id);
    ?>
    <details class="nac-panel" id="nac-toolbar-icon-panel">
        <summary>Toolbar icon</summary>
        <p>Choose an image for the icon beside the site name in the WordPress toolbar. A light icon with a transparent background works well on a dark toolbar.</p>
        <p>This is shared by all users and appears in the toolbar in the dashboard and when viewing the site. Your browser favicon stays as it is.</p>
        <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" id="nac-toolbar-icon-form">
            <input type="hidden" name="action" value="native_admin_colours_toolbar_icon">
            <?php wp_nonce_field('native_admin_toolbar_icon', '_nac_nonce'); ?>
            <input type="hidden" id="nac-toolbar-icon-id" name="toolbar_icon_id" value="<?php echo $url ? (int) $id : 0; ?>">
            <div class="nac-toolbar-icon-sample"><img id="nac-toolbar-icon-sample" <?php if ($url) { echo 'src="' . esc_url($url) . '"'; } ?> alt="Selected toolbar icon" <?php if (!$url) { echo 'hidden'; } ?>><span id="nac-toolbar-icon-default" <?php if ($url) { echo 'hidden'; } ?>>Default site icon</span></div>
            <p id="nac-toolbar-icon-status" role="status"><?php echo $url ? 'Saved toolbar icon.' : 'Currently using the default site icon.'; ?></p>
            <?php if (current_user_can('upload_files')) : ?>
            <p><button type="button" class="button" id="nac-choose-toolbar-icon">Choose Image</button></p>
            <button type="submit" class="button button-primary" id="nac-save-toolbar-icon" <?php disabled(!$url); ?>>Save Toolbar Icon</button>
            <?php else : ?><p>You need permission to upload files to choose an image.</p><?php endif; ?>
            <button type="submit" class="button" name="remove_toolbar_icon" value="1">Use Default Icon</button>
        </form>
        <p class="description">This image is kept separately from saved palettes and colour exports. Select it independently on each site.</p>
    </details>
    <?php
}
