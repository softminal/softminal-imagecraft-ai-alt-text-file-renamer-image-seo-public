<?php

namespace WpAiAltText\Includes;

if (!defined('ABSPATH')) {
    exit;
}

class Menu {
    public function init() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
    }

    public function add_admin_menu() {
        add_menu_page(
            __('AI Alt Text', 'wp-ai-alt-text'),
            __('AI Alt Text', 'wp-ai-alt-text'),
            'manage_options',
            'wp-ai-alt-text',
            [$this, 'render_dashboard'],
            'dashicons-format-image',
            20
        );
    }

    public function render_dashboard() {
        wp_enqueue_media();
        echo '<div id="wp-ai-alt-text-app"></div>';
    }
}