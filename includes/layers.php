<?php
namespace Leapfrog\Admin;

if (!defined('ABSPATH')) { exit; }

/**
 * Settings in three layers, used by the admin menu and admin bar editors:
 * Everyone (the whole site), then each role, then one named person. The most
 * specific layer wins. Someone with two roles gets the changes from both; if
 * one role hides an item and the other shows it, it is shown.
 *
 * Stored as one option per feature: ['everyone' => layer, 'roles' => [slug => layer],
 * 'users' => [id => layer]]. A layer holds only what it changes.
 */

/** Hidden settings are true (hide), false (show) or missing (as the layer above). */
function layers_get(string $option): array
{
    $stored = get_option($option, []);
    $stored = is_array($stored) ? $stored : [];
    return [
        'everyone' => is_array($stored['everyone'] ?? null) ? $stored['everyone'] : [],
        'roles' => is_array($stored['roles'] ?? null) ? $stored['roles'] : [],
        'users' => is_array($stored['users'] ?? null) ? $stored['users'] : [],
    ];
}

/** The layer named in the address: everyone, role:slug or user:id. */
function current_layer(bool $allowUsers = true): array
{
    $raw = isset($_GET['layer']) && is_string($_GET['layer']) ? sanitize_text_field(wp_unslash($_GET['layer'])) : '';
    return parse_layer($raw, $allowUsers);
}

function parse_layer(string $raw, bool $allowUsers = true): array
{
    if (!$allowUsers && strpos($raw, 'user:') === 0) {
        return ['type' => 'everyone', 'id' => ''];
    }
    if (strpos($raw, 'role:') === 0) {
        $slug = sanitize_key(substr($raw, 5));
        if ($slug !== '' && get_role($slug)) {
            return ['type' => 'role', 'id' => $slug];
        }
    }
    if (strpos($raw, 'user:') === 0) {
        $id = absint(substr($raw, 5));
        if ($id && get_userdata($id)) {
            return ['type' => 'user', 'id' => (string) $id];
        }
    }
    return ['type' => 'everyone', 'id' => ''];
}

function layer_param(array $layer): string
{
    return $layer['type'] === 'everyone' ? 'everyone' : $layer['type'] . ':' . $layer['id'];
}

function layer_label(array $layer): string
{
    if ($layer['type'] === 'role') {
        return 'Role: ' . role_label($layer['id']);
    }
    if ($layer['type'] === 'user') {
        $user = get_userdata((int) $layer['id']);
        return 'Person: ' . ($user ? $user->display_name : '#' . $layer['id']);
    }
    return 'Everyone';
}

function role_label(string $slug): string
{
    $roles = wp_roles()->roles;
    return isset($roles[$slug]) ? translate_user_role($roles[$slug]['name']) : $slug;
}

function layer_data(array $all, array $layer): array
{
    if ($layer['type'] === 'role') {
        return (array) ($all['roles'][$layer['id']] ?? []);
    }
    if ($layer['type'] === 'user') {
        return (array) ($all['users'][$layer['id']] ?? []);
    }
    return $all['everyone'];
}

function layers_set(string $option, array $layer, array $data): void
{
    $all = layers_get($option);
    $empty = !array_filter($data, static function ($value) { return $value !== [] && $value !== null && $value !== ''; });
    if ($layer['type'] === 'role') {
        if ($empty) { unset($all['roles'][$layer['id']]); } else { $all['roles'][$layer['id']] = $data; }
    } elseif ($layer['type'] === 'user') {
        if ($empty) { unset($all['users'][$layer['id']]); } else { $all['users'][$layer['id']] = $data; }
    } else {
        $all['everyone'] = $empty ? [] : $data;
    }
    update_option($option, $all);
}

/**
 * The layers that apply to a person, most general first:
 * ['everyone' => layer, 'roles' => [layer, …], 'user' => layer|null].
 * $upTo stops below a layer, giving what that layer inherits.
 */
function layers_for(array $all, ?\WP_User $user, ?array $upTo = null): array
{
    $result = ['everyone' => $all['everyone'], 'roles' => [], 'user' => null];
    if ($upTo && $upTo['type'] === 'everyone') {
        $result['everyone'] = [];
        return $result;
    }
    if ($upTo && $upTo['type'] === 'role') {
        return $result;
    }
    if ($user) {
        foreach ((array) $user->roles as $role) {
            if (!empty($all['roles'][$role])) {
                $result['roles'][] = $all['roles'][$role];
            }
        }
        if (!$upTo && !empty($all['users'][(string) $user->ID])) {
            $result['user'] = $all['users'][(string) $user->ID];
        }
    }
    return $result;
}

/**
 * Merge the hidden flags of a key across layers: roles together (show wins),
 * then the person's own layer on top.
 */
function merge_hidden(array $stack, string $key): array
{
    $hidden = [];
    foreach ((array) ($stack['everyone'][$key] ?? []) as $id => $item) {
        if (isset($item['hidden'])) { $hidden[$id] = (bool) $item['hidden']; }
    }
    $roleHidden = [];
    foreach ($stack['roles'] as $layer) {
        foreach ((array) ($layer[$key] ?? []) as $id => $item) {
            if (!isset($item['hidden'])) { continue; }
            $roleHidden[$id] = isset($roleHidden[$id]) ? ($roleHidden[$id] && $item['hidden']) : (bool) $item['hidden'];
        }
    }
    $hidden = $roleHidden + $hidden;
    foreach ((array) ($stack['user'][$key] ?? []) as $id => $item) {
        if (isset($item['hidden'])) { $hidden[$id] = (bool) $item['hidden']; }
    }
    return $hidden;
}

/** Layers in order (everyone, roles…, user) as a flat list. */
function layer_list(array $stack): array
{
    return array_merge([$stack['everyone']], $stack['roles'], $stack['user'] ? [$stack['user']] : []);
}

/** Layer switcher shown above both editors. */
function render_layer_picker(string $tab, string $option, array $layer, bool $allowUsers = true): void
{
    $all = layers_get($option);
    $users = $allowUsers ? get_users(['number' => 500, 'orderby' => 'display_name', 'fields' => ['ID', 'display_name', 'user_login']]) : [];
    $current = layer_param($layer);
    $changed = [];
    if ($all['everyone']) {
        $changed['everyone'] = 'Everyone';
    }
    foreach (array_keys($all['roles']) as $slug) {
        if (get_role((string) $slug)) { $changed['role:' . $slug] = role_label((string) $slug); }
    }
    foreach (array_keys($all['users']) as $id) {
        $user = get_userdata((int) $id);
        if ($user) { $changed['user:' . $id] = $user->display_name; }
    }
    ?>
    <div class="lfa-layer-bar">
        <form method="get" class="lfa-layer-form">
            <input type="hidden" name="page" value="<?php echo esc_attr(PAGE); ?>">
            <input type="hidden" name="tab" value="<?php echo esc_attr($tab); ?>">
            <label for="lfa-layer"><strong>Changes for</strong></label>
            <select id="lfa-layer" name="layer" onchange="this.form.submit()">
                <option value="everyone" <?php selected($current, 'everyone'); ?>>Everyone</option>
                <optgroup label="A role">
                    <?php foreach (wp_roles()->roles as $slug => $role) : ?>
                        <option value="<?php echo esc_attr('role:' . $slug); ?>" <?php selected($current, 'role:' . $slug); ?>><?php echo esc_html(translate_user_role($role['name'])); ?></option>
                    <?php endforeach; ?>
                </optgroup>
                <?php if ($users) : ?>
                <optgroup label="One person">
                    <?php foreach ($users as $user) : ?>
                        <option value="<?php echo esc_attr('user:' . $user->ID); ?>" <?php selected($current, 'user:' . $user->ID); ?>><?php echo esc_html($user->display_name . ($user->display_name !== $user->user_login ? ' (' . $user->user_login . ')' : '')); ?></option>
                    <?php endforeach; ?>
                </optgroup>
                <?php endif; ?>
            </select>
            <noscript><button type="submit" class="button">Show</button></noscript>
        </form>
        <?php if ($changed) : ?>
        <p class="lfa-layer-changed">Has changes:
            <?php foreach ($changed as $param => $label) : ?>
                <a class="lfa-badge<?php echo $param === $current ? ' is-current' : ''; ?>" href="<?php echo esc_url(page_url(['tab' => $tab, 'layer' => $param])); ?>"><?php echo esc_html($label); ?></a>
            <?php endforeach; ?>
        </p>
        <?php endif; ?>
    </div>
    <p class="lfa-layer-help"><?php
    if ($layer['type'] === 'everyone') {
        echo $allowUsers ? 'These changes apply to everyone. Choose a role or a person above to change things just for them.' : 'These changes apply to everyone. Choose a role above to change things just for that role.';
    } elseif ($layer['type'] === 'role') {
        echo 'Changes for everyone with this role, on top of the Everyone changes. Anything left as “As above” follows Everyone.';
    } else {
        echo 'Changes for this person only, on top of Everyone and their roles. Anything left as “As above” follows those.';
    }
    ?></p>
    <?php
}

/** Save and reset buttons for a layer editor form. */
function render_layer_buttons(string $resetAction, array $layer, bool $hasData): void
{
    ?>
    <div class="lfa-save-bar">
        <?php submit_button('Save Changes', 'primary', 'submit', false); ?>
        <?php if ($hasData) : ?>
            <button type="submit" class="button" name="lfa_reset" value="1" data-lfa-confirm="<?php echo esc_attr($layer['type'] === 'everyone' ? 'Remove all Everyone changes and go back to WordPress’s own layout?' : 'Remove the changes for ' . layer_label($layer) . '? They will follow the layer above again.'); ?>"><?php echo $layer['type'] === 'everyone' ? 'Reset Everyone' : 'Reset ' . esc_html($layer['type'] === 'role' ? 'this role' : 'this person'); ?></button>
        <?php endif; ?>
        <span class="lfa-unsaved" hidden>Unsaved changes</span>
    </div>
    <?php
}

/** Shared tri-state select: Show/Hide for Everyone; As above/Show/Hide otherwise. */
function render_visibility_select(string $name, ?bool $value, bool $inheritedHidden, bool $isEveryone, string $label): void
{
    $current = $value === null ? '' : ($value ? 'hide' : 'show');
    echo '<select name="' . esc_attr($name) . '" class="lfa-vis" aria-label="' . esc_attr($label) . '">';
    if ($isEveryone) {
        echo '<option value="show"' . selected($current !== 'hide', true, false) . '>Shown</option>';
        echo '<option value="hide"' . selected($current, 'hide', false) . '>Hidden</option>';
    } else {
        echo '<option value=""' . selected($current, '', false) . '>As above (' . ($inheritedHidden ? 'hidden' : 'shown') . ')</option>';
        echo '<option value="show"' . selected($current, 'show', false) . '>Show</option>';
        echo '<option value="hide"' . selected($current, 'hide', false) . '>Hide</option>';
    }
    echo '</select>';
}

/** Read a posted visibility value for a layer: true, false or null. */
function posted_visibility($value, bool $isEveryone): ?bool
{
    $value = is_string($value) ? $value : '';
    if ($value === 'hide') { return true; }
    if ($value === 'show') { return $isEveryone ? null : false; }
    return null;
}
