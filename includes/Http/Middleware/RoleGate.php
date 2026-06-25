<?php

namespace Softminal\ImageCraft\Http\Middleware;

use Softminal\ImageCraft\Support\Permission;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Restricts ImageCraft action routes (generation, rename, compression) to the
 * roles selected on the Permissions settings page. Administrators are always
 * allowed; an empty selection means no restriction.
 */
class RoleGate
{
    /**
     * @param \WP_REST_Request $request
     * @return true|WP_Error
     */
    public function handle($request)
    {
        if (Permission::isAllowed()) {
            return true;
        }

        return new WP_Error(
            'rest_forbidden_role',
            __('Your role is not permitted to use this feature.', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            ['status' => 403]
        );
    }
}
