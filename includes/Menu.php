<?php

namespace SmartAltTextAi\Includes;

if (!defined('ABSPATH')) {
    exit;
}

class Menu {
    public function init() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
    }

    public function add_admin_menu() {
        add_menu_page(
            __('AI Alt Text', 'smart-alt-text-ai'),
            __('AI Alt Text', 'smart-alt-text-ai'),
            'manage_options',
            'smart-alt-text-ai',
            [$this, 'render_dashboard'],
            'dashicons-format-image',
            20
        );
    }

    public function render_dashboard() {
        wp_enqueue_media();
        echo '<div id="smart-alt-text-ai-app"></div>';
    }
}
