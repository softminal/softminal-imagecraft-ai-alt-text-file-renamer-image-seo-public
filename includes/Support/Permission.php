<?php

namespace Softminal\ImageCraft\Support;

use Softminal\ImageCraft\Services\Settings;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Role-based access gate for ImageCraft's action features
 * (alt text generation, title generation, file rename, image compression).
 *
 * Administrators (manage_options) are always allowed. When no roles are
 * selected (empty allow-list) the plugin is unrestricted — every user who
 * passes the route capability check may use it, preserving legacy behavior.
 */
class Permission
{
    /**
     * Whether the given user (or current user) may use ImageCraft features.
     *
     * @param int|null $userId Specific user id, or null for the current user.
     */
    public static function isAllowed(?int $userId = null): bool
    {
        if ($userId === null) {
            if (current_user_can('manage_options')) {
                return true;
            }
            $user = wp_get_current_user();
        } else {
            if (user_can($userId, 'manage_options')) {
                return true;
            }
            $user = get_userdata($userId);
        }

        // get_userdata() can return false; wp_get_current_user() returns an
        // empty user (no roles) for logged-out requests.
        if (!$user || empty($user->roles)) {
            return false;
        }

        $raw = Settings::get('icais_allowed_roles', '[]');
        $allowed = json_decode((string) $raw, true);

        // No restriction configured -> preserve legacy behavior (allow).
        if (!is_array($allowed) || empty($allowed)) {
            return true;
        }

        return (bool) array_intersect((array) $user->roles, $allowed);
    }
}
