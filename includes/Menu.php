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
        add_menu_page(
            __('ImageCraft', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            __('ImageCraft', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'manage_options',
            'icais',
            [$this, 'render_dashboard'],
            'dashicons-format-image',
            99
        );
    }

    public function render_dashboard() {
        wp_enqueue_media();
        echo wp_kses_post('<div id="icais-app"></div>');
    }
}
