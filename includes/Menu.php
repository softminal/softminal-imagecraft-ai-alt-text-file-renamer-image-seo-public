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
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill="#a0a5aa" d="M2 2h16v16H2V2zm2 2v8l3.5-3.5L11 12l2.5-2.5L16 12V4H4z"/><circle fill="#a0a5aa" cx="7.5" cy="7" r="1.5"/></svg>'
        );

        add_menu_page(
            __('ImageCraft', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            __('ImageCraft', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'manage_options',
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
