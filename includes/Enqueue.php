<?php

namespace SmartAltTextAi\Includes;

use SmartAltTextAi\Includes\Services\Settings;
use SmartAltTextAi\Includes\Services\TranslationStrings;

if (!defined('ABSPATH')) {
    exit;
}

class Enqueue {
    private const DEV_SERVER_URL = 'http://localhost:4000';

    public function init() {
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueMediaButtonAssets']);
        add_action('enqueue_block_editor_assets', [$this, 'enqueueBlockEditorAssets']);
    }


    public function enqueueAdminAssets($hook) {
        if ($hook !== 'toplevel_page_smart-alt-text-ai') {
            return;
        }

        // Script dependencies - ensure WordPress core scripts load first
        // Note: Media scripts are loaded via wp_enqueue_media() in Menu.php
        $script_dependencies = ['jquery', 'wp-api-fetch', 'wp-i18n'];

        // Check if we should use dev mode (Vite dev server running)
        if (defined('SATAI_DEV_MODE') && SATAI_DEV_MODE) {
            // Development mode: Load assets from Vite dev server
            $dev_server_url = self::DEV_SERVER_URL . '/resources/admin/main.js';
            wp_enqueue_script(
                'satai-script',
                $dev_server_url,
                $script_dependencies,
                SATAI_VERSION,
                true // Load in footer to ensure WordPress scripts load first
            );
            add_filter('script_loader_tag', function ($tag, $handle) {
                if ('satai-script' === $handle) {
                    return str_replace('<script ', '<script type="module" ', $tag);
                }
                return $tag;
            }, 10, 2);

            // Enqueue SCSS from dev server
            wp_enqueue_style(
                'satai-dev-style',
                self::DEV_SERVER_URL . '/resources/scss/index.scss',
                [],
                SATAI_VERSION
            );

            // Localize script for dev mode
            wp_localize_script('satai-script', 'smartAltTextAi', [
                'apiUrl' => rest_url(SATAI_API_NAMESPACE),
                'adminUrl' => admin_url(),
                'nonce' => wp_create_nonce('wp_rest'),
                'debug' => true,
                'i18n' => TranslationStrings::get(),
            ]);
        } else {
            // Production mode: Load assets from the assets directory
            $script_enqueued = $this->enqueueProductionAssets($script_dependencies);

            // Only localize if script was successfully enqueued
            if ($script_enqueued) {
                wp_localize_script('satai-script', 'smartAltTextAi', [
                    'apiUrl' => rest_url(SATAI_API_NAMESPACE),
                    'adminUrl' => admin_url(),
                    'nonce' => wp_create_nonce('wp_rest'),
                    'debug' => false,
                    'i18n' => TranslationStrings::get(),
                ]);
            }
        }
    }

    private function enqueueProductionAssets(array $dependencies = []): bool {
        // Check for manifest in .vite folder first (Vite's default location)
        $manifest_path = SATAI_DIR . 'assets/.vite/manifest.json';

        // Fallback to root assets folder
        if (!file_exists($manifest_path)) {
            $manifest_path = SATAI_DIR . 'assets/manifest.json';
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
                'satai-script',
                SATAI_URL . 'assets/' . $main_js,
                $dependencies,
                SATAI_VERSION,
                true // Load in footer to ensure WordPress scripts load first
            );

            // Add type="module" for production builds
            add_filter('script_loader_tag', function ($tag, $handle) {
                if ('satai-script' === $handle) {
                    return str_replace('<script ', '<script type="module" ', $tag);
                }
                return $tag;
            }, 10, 2);

            $script_enqueued = true;

            // Enqueue the CSS files
            if (isset($manifest['resources/admin/main.js']['css'])) {
                foreach ($manifest['resources/admin/main.js']['css'] as $css_file) {
                    wp_enqueue_style(
                        'satai-style',
                        SATAI_URL . 'assets/' . $css_file,
                        [],
                        SATAI_VERSION
                    );
                }
            }
        }

        // Enqueue SCSS file if it exists separately
        if (isset($manifest['resources/scss/index.scss'])) {
            $scss_file = $manifest['resources/scss/index.scss']['file'];
            wp_enqueue_style(
                'satai-scss',
                SATAI_URL . 'assets/' . $scss_file,
                [],
                SATAI_VERSION
            );
        }

        return $script_enqueued;
    }

    /**
     * Enqueue media button assets for WordPress media modal
     */
    public function enqueueMediaButtonAssets($hook) {
        // Only load on pages where the media modal can appear
        if (!in_array($hook, ['post.php', 'post-new.php', 'upload.php'], true)) {
            return;
        }

        // Only for users who can edit posts
        if (!current_user_can('edit_posts')) {
            return;
        }

        // Only if wizard is completed
        if (!Settings::isWizardCompleted()) {
            return;
        }

        wp_enqueue_script(
            'satai-media-button',
            SATAI_URL . 'resources/admin/media-button.js',
            ['media-views'],
            SATAI_VERSION,
            true
        );

        wp_localize_script('satai-media-button', 'sataiMediaButton', [
            'apiUrl' => rest_url(SATAI_API_NAMESPACE),
            'nonce' => wp_create_nonce('wp_rest'),
            'defaultTone' => Settings::get('satai_default_tone', 'professional'),
            'i18n' => [
                'generate' => __('Generate with AI', 'smart-alt-text-ai'),
                'generating' => __('Generating...', 'smart-alt-text-ai'),
                'success' => __('Alt text generated successfully!', 'smart-alt-text-ai'),
                'error' => __('Failed to generate alt text. Please try again.', 'smart-alt-text-ai'),
                'noAttachment' => __('Could not detect image. Please select an image first.', 'smart-alt-text-ai'),
            ],
        ]);

        // Inline CSS for the media button
        $css = '
            .satai-media-btn-wrapper {
                padding: 2px 0 8px;
                /* Align with WP media modal input column (label is ~35%, input is ~65%) */
                padding-left: 35%;
                overflow: hidden;
            }
            .satai-media-btn {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 5px 12px;
                background: linear-gradient(135deg, #2271b1, #135e96);
                color: #fff;
                border: none;
                border-radius: 3px;
                cursor: pointer;
                font-size: 12px;
                font-weight: 500;
                line-height: 1.6;
                transition: all 0.2s ease;
                box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
            }
            .satai-media-btn:hover {
                background: linear-gradient(135deg, #135e96, #0a4b78);
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.15);
            }
            .satai-media-btn:focus {
                outline: 2px solid #2271b1;
                outline-offset: 2px;
            }
            .satai-media-btn:disabled {
                opacity: 0.7;
                cursor: not-allowed;
                box-shadow: none;
            }
            .satai-media-btn.satai-loading .satai-btn-icon {
                animation: satai-spin 1s linear infinite;
            }
            .satai-btn-icon {
                display: inline-flex;
                align-items: center;
                flex-shrink: 0;
            }
            .satai-btn-icon svg {
                display: block;
            }
            @keyframes satai-spin {
                from { transform: rotate(0deg); }
                to { transform: rotate(360deg); }
            }
            .satai-media-status {
                margin-top: 6px;
                font-size: 12px;
                line-height: 1.4;
            }
            .satai-status-success {
                color: #00a32a;
            }
            .satai-status-error {
                color: #d63638;
            }
        ';
        wp_add_inline_style('media-views', $css);
    }

    /**
     * Enqueue Gutenberg block editor assets for AI alt text generation
     */
    public function enqueueBlockEditorAssets() {
        if (!current_user_can('edit_posts')) {
            return;
        }

        if (!Settings::isWizardCompleted()) {
            return;
        }

        wp_enqueue_script(
            'satai-gutenberg-alt-button',
            SATAI_URL . 'resources/admin/gutenberg-alt-button.js',
            ['wp-data', 'wp-block-editor'],
            SATAI_VERSION,
            true
        );

        $previewSetting = Settings::get('satai_preview_before_accept', '1');
        $previewBeforeAccept = ($previewSetting === '1' || $previewSetting === 1 || $previewSetting === true);

        wp_localize_script('satai-gutenberg-alt-button', 'sataiGutenberg', [
            'apiUrl' => rest_url(SATAI_API_NAMESPACE),
            'nonce' => wp_create_nonce('wp_rest'),
            'previewBeforeAccept' => $previewBeforeAccept,
            'i18n' => [
                'generate' => __('Generate with AI', 'smart-alt-text-ai'),
                'generating' => __('Generating...', 'smart-alt-text-ai'),
                'accept' => __('Accept', 'smart-alt-text-ai'),
                'reject' => __('Reject', 'smart-alt-text-ai'),
                'success' => __('Alt text generated!', 'smart-alt-text-ai'),
                'error' => __('Failed to generate alt text.', 'smart-alt-text-ai'),
            ],
        ]);

        // Inline CSS for the injected button and preview
        wp_register_style('satai-gutenberg-style', false);
        wp_enqueue_style('satai-gutenberg-style');
        wp_add_inline_style('satai-gutenberg-style', '
            .satai-gut-wrapper {
                margin-top: 8px;
            }
            .satai-gut-btn {
                display: inline-flex !important;
                align-items: center;
                gap: 6px;
            }
            .satai-gut-btn-icon {
                display: inline-flex;
                align-items: center;
                flex-shrink: 0;
            }
            .satai-gut-btn-icon svg {
                display: block;
            }
            .satai-gut-spin {
                animation: satai-gut-spin 1s linear infinite;
            }
            @keyframes satai-gut-spin {
                from { transform: rotate(0deg); }
                to { transform: rotate(360deg); }
            }
            .satai-gut-status {
                margin-top: 6px;
                font-size: 12px;
                line-height: 1.4;
            }
            .satai-gut-status-success {
                color: #00a32a;
            }
            .satai-gut-status-error {
                color: #d63638;
            }
            .satai-gut-preview {
                margin-top: 8px;
            }
            .satai-gut-preview-ta {
                width: 100%;
                min-height: 70px;
                margin-bottom: 8px;
                font-size: 13px;
            }
            .satai-gut-preview-actions {
                display: flex;
                gap: 8px;
            }
        ');
    }
}
