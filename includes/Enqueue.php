<?php

namespace WpAiAltText\Includes;

if (!defined('ABSPATH')) {
    exit;
}

class Enqueue {
    private const DEV_SERVER_URL = 'http://localhost:4000';
    
    public function init() {
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
    }


    public function enqueueAdminAssets($hook) {
        if ($hook !== 'toplevel_page_wp-ai-alt-text') {
            return;
        }
    
        // Script dependencies - ensure WordPress core scripts load first
        // Note: Media scripts are loaded via wp_enqueue_media() in Menu.php
        $script_dependencies = ['jquery', 'wp-api-fetch', 'wp-i18n'];
        
        // Check if we should use dev mode (Vite dev server running)
        if (defined('WP_AI_ALT_TEXT_DEV_MODE') && WP_AI_ALT_TEXT_DEV_MODE) {
            // Development mode: Load assets from Vite dev server
            $dev_server_url = self::DEV_SERVER_URL . '/resources/admin/main.js';
            wp_enqueue_script(
                'wp-ai-alt-text-script',
                $dev_server_url,
                $script_dependencies,
                WP_AI_ALT_TEXT_VERSION,
                true // Load in footer to ensure WordPress scripts load first
            );
            add_filter('script_loader_tag', function ($tag, $handle) {
                if ('wp-ai-alt-text-script' === $handle) {
                    return str_replace('<script ', '<script type="module" ', $tag);
                }
                return $tag;
            }, 10, 2);
            
            // Enqueue SCSS from dev server
            wp_enqueue_style(
                'wp-ai-alt-text-dev-style',
                self::DEV_SERVER_URL . '/resources/scss/index.scss',
                [],
                WP_AI_ALT_TEXT_VERSION
            );
            
            // Localize script for dev mode
            wp_localize_script('wp-ai-alt-text-script', 'wpAiAltText', [
                'apiUrl' => rest_url(WP_AI_ALT_TEXT_API_NAMESPACE),
                'adminUrl' => admin_url(),
                'nonce' => wp_create_nonce('wp_rest'),
                'debug' => true
            ]);
        } else {
            // Production mode: Load assets from the assets directory
            $script_enqueued = $this->enqueue_production_assets($script_dependencies);
            
            // Only localize if script was successfully enqueued
            if ($script_enqueued) {
                wp_localize_script('wp-ai-alt-text-script', 'wpAiAltText', [
                    'apiUrl' => rest_url(WP_AI_ALT_TEXT_API_NAMESPACE),
                    'adminUrl' => admin_url(),
                    'nonce' => wp_create_nonce('wp_rest'),
                    'debug' => false
                ]);
            }
        }
    }

    private function enqueue_production_assets($dependencies = []) {
        // Check for manifest in .vite folder first (Vite's default location)
        $manifest_path = WP_AI_ALT_TEXT_DIR . 'assets/.vite/manifest.json';
        
        // Fallback to root assets folder
        if (!file_exists($manifest_path)) {
            $manifest_path = WP_AI_ALT_TEXT_DIR . 'assets/manifest.json';
        }
    
        if (!file_exists($manifest_path)) {
            return false;
        }
        
        $manifest = json_decode(file_get_contents($manifest_path), true);
        $script_enqueued = false;
    
        // Enqueue the main JS file
        if (isset($manifest['resources/admin/main.js'])) {
            $main_js = $manifest['resources/admin/main.js']['file'];
            wp_enqueue_script(
                'wp-ai-alt-text-script',
                WP_AI_ALT_TEXT_URL . 'assets/' . $main_js,
                $dependencies,
                WP_AI_ALT_TEXT_VERSION,
                true // Load in footer to ensure WordPress scripts load first
            );
            
            // Add type="module" for production builds
            add_filter('script_loader_tag', function ($tag, $handle) {
                if ('wp-ai-alt-text-script' === $handle) {
                    return str_replace('<script ', '<script type="module" ', $tag);
                }
                return $tag;
            }, 10, 2);
            
            $script_enqueued = true;
    
            // Enqueue the CSS files
            if (isset($manifest['resources/admin/main.js']['css'])) {
                foreach ($manifest['resources/admin/main.js']['css'] as $css_file) {
                    wp_enqueue_style(
                        'wp-ai-alt-text-style',
                        WP_AI_ALT_TEXT_URL . 'assets/' . $css_file,
                        [],
                        WP_AI_ALT_TEXT_VERSION
                    );
                }
            }
        }
        
        // Enqueue SCSS file if it exists separately
        if (isset($manifest['resources/scss/index.scss'])) {
            $scss_file = $manifest['resources/scss/index.scss']['file'];
            wp_enqueue_style(
                'wp-ai-alt-text-scss',
                WP_AI_ALT_TEXT_URL . 'assets/' . $scss_file,
                [],
                WP_AI_ALT_TEXT_VERSION
            );
        }
        
        return $script_enqueued;
    }
}