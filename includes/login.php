<?php
namespace Leapfrog\Admin;

if (!defined('ABSPATH')) { exit; }

/**
 * The Login page feature: your logo, background and button colour on
 * wp-login.php (blank colours follow the Leapfrog admin colours), the logo links
 * to the site, and where people land after logging in and out, for everyone or
 * per role.
 */

const OPTION_LOGIN = 'leapfrog_admin_login';

function login_boot(): void
{
    add_action('login_enqueue_scripts', __NAMESPACE__ . '\\login_styles');
    add_filter('login_headerurl', static function () { return home_url('/'); });
    add_filter('login_headertext', static function () { return get_bloginfo('name'); });
    add_filter('login_redirect', __NAMESPACE__ . '\\login_redirect', 99, 3);
    add_filter('logout_redirect', __NAMESPACE__ . '\\logout_redirect', 99, 3);
    add_action('admin_post_leapfrog_admin_login', __NAMESPACE__ . '\\save_login');
    add_action('admin_enqueue_scripts', __NAMESPACE__ . '\\login_settings_assets');
}

function login_settings(): array
{
    $saved = get_option(OPTION_LOGIN, []);
    $saved = is_array($saved) ? $saved : [];
    return [
        'logo' => absint($saved['logo'] ?? 0),
        'logo_width' => min(320, max(40, (int) ($saved['logo_width'] ?? 120))),
        'background' => normalise_colour($saved['background'] ?? '') ?: '',
        'background_image' => absint($saved['background_image'] ?? 0),
        'button' => normalise_colour($saved['button'] ?? '') ?: '',
        'redirects' => is_array($saved['redirects'] ?? null) ? $saved['redirects'] : [],
    ];
}

/** The admin colour scheme's colours, when the Colours feature is on. */
function scheme_colour(string $key): string
{
    return feature_enabled('colours') ? (string) (colours()[$key] ?? '') : '';
}

function login_styles(): void
{
    $s = login_settings();
    $background = $s['background'] ?: scheme_colour('canvas');
    $button = $s['button'] ?: scheme_colour('action');
    $css = '';
    if ($background !== '' || $s['background_image']) {
        $css .= 'body.login{' . ($background !== '' ? 'background-color:' . $background . ';' : '');
        $image = $s['background_image'] ? wp_get_attachment_image_url($s['background_image'], 'full') : '';
        if ($image) {
            $css .= 'background-image:url("' . esc_url($image) . '");background-size:cover;background-position:center;background-attachment:fixed;';
        }
        $css .= '}';
        if ($background !== '' && !$image) {
            $ink = ink($background);
            $css .= '.login #backtoblog a,.login #nav a,.login .privacy-policy-link,.login .language-switcher label .dashicons{color:' . $ink . ';}';
        }
    }
    if ($s['logo']) {
        $src = wp_get_attachment_image_src($s['logo'], 'medium');
        if ($src) {
            $height = $src[1] ? (int) round($s['logo_width'] * $src[2] / $src[1]) : $s['logo_width'];
            $css .= '.login h1 a{background-image:url("' . esc_url($src[0]) . '");background-size:contain;background-position:center;width:' . $s['logo_width'] . 'px;height:' . $height . 'px;max-width:100%;}';
        }
    }
    if ($button !== '') {
        $css .= '.wp-core-ui .button-primary{background:' . $button . ';border-color:' . $button . ';color:' . ink($button) . ';}'
            . '.wp-core-ui .button-primary:hover,.wp-core-ui .button-primary:focus{background:' . shade($button, -0.12) . ';border-color:' . shade($button, -0.12) . ';color:' . ink($button) . ';}'
            . '.login input[type=text]:focus,.login input[type=password]:focus,.login input[type=email]:focus,.login input[type=checkbox]:focus{border-color:' . $button . ';box-shadow:0 0 0 1px ' . $button . ';}'
            . '.login .button.wp-hide-pw .dashicons,.login .message a,.login #login_error a{color:' . $button . ';}';
    }
    if ($css !== '') {
        wp_register_style('lfa-login', false, [], VERSION);
        wp_enqueue_style('lfa-login');
        wp_add_inline_style('lfa-login', $css);
    }
}

/* -------------------------------------------------------------------------
 * Redirects
 * ---------------------------------------------------------------------- */

/** Where this person goes: their first role with a setting, else Everyone. */
function redirect_for(\WP_User $user, string $when): string
{
    $redirects = login_settings()['redirects'];
    $target = '';
    foreach ((array) $user->roles as $role) {
        if (($redirects['roles'][$role][$when] ?? '') !== '') { $target = $redirects['roles'][$role][$when]; break; }
    }
    if ($target === '') { $target = (string) ($redirects['everyone'][$when] ?? ''); }
    if ($target === '') { return ''; }
    $url = strpos($target, '/') === 0 ? home_url($target) : $target;
    // Only this site (and hosts WordPress allows); anything else is ignored.
    return wp_validate_redirect($url, '');
}

function login_redirect($redirectTo, $requested, $user)
{
    if (!($user instanceof \WP_User) || !$user->exists()) { return $redirectTo; }
    // Someone who was sent to log in on the way to a page goes to that page.
    if ($requested !== '' && $requested !== admin_url()) { return $redirectTo; }
    return redirect_for($user, 'login') ?: $redirectTo;
}

function logout_redirect($redirectTo, $requested, $user)
{
    if ($requested !== '' || !($user instanceof \WP_User) || !$user->exists()) { return $redirectTo; }
    return redirect_for($user, 'logout') ?: $redirectTo;
}

/* -------------------------------------------------------------------------
 * Settings
 * ---------------------------------------------------------------------- */

function clean_redirect($value): ?string
{
    $value = is_string($value) ? trim($value) : '';
    if ($value === '') { return ''; }
    if (strpos($value, '/') === 0) {
        return preg_match('#^/[^\s<>"\'\\\\]*$#D', $value) ? $value : null;
    }
    $url = esc_url_raw($value, ['http', 'https']);
    return $url !== '' ? $url : null;
}

function save_login(): void
{
    authorise('leapfrog_admin_login');
    $post = wp_unslash($_POST);
    $media = static function ($id) { $id = absint($id); return $id && wp_attachment_is_image($id) ? $id : 0; };
    $settings = [
        'logo' => $media($post['logo'] ?? 0),
        'logo_width' => min(320, max(40, (int) ($post['logo_width'] ?? 120))),
        'background' => normalise_colour((string) ($post['background'] ?? '')) ?: '',
        'background_image' => $media($post['background_image'] ?? 0),
        'button' => normalise_colour((string) ($post['button'] ?? '')) ?: '',
        'redirects' => ['everyone' => [], 'roles' => []],
    ];
    $rows = is_array($post['redirects'] ?? null) ? $post['redirects'] : [];
    foreach ($rows as $key => $row) {
        $key = (string) $key;
        $isRole = strpos($key, 'role:') === 0 && get_role(substr($key, 5));
        if ($key !== 'everyone' && !$isRole) { continue; }
        $clean = [];
        foreach (['login', 'logout'] as $when) {
            $target = clean_redirect($row[$when] ?? '');
            if ($target === null) {
                return_to_page('login_redirect', ['tab' => 'login']);
            }
            if ($target !== '') { $clean[$when] = $target; }
        }
        if (!$clean) { continue; }
        if ($isRole) { $settings['redirects']['roles'][substr($key, 5)] = $clean; } else { $settings['redirects']['everyone'] = $clean; }
    }
    update_option(OPTION_LOGIN, $settings);
    return_to_page('login_saved', ['tab' => 'login']);
}

function login_settings_assets(string $hook): void
{
    if ($hook !== PAGE_HOOK || current_tab() !== 'login') { return; }
    wp_enqueue_style('wp-color-picker');
    if (current_user_can('upload_files')) { wp_enqueue_media(); }
    wp_enqueue_script('lfa-media-field', asset_url('assets/media-field.js'), ['jquery', 'wp-color-picker', 'lfa-page'], asset_version('assets/media-field.js'), true);
}

function render_media_field(string $name, string $label, int $id, string $help): void
{
    $url = $id ? (string) wp_get_attachment_image_url($id, 'medium') : '';
    ?>
    <tr>
        <th scope="row"><?php echo esc_html($label); ?></th>
        <td class="lfa-media-field" data-lfa-media-title="<?php echo esc_attr($label); ?>">
            <input type="hidden" name="<?php echo esc_attr($name); ?>" value="<?php echo $url ? (int) $id : 0; ?>">
            <img class="lfa-media-preview" src="<?php echo esc_url($url); ?>" alt="" <?php if (!$url) { echo 'hidden'; } ?>>
            <p>
                <?php if (current_user_can('upload_files')) : ?><button type="button" class="button" data-lfa-media-choose>Choose Image</button><?php endif; ?>
                <button type="button" class="button-link" data-lfa-media-clear <?php if (!$url) { echo 'hidden'; } ?>>Remove</button>
            </p>
            <p class="description"><?php echo esc_html($help); ?></p>
        </td>
    </tr>
    <?php
}

function render_login_tab(): void
{
    $s = login_settings();
    $schemeCanvas = scheme_colour('canvas');
    $schemeAction = scheme_colour('action');
    ?>
    <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" class="lfa-track">
        <input type="hidden" name="action" value="leapfrog_admin_login">
        <?php wp_nonce_field('leapfrog_admin_login', '_lfa_nonce', false); ?>
        <p class="lfa-narrow-text">How WordPress’s log-in page looks. The logo links to your site. <a href="<?php echo esc_url(wp_login_url()); ?>" target="_blank" rel="noopener">Open the log-in page in a new tab</a> to check it after saving.</p>
        <table class="form-table" role="presentation">
            <?php render_media_field('logo', 'Logo', $s['logo'], 'Shown above the log-in form instead of the WordPress logo.'); ?>
            <tr>
                <th scope="row"><label for="lfa-logo-width">Logo width</label></th>
                <td><input type="number" id="lfa-logo-width" name="logo_width" min="40" max="320" step="1" value="<?php echo (int) $s['logo_width']; ?>"> px <p class="description">40–320 px. The height follows the image.</p></td>
            </tr>
            <tr>
                <th scope="row"><label for="lfa-login-background">Background colour</label></th>
                <td><input type="text" class="lfa-colour-input" id="lfa-login-background" name="background" value="<?php echo esc_attr($s['background']); ?>" data-default-color="">
                    <p class="description"><?php echo $schemeCanvas ? 'Leave blank to use the page background from your admin colours (' . esc_html($schemeCanvas) . ').' : 'Leave blank for WordPress’s own.'; ?></p></td>
            </tr>
            <?php render_media_field('background_image', 'Background image', $s['background_image'], 'Optional. Covers the whole page behind the form.'); ?>
            <tr>
                <th scope="row"><label for="lfa-login-button">Button colour</label></th>
                <td><input type="text" class="lfa-colour-input" id="lfa-login-button" name="button" value="<?php echo esc_attr($s['button']); ?>" data-default-color="">
                    <p class="description"><?php echo $schemeAction ? 'Leave blank to use the button colour from your admin colours (' . esc_html($schemeAction) . ').' : 'Leave blank for WordPress’s own.'; ?> Text on the button turns light or dark to stay readable.</p></td>
            </tr>
        </table>

        <h2>After logging in and out</h2>
        <p class="description lfa-narrow-text">Where people land. Start with / for a page on this site (for example /my-account/), or give a full address on this site. Leave blank for WordPress’s usual place. Someone sent to log in on the way to a page still goes to that page. People with several roles follow their first role that has a setting.</p>
        <table class="widefat striped lfa-bar-table">
            <thead><tr><th scope="col">Who</th><th scope="col">After logging in</th><th scope="col">After logging out</th></tr></thead>
            <tbody>
            <?php
            $rows = ['everyone' => 'Everyone'];
            foreach (wp_roles()->roles as $slug => $role) { $rows['role:' . $slug] = translate_user_role($role['name']); }
            foreach ($rows as $key => $label) :
                $values = $key === 'everyone' ? ($s['redirects']['everyone'] ?? []) : ($s['redirects']['roles'][substr($key, 5)] ?? []);
                ?>
                <tr>
                    <td><?php echo esc_html($label); ?></td>
                    <td><input type="text" class="regular-text code" name="redirects[<?php echo esc_attr($key); ?>][login]" value="<?php echo esc_attr($values['login'] ?? ''); ?>" placeholder="<?php echo $key === 'everyone' ? 'Dashboard' : 'As Everyone'; ?>" aria-label="<?php echo esc_attr($label . ': after logging in'); ?>"></td>
                    <td><input type="text" class="regular-text code" name="redirects[<?php echo esc_attr($key); ?>][logout]" value="<?php echo esc_attr($values['logout'] ?? ''); ?>" placeholder="<?php echo $key === 'everyone' ? 'Log-in page' : 'As Everyone'; ?>" aria-label="<?php echo esc_attr($label . ': after logging out'); ?>"></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <div class="lfa-save-bar"><?php submit_button('Save Changes', 'primary', 'submit', false); ?> <span class="lfa-unsaved" hidden>Unsaved changes</span></div>
    </form>
    <?php
}
