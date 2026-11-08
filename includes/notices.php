<?php
namespace Leapfrog\Admin;

if (!defined('ABSPATH')) { exit; }

/**
 * The Notices feature: gather admin notices behind one Notices button in the
 * toolbar instead of across the top of every screen, and hide the Help tab, for
 * everyone or a role (layers.php, without the person layer).
 *
 * Messages WordPress shows straight after you do something ("Post updated",
 * "Settings saved", form errors) stay where they are; the rest go into the panel.
 */

const OPTION_NOTICES = 'leapfrog_admin_notices';

function notices_boot(): void
{
    add_filter('admin_body_class', __NAMESPACE__ . '\\notices_body_class');
    add_action('admin_enqueue_scripts', __NAMESPACE__ . '\\notices_assets');
    // After "Howdy" (priority 7): items on the right side line up from the right.
    add_action('admin_bar_menu', __NAMESPACE__ . '\\notices_bar_node', 9);
    add_action('admin_head', __NAMESPACE__ . '\\notices_help_tab');
    add_action('admin_post_leapfrog_admin_notices', __NAMESPACE__ . '\\save_notices');
}

/** ['collect' => bool, 'hide_help' => bool]: the most specific setting wins. */
function merge_notices(array $stack): array
{
    $merged = ['collect' => false, 'hide_help' => false];
    foreach (layer_list($stack) as $layer) {
        foreach (array_keys($merged) as $key) {
            if (isset($layer[$key]) && is_bool($layer[$key])) { $merged[$key] = $layer[$key]; }
        }
    }
    return $merged;
}

function my_notices(): array
{
    static $merged = null;
    if ($merged === null) {
        $merged = merge_notices(layers_for(layers_get(OPTION_NOTICES), wp_get_current_user()));
    }
    return $merged;
}

function notices_body_class(string $classes): string
{
    return my_notices()['collect'] ? $classes . ' lfa-collect-notices' : $classes;
}

function notices_assets(): void
{
    if (!my_notices()['collect']) { return; }
    wp_enqueue_style('lfa-notices', asset_url('assets/notices.css'), [], asset_version('assets/notices.css'));
    wp_enqueue_script('lfa-notices', asset_url('assets/notices.js'), ['jquery', 'common'], asset_version('assets/notices.js'), true);
}

/** The Notices button, on the right of the toolbar; notices.js fills in the count. */
function notices_bar_node(\WP_Admin_Bar $bar): void
{
    if (!is_admin() || !my_notices()['collect']) { return; }
    $bar->add_node([
        'parent' => 'top-secondary',
        'id' => 'lfa-notices',
        'title' => '<span class="ab-icon dashicons dashicons-megaphone" aria-hidden="true"></span><span class="ab-label">Notices <span class="lfa-notices-count">0</span></span>',
        'href' => '#lfa-notices-panel',
        'meta' => ['class' => 'lfa-notices-empty', 'title' => 'Notices'],
    ]);
}

function notices_help_tab(): void
{
    if (!my_notices()['hide_help']) { return; }
    $screen = get_current_screen();
    if ($screen) { $screen->remove_help_tabs(); }
}

function save_notices(): void
{
    authorise('leapfrog_admin_notices');
    $layer = parse_layer(isset($_POST['layer']) && is_string($_POST['layer']) ? sanitize_text_field(wp_unslash($_POST['layer'])) : '', false);
    $args = ['tab' => 'notices', 'layer' => layer_param($layer)];
    if (!empty($_POST['lfa_reset'])) {
        layers_set(OPTION_NOTICES, $layer, []);
        return_to_page('notices_reset', $args);
    }
    $data = [];
    foreach (['collect', 'hide_help'] as $key) {
        $value = isset($_POST[$key]) && is_string($_POST[$key]) ? $_POST[$key] : '';
        if ($value === '1') { $data[$key] = true; } elseif ($value === '0' && $layer['type'] !== 'everyone') { $data[$key] = false; }
    }
    layers_set(OPTION_NOTICES, $layer, $data);
    return_to_page('notices_saved', $args);
}

function render_notice_setting(string $key, string $label, string $help, array $data, array $inherited, bool $isEveryone): void
{
    $own = isset($data[$key]) && is_bool($data[$key]) ? $data[$key] : null;
    ?>
    <tr>
        <th scope="row"><?php echo esc_html($label); ?></th>
        <td>
            <?php if ($isEveryone) : ?>
                <label><input type="checkbox" class="lfa-toggle" name="<?php echo esc_attr($key); ?>" value="1" <?php checked($own === true); ?>> On</label>
            <?php else : ?>
                <select name="<?php echo esc_attr($key); ?>" aria-label="<?php echo esc_attr($label); ?>">
                    <option value="" <?php selected($own === null); ?>>As above (<?php echo $inherited[$key] ? 'on' : 'off'; ?>)</option>
                    <option value="1" <?php selected($own === true); ?>>On</option>
                    <option value="0" <?php selected($own === false); ?>>Off</option>
                </select>
            <?php endif; ?>
            <p class="description"><?php echo esc_html($help); ?></p>
        </td>
    </tr>
    <?php
}

function render_notices_tab(): void
{
    $layer = current_layer(false);
    $all = layers_get(OPTION_NOTICES);
    $data = layer_data($all, $layer);
    $isEveryone = $layer['type'] === 'everyone';
    $inherited = merge_notices(layers_for($all, null, $layer));
    render_layer_picker('notices', OPTION_NOTICES, $layer, false);
    ?>
    <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" class="lfa-track">
        <input type="hidden" name="action" value="leapfrog_admin_notices">
        <input type="hidden" name="layer" value="<?php echo esc_attr(layer_param($layer)); ?>">
        <?php wp_nonce_field('leapfrog_admin_notices', '_lfa_nonce', false); ?>
        <table class="form-table" role="presentation">
            <?php
            render_notice_setting('collect', 'Gather notices', 'Plugin and WordPress notices go behind a Notices button on the right of the toolbar, with a count (red when one is an error). Messages straight after you save or update something still show on the page.', $data, $inherited, $isEveryone);
            render_notice_setting('hide_help', 'Hide the Help tab', 'Removes the Help tab at the top right of admin screens. Screen Options stays.', $data, $inherited, $isEveryone);
            ?>
        </table>
        <?php render_layer_buttons('notices', $layer, (bool) $data); ?>
    </form>
    <?php
}
