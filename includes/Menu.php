<?php

namespace ImageCraft\Includes;

if (!defined('ABSPATH')) {
    exit;
}

class Menu {
    public function init() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
    }

    public function add_admin_menu() {
        add_menu_page(
            __('ImageCraft', 'imagecraft'),
            __('ImageCraft', 'imagecraft'),
            'manage_options',
            'imagecraft',
            [$this, 'render_dashboard'],
            'dashicons-format-image',
            20
        );
    }

    public function render_dashboard() {
        wp_enqueue_media();
        echo '<div id="imagecraft-app"></div>';
    }
}
