<?php

namespace Softminal\ImageCraft;

use Softminal\ImageCraft\Services\Settings;
use Softminal\ImageCraft\Services\TranslationStrings;

if (!defined('ABSPATH')) {
    exit;
}

class Enqueue {
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

        $deps = ['jquery', 'wp-api-fetch', 'wp-i18n'];
        $script_enqueued = EnqueueAsset::script('icais-script', 'resources/admin/main.js', $deps);
        EnqueueAsset::style('icais-scss', 'resources/scss/index.css');

        if ($script_enqueued) {
            wp_localize_script('icais-script', 'icais', [
                'apiUrl' => rest_url(ICAIS_API_NAMESPACE),
                'adminUrl' => admin_url(),
                'nonce' => wp_create_nonce('wp_rest'),
                'debug' => EnqueueAsset::isDev(),
                'pluginUrl' => ICAIS_URL,
                'isPro'          => defined('ICAIS_PRO') && ICAIS_PRO,
                'isProInstalled' => defined('ICAIS_PRO_INSTALLED') && ICAIS_PRO_INSTALLED,
                'generateTitle' => Settings::get('icais_generate_title', '0'),
                'autoRename' => Settings::get('icais_auto_rename', '0'),
                'i18n' => TranslationStrings::get(),
            ]);
        }
    }

    /**
     * Enqueue media button assets for WordPress media modal
     */
    public function enqueueMediaButtonAssets($hook) {
        // Only load on pages where the media modal can appear
        if (!in_array($hook, ['post.php', 'post-new.php', 'upload.php', 'toplevel_page_icais'], true)) {
            return;
        }

        if (!current_user_can('edit_posts')) {
            return;
        }

        if (!Settings::isWizardCompleted()) {
            return;
        }

        EnqueueAsset::script('icais-media-button', 'resources/admin/js/media-button.js', ['media-views']);

        wp_localize_script('icais-media-button', 'icaisMediaButton', [
            'apiUrl' => rest_url(ICAIS_API_NAMESPACE),
            'nonce' => wp_create_nonce('wp_rest'),
            'defaultTone' => Settings::get('icais_default_tone', 'professional'),
            'generateTitle' => Settings::get('icais_generate_title', '0'),
            'autoRename' => Settings::get('icais_auto_rename', '0'),
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
                'generateTitle' => __('Generate Title', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
                'generatingTitle' => __('Generating title...', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
                /* translators: %s: new title */
                'titleSuccess' => __('Title updated to: %s', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
                'titleError' => __('Failed to generate title.', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
                'renameConfirm' => __("This will change the image URL. External links and cached pages may show broken images.\n\nAre you sure you want to rename this file?", 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            ],
        ]);

        wp_enqueue_style(
            'icais-media-button-style',
            ICAIS_URL . 'resources/admin/css/media-button.css',
            ['media-views'],
            ICAIS_VERSION
        );
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

        EnqueueAsset::script('icais-gutenberg-alt-button', 'resources/admin/js/gutenberg-alt-button.js', ['wp-data', 'wp-block-editor']);

        $previewBeforeAccept = Settings::getBoolean('icais_preview_before_accept', true);
        $titlePreviewBeforeAccept = Settings::getBoolean('icais_title_preview_before_accept', true);

        wp_localize_script('icais-gutenberg-alt-button', 'icaisGutenberg', [
            'apiUrl' => rest_url(ICAIS_API_NAMESPACE),
            'nonce' => wp_create_nonce('wp_rest'),
            'previewBeforeAccept' => $previewBeforeAccept,
            'titlePreviewBeforeAccept' => $titlePreviewBeforeAccept,
            'i18n' => [
                'generate' => __('Generate with AI', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
                'generating' => __('Generating...', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
                'accept' => __('Accept', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
                'reject' => __('Reject', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
                'success' => __('Alt text generated!', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
                'error' => __('Failed to generate alt text.', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            ],
        ]);

        // Shared utilities for block editor integrations
        $needsBlockEditorCommon = (self::isKadenceActive() && Settings::get('icais_integration_kadence', '1') === '1')
            || (self::isSpectraActive() && Settings::get('icais_integration_spectra', '1') === '1');
        if ($needsBlockEditorCommon) {
            EnqueueAsset::script('icais-block-editor-common', 'resources/admin/js/block-editor-common.js', ['wp-data', 'wp-block-editor']);
        }

        // Kadence Image block integration
        if (self::isKadenceActive() && Settings::get('icais_integration_kadence', '1') === '1') {
            EnqueueAsset::script('icais-kadence-alt-button', 'resources/admin/js/kadence-alt-button.js', ['wp-data', 'wp-block-editor', 'icais-block-editor-common']);
        }

        // Spectra Image block integration
        if (self::isSpectraActive() && Settings::get('icais_integration_spectra', '1') === '1') {
            EnqueueAsset::script('icais-spectra-alt-button', 'resources/admin/js/spectra-alt-button.js', ['wp-data', 'wp-block-editor', 'icais-block-editor-common']);
        }

        wp_enqueue_style(
            'icais-gutenberg-style',
            ICAIS_URL . 'resources/admin/css/block-editor-ai.css',
            [],
            ICAIS_VERSION
        );
    }

    private static function isKadenceActive(): bool {
        return defined('KADENCE_BLOCKS_VERSION');
    }

    private static function isSpectraActive(): bool {
        return defined('UAGB_VER');
    }
}
