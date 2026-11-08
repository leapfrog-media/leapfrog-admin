<?php
namespace Leapfrog\Admin;

if (!defined('ABSPATH')) { exit; }

/**
 * The Dashboard feature: hide WordPress's and plugins' dashboard widgets and the
 * welcome panel, and add a Leapfrog help box with your own text, for everyone or
 * a role (layers.php, without the person layer).
 *
 * The editor lists widgets Leapfrog has seen on the dashboard (option
 * leapfrog_admin_dashboard_seen), because plugins add them only there.
 */

const OPTION_DASHBOARD = 'leapfrog_admin_dashboard';
const OPTION_DASHBOARD_SEEN = 'leapfrog_admin_dashboard_seen';
const WELCOME_PANEL = 'welcome-panel';
const HELP_BOX = 'lfa_help';

function dashboard_boot(): void
{
    add_action('wp_dashboard_setup', __NAMESPACE__ . '\\dashboard_apply', PHP_INT_MAX);
    add_action('admin_post_leapfrog_admin_dashboard', __NAMESPACE__ . '\\save_dashboard');
}

function merge_dashboard(array $stack): array
{
    $merged = ['hidden' => merge_hidden($stack, 'items'), 'help' => null];
    foreach (layer_list($stack) as $layer) {
        if (($layer['help']['title'] ?? '') !== '' || ($layer['help']['content'] ?? '') !== '') {
            $merged['help'] = $layer['help'];
        }
    }
    return $merged;
}

function dashboard_apply(): void
{
    global $wp_meta_boxes;
    if (current_user_can('manage_options')) {
        record_dashboard_widgets();
    }
    $m = merge_dashboard(layers_for(layers_get(OPTION_DASHBOARD), wp_get_current_user()));
    foreach ((array) ($wp_meta_boxes['dashboard'] ?? []) as $context => $priorities) {
        foreach ((array) $priorities as $boxes) {
            foreach ((array) $boxes as $id => $box) {
                if (!empty($m['hidden'][$id])) {
                    remove_meta_box((string) $id, 'dashboard', (string) $context);
                }
            }
        }
    }
    if (!empty($m['hidden'][WELCOME_PANEL])) {
        remove_action('welcome_panel', 'wp_welcome_panel');
    }
    if ($m['help'] && empty($m['hidden'][HELP_BOX])) {
        $help = $m['help'];
        wp_add_dashboard_widget(HELP_BOX, esc_html(($help['title'] ?? '') !== '' ? $help['title'] : 'Help'), static function () use ($help) {
            echo '<div class="lfa-help-box">' . wp_kses_post(wpautop((string) ($help['content'] ?? ''))) . '</div>';
        }, null, null, 'normal', 'high');
    }
}

/** Remember the dashboard's widgets for the editor. Writes only when something new appears. */
function record_dashboard_widgets(): void
{
    global $wp_meta_boxes;
    $seen = get_option(OPTION_DASHBOARD_SEEN, []);
    $seen = is_array($seen) ? $seen : [];
    $changed = false;
    foreach ((array) ($wp_meta_boxes['dashboard'] ?? []) as $priorities) {
        foreach ((array) $priorities as $boxes) {
            foreach ((array) $boxes as $id => $box) {
                if (!is_array($box) || $id === HELP_BOX || isset($seen[$id])) { continue; }
                // Leave out text that is normally hidden (Quick Draft's "Your Recent Drafts").
                $raw = (string) preg_replace('/<span[^>]*class="[^"]*\b(?:hide-if-js|screen-reader-text)\b[^"]*"[^>]*>.*?<\/span>/s', '', (string) ($box['title'] ?? ''));
                $title = trim(preg_replace('/\s+/', ' ', wp_strip_all_tags($raw)));
                $seen[$id] = $title !== '' ? $title : ucfirst(str_replace(['_', '-'], ' ', (string) $id));
                $changed = true;
            }
        }
    }
    if ($changed && count($seen) <= 200) {
        update_option(OPTION_DASHBOARD_SEEN, $seen, false);
    }
}

function save_dashboard(): void
{
    authorise('leapfrog_admin_dashboard');
    $layer = parse_layer(isset($_POST['layer']) && is_string($_POST['layer']) ? sanitize_text_field(wp_unslash($_POST['layer'])) : '', false);
    $args = ['tab' => 'dashboard', 'layer' => layer_param($layer)];
    if (!empty($_POST['lfa_reset'])) {
        layers_set(OPTION_DASHBOARD, $layer, []);
        return_to_page('dashboard_reset', $args);
    }
    $isEveryone = $layer['type'] === 'everyone';
    $data = ['items' => [], 'help' => []];
    foreach (isset($_POST['hidden']) && is_array($_POST['hidden']) ? wp_unslash($_POST['hidden']) : [] as $id => $value) {
        $hidden = posted_visibility($value, $isEveryone);
        if ($hidden !== null && preg_match('/^[A-Za-z0-9_\-]{1,100}$/D', (string) $id)) {
            $data['items'][(string) $id] = ['hidden' => $hidden];
        }
    }
    $help = isset($_POST['help']) && is_array($_POST['help']) ? wp_unslash($_POST['help']) : [];
    $title = trim(sanitize_text_field((string) ($help['title'] ?? '')));
    $content = trim(wp_kses_post((string) ($help['content'] ?? '')));
    if ($title !== '' || $content !== '') {
        $data['help'] = ['title' => function_exists('mb_substr') ? mb_substr($title, 0, 80) : substr($title, 0, 80), 'content' => substr($content, 0, 20000)];
    }
    layers_set(OPTION_DASHBOARD, $layer, $data);
    return_to_page('dashboard_saved', $args);
}

function render_dashboard_tab(): void
{
    $layer = current_layer(false);
    $all = layers_get(OPTION_DASHBOARD);
    $data = layer_data($all, $layer);
    $isEveryone = $layer['type'] === 'everyone';
    $inherited = merge_dashboard(layers_for($all, null, $layer));
    $own = (array) ($data['items'] ?? []);
    $seen = get_option(OPTION_DASHBOARD_SEEN, []);
    $rows = [WELCOME_PANEL => 'Welcome panel', HELP_BOX => 'Leapfrog help box'] + (is_array($seen) ? $seen : []);
    $help = (array) ($data['help'] ?? []);
    render_layer_picker('dashboard', OPTION_DASHBOARD, $layer, false);
    ?>
    <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" class="lfa-track">
        <input type="hidden" name="action" value="leapfrog_admin_dashboard">
        <input type="hidden" name="layer" value="<?php echo esc_attr(layer_param($layer)); ?>">
        <?php wp_nonce_field('leapfrog_admin_dashboard', '_lfa_nonce', false); ?>

        <h2>Widgets</h2>
        <p class="description lfa-narrow-text">Widgets appear here once Leapfrog has seen them on the <a href="<?php echo esc_url(admin_url()); ?>">dashboard</a>. People can still rearrange or close the widgets they see.</p>
        <table class="widefat striped lfa-bar-table">
            <thead><tr><th scope="col">Widget</th><th scope="col">Show</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $id => $title) : ?>
                <tr>
                    <td><?php echo esc_html($title); ?> <code><?php echo esc_html((string) $id); ?></code></td>
                    <td><?php render_visibility_select('hidden[' . $id . ']', isset($own[$id]['hidden']) ? (bool) $own[$id]['hidden'] : null, !empty($inherited['hidden'][$id]), $isEveryone, 'Show or hide ' . $title); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <h2>Leapfrog help box</h2>
        <p class="description lfa-narrow-text"><?php echo $isEveryone
            ? 'A box at the top of the dashboard with your own text: how to get help, who to contact, links to guides. Leave it empty for no box.'
            : 'Text for this role’s help box. Leave it empty to use the help box from Everyone' . ($inherited['help'] ? ' (“' . esc_html($inherited['help']['title'] ?: 'Help') . '”)' : '') . '.'; ?></p>
        <div class="lfa-panel lfa-help-editor">
            <p><label for="lfa-help-title"><strong>Title</strong></label><br><input type="text" id="lfa-help-title" name="help[title]" value="<?php echo esc_attr($help['title'] ?? ''); ?>" maxlength="80" class="regular-text" placeholder="Help"></p>
            <?php wp_editor((string) ($help['content'] ?? ''), 'lfahelpcontent', ['textarea_name' => 'help[content]', 'media_buttons' => false, 'teeny' => true, 'textarea_rows' => 8]); ?>
        </div>

        <?php render_layer_buttons('dashboard', $layer, (bool) $data); ?>
    </form>
    <?php
}
