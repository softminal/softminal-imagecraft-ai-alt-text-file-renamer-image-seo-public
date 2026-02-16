<?php

namespace ImageCraft\Includes;

use ImageCraft\Includes\Services\Settings;
use ImageCraft\Includes\Services\TranslationStrings;

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
        if ($hook !== 'toplevel_page_imagecraft') {
            return;
        }

        // Script dependencies - ensure WordPress core scripts load first
        // Note: Media scripts are loaded via wp_enqueue_media() in Menu.php
        $script_dependencies = ['jquery', 'wp-api-fetch', 'wp-i18n'];

        // Check if we should use dev mode (Vite dev server running)
        if (defined('IMGCRAFT_DEV_MODE') && IMGCRAFT_DEV_MODE) {
            // Development mode: Load assets from Vite dev server
            $dev_server_url = self::DEV_SERVER_URL . '/resources/admin/main.js';
            wp_enqueue_script(
                'imgcraft-script',
                $dev_server_url,
                $script_dependencies,
                IMGCRAFT_VERSION,
                true // Load in footer to ensure WordPress scripts load first
            );
            add_filter('script_loader_tag', function ($tag, $handle) {
                if ('imgcraft-script' === $handle) {
                    return str_replace('<script ', '<script type="module" ', $tag);
                }
                return $tag;
            }, 10, 2);

            // Enqueue SCSS from dev server
            wp_enqueue_style(
                'imgcraft-dev-style',
                self::DEV_SERVER_URL . '/resources/scss/index.scss',
                [],
                IMGCRAFT_VERSION
            );

            // Localize script for dev mode
            wp_localize_script('imgcraft-script', 'imageCraft', [
                'apiUrl' => rest_url(IMGCRAFT_API_NAMESPACE),
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
                wp_localize_script('imgcraft-script', 'imageCraft', [
                    'apiUrl' => rest_url(IMGCRAFT_API_NAMESPACE),
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
        $manifest_path = IMGCRAFT_DIR . 'assets/.vite/manifest.json';

        // Fallback to root assets folder
        if (!file_exists($manifest_path)) {
            $manifest_path = IMGCRAFT_DIR . 'assets/manifest.json';
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
                'imgcraft-script',
                IMGCRAFT_URL . 'assets/' . $main_js,
                $dependencies,
                IMGCRAFT_VERSION,
                true // Load in footer to ensure WordPress scripts load first
            );

            // Add type="module" for production builds
            add_filter('script_loader_tag', function ($tag, $handle) {
                if ('imgcraft-script' === $handle) {
                    return str_replace('<script ', '<script type="module" ', $tag);
                }
                return $tag;
            }, 10, 2);

            $script_enqueued = true;

            // Enqueue the CSS files
            if (isset($manifest['resources/admin/main.js']['css'])) {
                foreach ($manifest['resources/admin/main.js']['css'] as $css_file) {
                    wp_enqueue_style(
                        'imgcraft-style',
                        IMGCRAFT_URL . 'assets/' . $css_file,
                        [],
                        IMGCRAFT_VERSION
                    );
                }
            }
        }

        // Enqueue SCSS file if it exists separately
        if (isset($manifest['resources/scss/index.scss'])) {
            $scss_file = $manifest['resources/scss/index.scss']['file'];
            wp_enqueue_style(
                'imgcraft-scss',
                IMGCRAFT_URL . 'assets/' . $scss_file,
                [],
                IMGCRAFT_VERSION
            );
        }

        return $script_enqueued;
    }

    /**
     * Enqueue media button assets for WordPress media modal
     */
    public function enqueueMediaButtonAssets($hook) {
        // Only load on pages where the media modal can appear
        if (!in_array($hook, ['post.php', 'post-new.php', 'upload.php', 'toplevel_page_imagecraft'], true)) {
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
            'imgcraft-media-button',
            IMGCRAFT_URL . 'resources/admin/media-button.js',
            ['media-views'],
            IMGCRAFT_VERSION,
            true
        );

        wp_localize_script('imgcraft-media-button', 'imgcraftMediaButton', [
            'apiUrl' => rest_url(IMGCRAFT_API_NAMESPACE),
            'nonce' => wp_create_nonce('wp_rest'),
            'defaultTone' => Settings::get('imgcraft_default_tone', 'professional'),
            'i18n' => [
                'generate' => __('Generate with AI', 'imagecraft'),
                'generating' => __('Generating...', 'imagecraft'),
                'success' => __('Alt text generated successfully!', 'imagecraft'),
                'error' => __('Failed to generate alt text. Please try again.', 'imagecraft'),
                'noAttachment' => __('Could not detect image. Please select an image first.', 'imagecraft'),
            ],
        ]);

        // Inline CSS for the media button
        $css = '
            .imgcraft-media-btn-wrapper {
                padding: 2px 0 8px;
                /* Align with WP media modal input column (label is ~35%, input is ~65%) */
                padding-left: 35%;
                overflow: hidden;
            }
            .imgcraft-media-btn {
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
            .imgcraft-media-btn:hover {
                background: linear-gradient(135deg, #135e96, #0a4b78);
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.15);
            }
            .imgcraft-media-btn:focus {
                outline: 2px solid #2271b1;
                outline-offset: 2px;
            }
            .imgcraft-media-btn:disabled {
                opacity: 0.7;
                cursor: not-allowed;
                box-shadow: none;
            }
            .imgcraft-media-btn.imgcraft-loading .imgcraft-btn-icon {
                animation: imgcraft-spin 1s linear infinite;
            }
            .imgcraft-btn-icon {
                display: inline-flex;
                align-items: center;
                flex-shrink: 0;
            }
            .imgcraft-btn-icon svg {
                display: block;
            }
            @keyframes imgcraft-spin {
                from { transform: rotate(0deg); }
                to { transform: rotate(360deg); }
            }
            .imgcraft-media-status {
                margin-top: 6px;
                font-size: 12px;
                line-height: 1.4;
            }
            .imgcraft-status-success {
                color: #00a32a;
            }
            .imgcraft-status-error {
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
            'imgcraft-gutenberg-alt-button',
            IMGCRAFT_URL . 'resources/admin/gutenberg-alt-button.js',
            ['wp-data', 'wp-block-editor'],
            IMGCRAFT_VERSION,
            true
        );

        $previewSetting = Settings::get('imgcraft_preview_before_accept', '1');
        $previewBeforeAccept = ($previewSetting === '1' || $previewSetting === 1 || $previewSetting === true);

        wp_localize_script('imgcraft-gutenberg-alt-button', 'imgcraftGutenberg', [
            'apiUrl' => rest_url(IMGCRAFT_API_NAMESPACE),
            'nonce' => wp_create_nonce('wp_rest'),
            'previewBeforeAccept' => $previewBeforeAccept,
            'i18n' => [
                'generate' => __('Generate with AI', 'imagecraft'),
                'generating' => __('Generating...', 'imagecraft'),
                'accept' => __('Accept', 'imagecraft'),
                'reject' => __('Reject', 'imagecraft'),
                'success' => __('Alt text generated!', 'imagecraft'),
                'error' => __('Failed to generate alt text.', 'imagecraft'),
            ],
        ]);

        // Inline CSS for the injected button and preview
        wp_register_style('imgcraft-gutenberg-style', false, [], IMGCRAFT_VERSION);
        wp_enqueue_style('imgcraft-gutenberg-style');
        wp_add_inline_style('imgcraft-gutenberg-style', '
            .imgcraft-gut-wrapper {
                margin-top: 8px;
            }
            .imgcraft-gut-btn {
                display: inline-flex !important;
                align-items: center;
                gap: 6px;
            }
            .imgcraft-gut-btn-icon {
                display: inline-flex;
                align-items: center;
                flex-shrink: 0;
            }
            .imgcraft-gut-btn-icon svg {
                display: block;
            }
            .imgcraft-gut-spin {
                animation: imgcraft-gut-spin 1s linear infinite;
            }
            @keyframes imgcraft-gut-spin {
                from { transform: rotate(0deg); }
                to { transform: rotate(360deg); }
            }
            .imgcraft-gut-status {
                margin-top: 6px;
                font-size: 12px;
                line-height: 1.4;
            }
            .imgcraft-gut-status-success {
                color: #00a32a;
            }
            .imgcraft-gut-status-error {
                color: #d63638;
            }
            .imgcraft-gut-preview {
                margin-top: 8px;
            }
            .imgcraft-gut-preview-ta {
                width: 100%;
                min-height: 70px;
                margin-bottom: 8px;
                font-size: 13px;
            }
            .imgcraft-gut-preview-actions {
                display: flex;
                gap: 8px;
            }
        ');
    }
}
