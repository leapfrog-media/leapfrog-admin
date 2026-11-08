<?php
namespace Leapfrog\Admin;

if (!defined('ABSPATH')) { exit; }

/**
 * The Profile pictures feature: choose a profile picture from the Media Library
 * on Users > Profile instead of using Gravatar. It is used wherever WordPress
 * shows that person's picture (toolbar, comments, author boxes). People without
 * one keep their Gravatar or the default picture (Settings > Discussion).
 */

const AVATAR_META = 'lfa_avatar';

function avatars_boot(): void
{
    add_filter('pre_get_avatar_data', __NAMESPACE__ . '\\avatar_data', 10, 2);
    add_filter('user_profile_picture_description', __NAMESPACE__ . '\\avatar_field', 10, 2);
    add_action('personal_options_update', __NAMESPACE__ . '\\save_avatar');
    add_action('edit_user_profile_update', __NAMESPACE__ . '\\save_avatar');
    add_action('admin_enqueue_scripts', __NAMESPACE__ . '\\avatar_assets');
}

/** The user an avatar request is for: an ID, user, post author, comment or email. */
function avatar_user_id($idOrEmail): int
{
    if (is_numeric($idOrEmail)) { return (int) $idOrEmail; }
    if ($idOrEmail instanceof \WP_User) { return (int) $idOrEmail->ID; }
    if ($idOrEmail instanceof \WP_Post) { return (int) $idOrEmail->post_author; }
    if ($idOrEmail instanceof \WP_Comment) {
        if ((int) $idOrEmail->user_id) { return (int) $idOrEmail->user_id; }
        $idOrEmail = $idOrEmail->comment_author_email;
    }
    if (is_string($idOrEmail) && is_email($idOrEmail)) {
        $user = get_user_by('email', $idOrEmail);
        return $user ? (int) $user->ID : 0;
    }
    return 0;
}

function avatar_data($args, $idOrEmail)
{
    $userId = avatar_user_id($idOrEmail);
    $attachment = $userId ? (int) get_user_meta($userId, AVATAR_META, true) : 0;
    if (!$attachment || !wp_attachment_is_image($attachment)) { return $args; }
    $size = (int) ($args['size'] ?? 96);
    // The square thumbnail suits avatars; larger pictures use a bigger size.
    $url = wp_get_attachment_image_url($attachment, $size * 2 > 150 ? 'medium' : 'thumbnail');
    if ($url) {
        $args['url'] = $url;
        $args['found_avatar'] = true;
    }
    return $args;
}

function avatar_assets(string $hook): void
{
    if (!in_array($hook, ['profile.php', 'user-edit.php'], true) || !current_user_can('upload_files')) { return; }
    wp_enqueue_media();
    wp_enqueue_script('lfa-avatar', asset_url('assets/avatar.js'), ['media-views'], asset_version('assets/avatar.js'), true);
}

/** Replaces the "change it on Gravatar" line under the profile picture. */
function avatar_field($description, $user)
{
    if (!($user instanceof \WP_User) || !current_user_can('edit_user', $user->ID)) { return $description; }
    $id = (int) get_user_meta($user->ID, AVATAR_META, true);
    $html = '<span class="lfa-avatar-field">';
    $html .= '<input type="hidden" name="lfa_avatar" id="lfa-avatar" value="' . ($id ?: 0) . '">';
    if (current_user_can('upload_files')) {
        $html .= '<button type="button" class="button" id="lfa-avatar-choose">Choose from Media Library</button>';
        $html .= '<button type="button" class="button-link" id="lfa-avatar-clear"' . ($id ? '' : ' hidden') . '>Use Gravatar instead</button><br>';
        $html .= '<span id="lfa-avatar-status">' . ($id ? 'This picture is used wherever your profile picture shows. ' : '') . '</span>';
    } else {
        $html .= 'Ask an administrator to choose a profile picture for you, or: ';
    }
    $html .= '</span>';
    return $html . ($id ? '' : ' ' . $description);
}

function save_avatar(int $userId): void
{
    // WordPress has checked the profile form's own nonce before this runs.
    if (!current_user_can('edit_user', $userId) || !isset($_POST['lfa_avatar'])) { return; }
    $id = absint($_POST['lfa_avatar']);
    if (!$id) {
        delete_user_meta($userId, AVATAR_META);
        return;
    }
    $attachment = get_post($id);
    // An image from the Media Library; without upload rights, only one they uploaded themselves.
    if (!$attachment || !wp_attachment_is_image($id) || (!current_user_can('upload_files') && (int) $attachment->post_author !== get_current_user_id())) { return; }
    update_user_meta($userId, AVATAR_META, $id);
}

function render_avatars_tab(): void
{
    ?>
    <p class="lfa-narrow-text">Everyone can choose a profile picture from the Media Library on <a href="<?php echo esc_url(admin_url('profile.php')); ?>">Users &gt; Profile</a>, and administrators can choose one for anyone on that person’s profile. It shows wherever WordPress shows their picture: the toolbar, comments and author boxes. People without one keep their Gravatar or the default picture set under <a href="<?php echo esc_url(admin_url('options-discussion.php')); ?>">Settings &gt; Discussion</a>, where “Show avatars” must stay on.</p>
    <p class="description">Choosing a picture needs permission to upload files (Authors and above, by default).</p>
    <?php
}
