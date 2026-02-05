<?php

namespace SmartAltTextAi\Includes;

use SmartAltTextAi\Includes\Models\Stats;
use SmartAltTextAi\Includes\Services\Settings;
use SmartAltTextAi\Includes\Services\AI\AIFactory;
use SmartAltTextAi\Includes\Services\FileRenamer;

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

        // Schedule generation in background
        wp_schedule_single_event(time() + 5, 'satai_auto_generate', [$attachmentId]);

        // Schedule rename after alt text is generated
        if (Settings::get('satai_auto_rename', false)) {
            wp_schedule_single_event(time() + 15, 'satai_auto_rename', [$attachmentId]);
        }
    }

    /**
     * Generate alt text for attachment
     */
    public static function generateForAttachment($attachmentId)
    {
        $userId = get_current_user_id();
        $aiProvider = AIFactory::getProviderForUser($userId);

        if (!$aiProvider) {
            return;
        }

        $imageUrl = wp_get_attachment_url($attachmentId);
        if (!$imageUrl) {
            return;
        }

        $tone = Settings::get('satai_default_tone', 'professional');
        $result = $aiProvider->generateAltText($imageUrl, $tone);

        if (!$result['success']) {
            return;
        }

        // Save to history
        $apiSetting = \SmartAltTextAi\Includes\Models\ApiSetting::getActive($userId);
        if ($apiSetting) {
            // WordPress stores booleans as '1'/'0', so we need to properly check the value
            $previewSetting = Settings::get('satai_preview_before_accept', '1');
            $previewBeforeAccept = ($previewSetting === '1' || $previewSetting === 1 || $previewSetting === true);

            $history = \SmartAltTextAi\Includes\Models\History::create([
                'attachment_id' => $attachmentId,
                'original_alt' => get_post_meta($attachmentId, '_wp_attachment_image_alt', true),
                'generated_alt' => $result['alt_text'],
                'user_id' => $userId,
                'api_provider' => $apiSetting->api_provider,
                'tone' => $tone,
                'status' => $previewBeforeAccept ? 'pending' : 'accepted',
                'tokens_used' => $result['tokens'] ?? 0,
                'cost_estimate' => $result['cost'] ?? 0
            ]);

            // Auto-accept if preview is disabled
            if (!$previewBeforeAccept) {
                update_post_meta($attachmentId, '_wp_attachment_image_alt', $result['alt_text']);
                $history->status = 'accepted';
                $history->save();

                // Update stats
                $stats = Stats::getOrCreate($userId, gmdate('Y-m-d'));
                $stats->increment('images_processed');
                $stats->increment('images_accepted');
                $stats->total_cost_estimate = (float) $stats->total_cost_estimate + ($result['cost'] ?? 0);
                $stats->api_provider = $apiSetting->api_provider;
                $stats->save();
            }
        }
    }

    public static function renameOnUpload($attachmentId)
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
