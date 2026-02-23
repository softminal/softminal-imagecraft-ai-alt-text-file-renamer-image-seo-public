<?php

namespace Softminal\ImageCraft;

use Softminal\ImageCraft\Services\Settings;
use Softminal\ImageCraft\Services\TranslationStrings;

if (!defined('ABSPATH')) {
    exit;
}

class Enqueue {
    private const DEV_SERVER_URL = 'http://localhost:4000';

    public function init() {
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueMediaButtonAssets']);
        add_action('enqueue_block_editor_assets', [$this, 'enqueueBlockEditorAssets']);
        add_action('admin_enqueue_scripts', [$this, 'removeExternalNotices']);
    }

    /**
     * Remove all external plugin notices and content on ImageCraft pages
     */
    public function removeExternalNotices($hook) {
        if ($hook !== 'toplevel_page_icais') {
            return;
        }

        remove_all_actions('admin_notices');
        remove_all_actions('all_admin_notices');

        // Re-add our own review notice
        if (class_exists('\Softminal\ImageCraft\AdminNotice')) {
            add_action('admin_notices', ['\Softminal\ImageCraft\AdminNotice', 'displayReviewNotice']);
        }
    }


    public function enqueueAdminAssets($hook) {
        if ($hook !== 'toplevel_page_icais') {
            return;
        }

        // Script dependencies - ensure WordPress core scripts load first
        // Note: Media scripts are loaded via wp_enqueue_media() in Menu.php
        $script_dependencies = ['jquery', 'wp-api-fetch', 'wp-i18n'];

        // Check if we should use dev mode (Vite dev server running)
        if (defined('ICAIS_DEV_MODE') && ICAIS_DEV_MODE) {
            // Development mode: Load assets from Vite dev server
            $dev_server_url = self::DEV_SERVER_URL . '/resources/admin/main.js';
            wp_enqueue_script(
                'icais-script',
                $dev_server_url,
                $script_dependencies,
                ICAIS_VERSION,
                true // Load in footer to ensure WordPress scripts load first
            );
            add_filter('script_loader_tag', function ($tag, $handle) {
                if ('icais-script' === $handle) {
                    return str_replace('<script ', '<script type="module" ', $tag);
                }
                return $tag;
            }, 10, 2);

            // Enqueue SCSS from dev server
            wp_enqueue_style(
                'icais-dev-style',
                self::DEV_SERVER_URL . '/resources/scss/index.scss',
                [],
                ICAIS_VERSION
            );

            // Localize script for dev mode
            wp_localize_script('icais-script', 'icais', [
                'apiUrl' => rest_url(ICAIS_API_NAMESPACE),
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
                wp_localize_script('icais-script', 'icais', [
                    'apiUrl' => rest_url(ICAIS_API_NAMESPACE),
                    'adminUrl' => admin_url(),
                    'nonce' => wp_create_nonce('wp_rest'),
                    'debug' => false,
                    'i18n' => TranslationStrings::get(),
                ]);
            }
        }
    }

    private function enqueueProductionAssets(array $dependencies = []): bool {
        // Check for manifest in .vite folder first (Vite's default location), then root assets folder
        $manifest = wp_json_file_decode(ICAIS_DIR . 'assets/.vite/manifest.json', ['associative' => true]);
        if (empty($manifest)) {
            $manifest = wp_json_file_decode(ICAIS_DIR . 'assets/manifest.json', ['associative' => true]);
        }
        if (empty($manifest)) {
            return false;
        }
        $script_enqueued = false;

        // Enqueue the main JS file
        if (isset($manifest['resources/admin/main.js'])) {
            $main_js = $manifest['resources/admin/main.js']['file'];
            wp_enqueue_script(
                'icais-script',
                ICAIS_URL . 'assets/' . $main_js,
                $dependencies,
                ICAIS_VERSION,
                true // Load in footer to ensure WordPress scripts load first
            );

            // Add type="module" for production builds
            add_filter('script_loader_tag', function ($tag, $handle) {
                if ('icais-script' === $handle) {
                    return str_replace('<script ', '<script type="module" ', $tag);
                }
                return $tag;
            }, 10, 2);

            $script_enqueued = true;

            // Enqueue the CSS files
            if (isset($manifest['resources/admin/main.js']['css'])) {
                foreach ($manifest['resources/admin/main.js']['css'] as $css_file) {
                    wp_enqueue_style(
                        'icais-style',
                        ICAIS_URL . 'assets/' . $css_file,
                        [],
                        ICAIS_VERSION
                    );
                }
            }
        }

        // Enqueue SCSS file if it exists separately
        if (isset($manifest['resources/scss/index.scss'])) {
            $scss_file = $manifest['resources/scss/index.scss']['file'];
            wp_enqueue_style(
                'icais-scss',
                ICAIS_URL . 'assets/' . $scss_file,
                [],
                ICAIS_VERSION
            );
        }

        return $script_enqueued;
    }

    /**
     * Enqueue media button assets for WordPress media modal
     */
    public function enqueueMediaButtonAssets($hook) {
        // Only load on pages where the media modal can appear
        if (!in_array($hook, ['post.php', 'post-new.php', 'upload.php', 'toplevel_page_icais'], true)) {
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
            'icais-media-button',
            ICAIS_URL . 'resources/admin/media-button.js',
            ['media-views'],
            ICAIS_VERSION,
            true
        );

        wp_localize_script('icais-media-button', 'icaisMediaButton', [
            'apiUrl' => rest_url(ICAIS_API_NAMESPACE),
            'nonce' => wp_create_nonce('wp_rest'),
            'defaultTone' => Settings::get('icais_default_tone', 'professional'),
            'i18n' => [
                'generate' => __('Generate with AI', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
                'generating' => __('Generating...', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
                'success' => __('Alt text generated successfully!', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
                'error' => __('Failed to generate alt text. Please try again.', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
                'noAttachment' => __('Could not detect image. Please select an image first.', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
                'rename' => __('Rename File', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
                'renaming' => __('Renaming...', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
                /* translators: %s: new filename */
                'renameSuccess' => __('File renamed to: %s', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
                'renameError' => __('Failed to rename file.', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            ],
        ]);

        // Inline CSS for the media button
        $css = '
            .icais-media-btn-wrapper {
                padding: 2px 0 8px;
                /* Align with WP media modal input column (label is ~35%, input is ~65%) */
                padding-left: 35%;
                overflow: hidden;
            }
            .icais-media-btn {
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
            .icais-media-btn:hover {
                background: linear-gradient(135deg, #135e96, #0a4b78);
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.15);
            }
            .icais-media-btn:focus {
                outline: 2px solid #2271b1;
                outline-offset: 2px;
            }
            .icais-media-btn:disabled {
                opacity: 0.7;
                cursor: not-allowed;
                box-shadow: none;
            }
            .icais-media-btn.icais-loading .icais-btn-icon {
                animation: icais-spin 1s linear infinite;
            }
            .icais-btn-icon {
                display: inline-flex;
                align-items: center;
                flex-shrink: 0;
            }
            .icais-btn-icon svg {
                display: block;
            }
            @keyframes icais-spin {
                from { transform: rotate(0deg); }
                to { transform: rotate(360deg); }
            }
            .icais-media-status {
                margin-top: 6px;
                font-size: 12px;
                line-height: 1.4;
            }
            .icais-status-success {
                color: #00a32a;
            }
            .icais-status-error {
                color: #d63638;
            }
            .icais-rename-btn {
                background: linear-gradient(135deg, #00a32a, #008a20) !important;
            }
            .icais-rename-btn:hover {
                background: linear-gradient(135deg, #008a20, #006818) !important;
            }
            .icais-rename-btn:focus {
                outline-color: #00a32a !important;
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
            'icais-gutenberg-alt-button',
            ICAIS_URL . 'resources/admin/gutenberg-alt-button.js',
            ['wp-data', 'wp-block-editor'],
            ICAIS_VERSION,
            true
        );

        $previewSetting = Settings::get('icais_preview_before_accept', '1');
        $previewBeforeAccept = ($previewSetting === '1' || $previewSetting === 1 || $previewSetting === true);

        wp_localize_script('icais-gutenberg-alt-button', 'icaisGutenberg', [
            'apiUrl' => rest_url(ICAIS_API_NAMESPACE),
            'nonce' => wp_create_nonce('wp_rest'),
            'previewBeforeAccept' => $previewBeforeAccept,
            'i18n' => [
                'generate' => __('Generate with AI', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
                'generating' => __('Generating...', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
                'accept' => __('Accept', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
                'reject' => __('Reject', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
                'success' => __('Alt text generated!', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
                'error' => __('Failed to generate alt text.', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            ],
        ]);

        // Inline CSS for the injected button and preview
        wp_register_style('icais-gutenberg-style', false, [], ICAIS_VERSION);
        wp_enqueue_style('icais-gutenberg-style');
        wp_add_inline_style('icais-gutenberg-style', '
            .icais-gut-wrapper {
                margin-top: 8px;
            }
            .icais-gut-btn {
                display: inline-flex !important;
                align-items: center;
                gap: 6px;
            }
            .icais-gut-btn-icon {
                display: inline-flex;
                align-items: center;
                flex-shrink: 0;
            }
            .icais-gut-btn-icon svg {
                display: block;
            }
            .icais-gut-spin {
                animation: icais-gut-spin 1s linear infinite;
            }
            @keyframes icais-gut-spin {
                from { transform: rotate(0deg); }
                to { transform: rotate(360deg); }
            }
            .icais-gut-status {
                margin-top: 6px;
                font-size: 12px;
                line-height: 1.4;
            }
            .icais-gut-status-success {
                color: #00a32a;
            }
            .icais-gut-status-error {
                color: #d63638;
            }
            .icais-gut-preview {
                margin-top: 8px;
            }
            .icais-gut-preview-ta {
                width: 100%;
                min-height: 70px;
                margin-bottom: 8px;
                font-size: 13px;
            }
            .icais-gut-preview-actions {
                display: flex;
                gap: 8px;
            }
        ');
    }
}
