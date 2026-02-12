<?php

namespace SmartAltTextAi\Includes;

use SmartAltTextAi\Includes\Services\Settings;
use SmartAltTextAi\Includes\Services\StatsTracker;
use SmartAltTextAi\Includes\Services\AI\AIFactory;
use SmartAltTextAi\Includes\Services\FileRenamer;
use SmartAltTextAi\Includes\Services\SeoIntegration;

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
        add_action('satai_auto_generate', [self::class, 'generateForAttachment']);
        add_action('satai_auto_rename', [self::class, 'renameOnUpload']);
    }

    /**
     * Handle new attachment upload
     */
    public static function onAttachmentAdded($attachmentId)
    {
        // Check if auto-generate is enabled
        if (!Settings::get('satai_auto_generate', false)) {
            return;
        }

        // Check if it's an image
        if (!wp_attachment_is_image($attachmentId)) {
            return;
        }

        // Check if skip existing is enabled and alt text already exists
        if (Settings::get('satai_skip_existing', false)) {
            $existingAlt = get_post_meta($attachmentId, '_wp_attachment_image_alt', true);
            if (!empty($existingAlt)) {
                return;
            }
        }

        // Capture user ID now (cron context won't have a logged-in user)
        $userId = get_current_user_id();

        // Schedule generation in background
        wp_schedule_single_event(time() + 5, 'satai_auto_generate', [$attachmentId, $userId]);

        // Schedule rename after alt text is generated
        if (Settings::get('satai_auto_rename', false)) {
            wp_schedule_single_event(time() + 15, 'satai_auto_rename', [$attachmentId, $userId]);
        }
    }

    /**
     * Generate alt text for attachment
     */
    public static function generateForAttachment($attachmentId, $userId = 0)
    {
        $userId = $userId ?: get_current_user_id();
        $aiProvider = AIFactory::getProviderForUser($userId);

        if (!$aiProvider) {
            return;
        }

        $imageUrl = wp_get_attachment_url($attachmentId);
        if (!$imageUrl) {
            return;
        }

        $tone = Settings::get('satai_default_tone', 'professional');
        $context = SeoIntegration::buildSeoContext((int) $attachmentId);
        $result = $aiProvider->generateAltText($imageUrl, $tone, $context);

        if (!$result['success']) {
            return;
        }

        $previewSetting = Settings::get('satai_preview_before_accept', '1');
        $previewBeforeAccept = ($previewSetting === '1' || $previewSetting === 1 || $previewSetting === true);
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
            update_post_meta($attachmentId, '_wp_attachment_image_alt', $result['alt_text']);
        }
    }

    public static function renameOnUpload($attachmentId, $userId = 0)
    {
        if (!Settings::get('satai_auto_rename', false)) {
            return;
        }

        if (!wp_attachment_is_image($attachmentId)) {
            return;
        }

        $renamer = new FileRenamer();
        $renamer->rename($attachmentId);
    }
}
