<?php

namespace Softminal\ImageCraft;

use Softminal\ImageCraft\Services\Settings;
use Softminal\ImageCraft\Services\StatsTracker;
use Softminal\ImageCraft\Services\UsageLimiter;
use Softminal\ImageCraft\Services\AI\AIFactory;
use Softminal\ImageCraft\Services\SeoIntegration;
use Softminal\ImageCraft\Support\AltTextHelper;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Auto-generate alt text on image upload
 */
class AutoGenerate
{
    public static function init(): void
    {
        add_action('add_attachment', [self::class, 'onAttachmentAdded']);
        add_action('icais_auto_generate', [self::class, 'generateForAttachment']);
        add_action('icais_auto_generate_title', [self::class, 'generateTitleForAttachment']);
    }

    /**
     * Handle new attachment upload
     */
    public static function onAttachmentAdded($attachmentId)
    {
        // Respect the role-based permission for the uploading user
        // (admins always allowed; empty allow-list = no restriction).
        if (!\Softminal\ImageCraft\Support\Permission::isAllowed(get_current_user_id())) {
            return;
        }

        // Check if auto-generate is enabled
        // For WooCommerce product images, respect the WooCommerce-specific setting
        $isWooProduct = false;
        $attachment = get_post($attachmentId);
        if ($attachment && $attachment->post_parent > 0 && get_post_type($attachment->post_parent) === 'product') {
            $isWooProduct = true;
        }

        if ($isWooProduct) {
            if (!Settings::get('icais_woo_auto_generate', '0')) {
                return;
            }
        } else {
            if (!Settings::get('icais_auto_generate', '0')) {
                return;
            }
        }

        // Check if the parent post type is excluded from auto-generation
        if ($attachment && $attachment->post_parent > 0) {
            $parentPostType = get_post_type($attachment->post_parent);
            $excludedJson = Settings::get('icais_excluded_post_types', '[]');
            $excluded = json_decode($excludedJson, true);
            if (is_array($excluded) && in_array($parentPostType, $excluded, true)) {
                return;
            }
        }

        // Check if it's an image
        if (!wp_attachment_is_image($attachmentId)) {
            return;
        }

        // Check if skip existing is enabled and alt text already exists
        if (Settings::get('icais_skip_existing', '0')) {
            $existingAlt = get_post_meta($attachmentId, '_wp_attachment_image_alt', true);
            if (!empty($existingAlt)) {
                return;
            }
        }

        // Capture user ID now (cron context won't have a logged-in user)
        $userId = get_current_user_id();

        // Schedule generation in background
        wp_schedule_single_event(time() + 5, 'icais_auto_generate', [$attachmentId, $userId]);

        // Schedule title generation after alt text is generated
        if (Settings::get('icais_title_auto_generate', '0')) {
            wp_schedule_single_event(time() + 10, 'icais_auto_generate_title', [$attachmentId, $userId]);
        }
    }

    /**
     * Generate alt text for attachment
     */
    public static function generateForAttachment($attachmentId, $userId = 0)
    {
        $userId = $userId ?: get_current_user_id();

        // Daily usage limit check
        if (!UsageLimiter::canGenerate($userId, (int) $attachmentId)) {
            return;
        }

        $aiProvider = AIFactory::getProviderForUser($userId);

        if (!$aiProvider) {
            return;
        }

        $imageUrl = wp_get_attachment_url($attachmentId);
        if (!$imageUrl) {
            return;
        }

        $tone = Settings::get('icais_default_tone', 'professional');
        $context = SeoIntegration::buildSeoContext((int) $attachmentId);
        $result = $aiProvider->generateAltText($imageUrl, $tone, $context);

        if (!$result['success']) {
            return;
        }

        // Track daily usage
        UsageLimiter::recordUsage($userId, (int) $attachmentId);

        $previewBeforeAccept = Settings::getBoolean('icais_preview_before_accept', true);
        $initialStatus = $previewBeforeAccept ? 'pending' : 'accepted';

        StatsTracker::recordGeneration([
            'attachment_id' => $attachmentId,
            'original_alt'  => get_post_meta($attachmentId, '_wp_attachment_image_alt', true),
            'alt_text'      => $result['alt_text'],
            'user_id'       => $userId,
            'tone'          => $tone,
            'status'        => $initialStatus,
            'tokens'        => $result['tokens'] ?? 0,
            'cost'          => $result['cost'] ?? 0,
        ]);

        // Auto-accept: write alt text to attachment meta
        if (!$previewBeforeAccept) {
            update_post_meta($attachmentId, '_wp_attachment_image_alt', sanitize_text_field($result['alt_text']));
        }
    }

    /**
     * Auto-generate title for attachment (scheduled cron action)
     */
    public static function generateTitleForAttachment($attachmentId, $userId = 0)
    {
        if (!Settings::get('icais_title_auto_generate', '0')) {
            return;
        }

        if (!wp_attachment_is_image($attachmentId)) {
            return;
        }

        // Skip if title already exists
        if (Settings::get('icais_title_skip_existing', '0')) {
            $existingTitle = get_post_meta($attachmentId, '_icais_generated_title', true);
            if (!empty($existingTitle)) {
                return;
            }
        }

        $userId = $userId ?: get_current_user_id();

        // Daily usage limit check
        if (!UsageLimiter::canGenerate($userId, (int) $attachmentId)) {
            return;
        }

        $aiProvider = AIFactory::getProviderForUser($userId);

        if (!$aiProvider) {
            return;
        }

        $imageUrl = wp_get_attachment_url($attachmentId);
        if (!$imageUrl) {
            return;
        }

        $context = null;
        if (Settings::get('icais_title_context_mode', 'image') === 'context') {
            $context = SeoIntegration::buildSeoContext((int) $attachmentId);
        }

        // Use title-only mode (reads tone and max_length from title settings)
        $aiProvider->setTitleOnlyMode(true);
        $result = $aiProvider->generateAltText($imageUrl, 'professional', $context);

        if (empty($result['success']) || empty($result['alt_text'])) {
            return;
        }

        // Track daily usage
        UsageLimiter::recordUsage($userId, (int) $attachmentId);

        $generatedTitle = AltTextHelper::truncateTitle(trim($result['alt_text']), (int) Settings::get('icais_title_max_length', 30));

        // Check preview setting for titles
        $previewBeforeAccept = Settings::getBoolean('icais_title_preview_before_accept', true);

        if (!$previewBeforeAccept) {
            // Auto-accept: save to meta and apply as post_title
            update_post_meta($attachmentId, '_icais_generated_title', sanitize_text_field($generatedTitle));

            global $wpdb;
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->update($wpdb->posts, ['post_title' => $generatedTitle], ['ID' => $attachmentId], ['%s'], ['%d']);
            clean_post_cache($attachmentId);
        } else {
            // Preview mode: store title in meta for later review, don't apply yet
            update_post_meta($attachmentId, '_icais_generated_title', sanitize_text_field($generatedTitle));
        }
    }

}
