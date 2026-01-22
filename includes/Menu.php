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
            __('WP AI Alt Text', 'wp-ai-alt-text'), // Page title
            __('WP AI Alt Text', 'wp-ai-alt-text'), // Menu title
            'manage_options',                     // Capability
            'wp-ai-alt-text',                      // Menu slug
            [$this, 'render_dashboard'],          // Callback function
            'dashicons-format-image',                   // Icon
            20                                    // Position
        );
    }

    public function render_dashboard() {
        wp_enqueue_media();
        echo '<div id="wp-ai-alt-text-app"></div>';
    }
}