<?php

namespace WpAiAltText\Includes;

use WpAiAltText\Includes\Models\Stats;
use WpAiAltText\Includes\Services\Settings;
use WpAiAltText\Includes\Services\AI\AIFactory;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Auto-generate alt text on image upload
 */
class AutoGenerate
{
    public static function init()
    {
        add_action('add_attachment', [self::class, 'onAttachmentAdded']);
    }
    
    /**
     * Handle new attachment upload
     */
    public static function onAttachmentAdded($attachmentId)
    {
        // Check if auto-generate is enabled
        if (!Settings::get('alttext_auto_generate', false)) {
            return;
        }
        
        // Check if it's an image
        if (!wp_attachment_is_image($attachmentId)) {
            return;
        }
        
        // Check if skip existing is enabled and alt text already exists
        if (Settings::get('alttext_skip_existing', false)) {
            $existingAlt = get_post_meta($attachmentId, '_wp_attachment_image_alt', true);
            if (!empty($existingAlt)) {
                return;
            }
        }
        
        // Schedule generation in background
        wp_schedule_single_event(time() + 5, 'alttext_auto_generate', [$attachmentId]);
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
        
        $tone = Settings::get('alttext_default_tone', 'professional');
        $result = $aiProvider->generateAltText($imageUrl, $tone);
        
        if (!$result['success']) {
            error_log('Auto-generate failed for attachment ' . $attachmentId . ': ' . ($result['error'] ?? 'Unknown error'));
            return;
        }
        
        // Save to history
        $apiSetting = \WpAiAltText\Includes\Models\ApiSetting::getActive($userId);
        if ($apiSetting) {
            // WordPress stores booleans as '1'/'0', so we need to properly check the value
            $previewSetting = Settings::get('alttext_preview_before_accept', '1');
            $previewBeforeAccept = ($previewSetting === '1' || $previewSetting === 1 || $previewSetting === true);
            
            $history = \WpAiAltText\Includes\Models\History::create([
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
                $stats = Stats::getOrCreate($userId, date('Y-m-d'));
                $stats->increment('images_processed');
                $stats->increment('images_accepted');
                $stats->total_cost_estimate = (float) $stats->total_cost_estimate + ($result['cost'] ?? 0);
                $stats->api_provider = $apiSetting->api_provider;
                $stats->save();
            }
        }
    }
}

// Register the hook
add_action('alttext_auto_generate', [AutoGenerate::class, 'generateForAttachment']);
