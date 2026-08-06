<?php

namespace Softminal\ImageCraft;

if (!defined('ABSPATH')) {
    exit;
}

class Menu {
    public function init() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
    }

    public function add_admin_menu() {
        // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Required for WordPress SVG menu icon
        $icon = 'data:image/svg+xml;base64,' . base64_encode(
            // Bolt Horizon mark, monochrome: WordPress tints menu icons via
            // currentColor, so this must stay single-colour and detail-light.
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M2.5 18.6L7.6 12L11.2 15.7L14.6 11L20.6 18.6Z"></path><path d="M18.6 2.4L14.6 8.2L17.1 8.2L16.4 12.2L20.4 6.4L17.9 6.4Z"></path></svg>'
        );

        $menu_title = apply_filters('icais_menu_title', 'ImageCraft');

        // Admins always see the menu. Non-admins see it only when their role is
        // permitted (Permissions settings) AND they can upload media — which is
        // the capability floor the plugin's REST routes require.
        $isAdmin = current_user_can('manage_options');
        if (!$isAdmin && !\Softminal\ImageCraft\Support\Permission::isAllowed()) {
            return;
        }
        $capability = $isAdmin ? 'manage_options' : 'upload_files';

        add_menu_page(
            $menu_title,
            $menu_title,
            $capability,
            'icais',
            [$this, 'render_dashboard'],
            $icon,
            59
        );
    }

    public function render_dashboard() {
        wp_enqueue_media();
        echo wp_kses_post('<div id="icais-app"></div>');
    }
}
