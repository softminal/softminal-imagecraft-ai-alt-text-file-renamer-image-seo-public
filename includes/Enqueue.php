<?php

namespace WpAiAltText\Includes;

if (!defined('ABSPATH')) {
    exit;
}

class Enqueue {
    private const DEV_SERVER_URL = 'http://localhost:4000';
    private const DEV_SERVER_PORT = 4000;
    
    public function init() {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }

    /**
     * Check if Vite dev server is running
     * Uses a fast socket connection check to avoid blocking page load
     * Caches result in transient to avoid repeated checks
     * @return bool
     */
    private function isDevServerRunning() {
        // Cache the result for 5 seconds to avoid repeated checks
        $cache_key = 'wp_ai_alt_text_dev_server_check';
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return (bool) $cached;
        }
        
        $is_running = false;
        
        // Use a method that doesn't trigger PHP errors
        // Check if socket extension is available
        if (function_exists('socket_create')) {
            $socket = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
            if ($socket !== false) {
                // Set non-blocking mode
                @socket_set_nonblock($socket);
                // Try to connect with very short timeout
                $result = @socket_connect($socket, '127.0.0.1', self::DEV_SERVER_PORT);
                if ($result !== false) {
                    $is_running = true;
                }
                @socket_close($socket);
            }
        } else {
            // Fallback: Use stream_socket_client with complete error suppression
            $old_error_handler = set_error_handler(function() { return true; }, E_ALL);
            $socket = stream_socket_client(
                'tcp://127.0.0.1:' . self::DEV_SERVER_PORT,
                $errno,
                $errstr,
                0.1,
                STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT
            );
            restore_error_handler();
            
            if ($socket !== false) {
                @fclose($socket);
                $is_running = true;
            }
        }
        
        // Cache the result for 5 seconds
        set_transient($cache_key, $is_running ? 1 : 0, 5);
        
        return $is_running;
    }

    /**
     * Check if we should use dev mode
     * @return bool
     */
    private function isDevMode() {
        // Check if dev mode is explicitly set via constant
        if (defined('WP_AI_ALT_TEXT_DEV_MODE')) {
            $dev_mode = WP_AI_ALT_TEXT_DEV_MODE;
            if ($dev_mode !== null) {
                return (bool) $dev_mode;
            }
        }
        
        // Auto-detect: Check if dev server is running
        if ($this->isDevServerRunning()) {
            return true;
        }
        
        // Fallback: Check if manifest exists (production mode)
        $manifest_path = WP_AI_ALT_TEXT_DIR . 'assets/.vite/manifest.json';
        if (!file_exists($manifest_path)) {
            $manifest_path = WP_AI_ALT_TEXT_DIR . 'assets/manifest.json';
        }
        
        // If manifest exists, we're in production mode
        // If no manifest exists, assume dev mode (but dev server not running - might be an error)
        return !file_exists($manifest_path);
    }

    public function enqueue_admin_assets($hook) {
        // Only load assets on the plugin's admin page
        if ($hook !== 'toplevel_page_wp-ai-alt-text') {
            return;
        }
    
        // Script dependencies - ensure WordPress core scripts load first
        // Note: Media scripts are loaded via wp_enqueue_media() in Menu.php
        $script_dependencies = ['jquery', 'wp-api-fetch', 'wp-i18n'];
        
        // Check if we should use dev mode (Vite dev server running)
        if ($this->isDevMode()) {
            // Development mode: Load assets from Vite dev server
            // Vite dev server serves files from project root without base path
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
            error_log('WP AI Alt Text: Manifest file not found in production mode.');
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