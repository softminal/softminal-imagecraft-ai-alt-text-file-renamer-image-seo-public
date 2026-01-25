<?php

namespace WpAiAltText\Includes\Http\Controllers;

use WpAiAltText\Includes\Models\ApiSetting;
use WpAiAltText\Includes\Models\History;
use WpAiAltText\Includes\Models\Stats;
use WpAiAltText\Includes\Services\AI\AIFactory;
use WpAiAltText\Includes\Services\Settings;

if (!defined('ABSPATH')) {
    exit;
}

class GeneratorController extends Controller
{
    /**
     * Generate alt text for a single image (REST API endpoint)
     */
    public function generate($request)
    {
        $params = $request->get_json_params();
        $attachmentId = (int) ($params['attachment_id'] ?? 0);
        $tone = sanitize_text_field($params['tone'] ?? Settings::get('alttext_default_tone', 'professional'));
        
        $result = $this->generateAltText($attachmentId, $tone);
        
        if (!$result['success']) {
            return $this->response($result, 400);
        }
        
        return $this->response($result);
    }
    
    /**
     * Generate alt text for a single image (internal method, can be called directly)
     * 
     * @param int $attachmentId
     * @param string $tone
     * @param int|null $userId Optional user ID (for batch processing when current user might not be available)
     * @return array
     */
    public function generateAltText(int $attachmentId, string $tone = 'professional', ?int $userId = null): array
    {
        if (!$attachmentId) {
            return [
                'success' => false,
                'message' => 'Attachment ID is required'
            ];
        }
        
        if (!wp_attachment_is_image($attachmentId)) {
            return [
                'success' => false,
                'message' => 'Invalid image attachment'
            ];
        }
        
        // Use provided user ID or fall back to current user ID
        if ($userId === null) {
            $userId = get_current_user_id();
        }
        
        $aiProvider = AIFactory::getProviderForUser($userId);
        if (!$aiProvider) {
            return [
                'success' => false,
                'message' => 'No API key configured. Please set up your API key in settings.'
            ];
        }
        
        $imageUrl = wp_get_attachment_url($attachmentId);
        if (!$imageUrl) {
            return [
                'success' => false,
                'message' => 'Failed to get image URL'
            ];
        }
        
        // Get original alt text
        $originalAlt = get_post_meta($attachmentId, '_wp_attachment_image_alt', true);
        
        // Generate alt text
        $result = $aiProvider->generateAltText($imageUrl, $tone);
        
        if (!$result['success']) {
            return [
                'success' => false,
                'message' => $result['error'] ?? 'Failed to generate alt text'
            ];
        }
        
        // Get API setting for provider
        $apiSetting = ApiSetting::getActive($userId);
        
        // Determine initial status based on preview setting
        // WordPress stores booleans as '1'/'0', so we need to properly check the value
        $previewSetting = Settings::get('alttext_preview_before_accept', '1');
        $previewBeforeAccept = ($previewSetting === '1' || $previewSetting === 1 || $previewSetting === true);
        $initialStatus = $previewBeforeAccept ? 'pending' : 'accepted';
        
        // Save to history
        $history = History::create([
            'attachment_id' => $attachmentId,
            'original_alt' => $originalAlt,
            'generated_alt' => $result['alt_text'],
            'user_id' => $userId,
            'api_provider' => $apiSetting ? $apiSetting->api_provider : 'unknown',
            'tone' => $tone,
            'status' => $initialStatus,
            'tokens_used' => $result['tokens'] ?? 0,
            'cost_estimate' => $result['cost'] ?? 0
        ]);
        
        // Auto-accept if preview is disabled
        if (!$previewBeforeAccept) {
            // Update attachment alt text (sanitize for safety)
            $altText = sanitize_text_field($result['alt_text']);
            update_post_meta($attachmentId, '_wp_attachment_image_alt', $altText);
            
            // Update stats
            $stats = Stats::getOrCreate($userId, date('Y-m-d'));
            $stats->increment('images_processed');
            $stats->increment('images_accepted');
            $stats->total_cost_estimate = (float) $stats->total_cost_estimate + ($result['cost'] ?? 0);
            if ($apiSetting) {
                $stats->api_provider = $apiSetting->api_provider;
            }
            $stats->save();
        } else {
            // Update stats (without incrementing accepted count)
            $stats = Stats::getOrCreate($userId, date('Y-m-d'));
            $stats->increment('images_processed');
            $stats->total_cost_estimate = (float) $stats->total_cost_estimate + ($result['cost'] ?? 0);
            if ($apiSetting) {
                $stats->api_provider = $apiSetting->api_provider;
            }
            $stats->save();
        }
        
        return [
            'success' => true,
            'alt_text' => $result['alt_text'],
            'history_id' => $history->id,
            'tokens' => $result['tokens'] ?? 0,
            'cost' => $result['cost'] ?? 0
        ];
    }
    
    /**
     * Accept generated alt text
     */
    public function accept($request)
    {
        $params = $request->get_json_params();
        $historyId = (int) ($params['history_id'] ?? 0);
        $altText = sanitize_text_field($params['alt_text'] ?? '');
        
        if (!$historyId) {
            return $this->response([
                'success' => false,
                'message' => 'History ID is required'
            ], 400);
        }
        
        $history = History::find($historyId);
        if (!$history) {
            return $this->response([
                'success' => false,
                'message' => 'History not found'
            ], 404);
        }
        
        // Use provided alt text or generated one
        $finalAltText = !empty($altText) ? $altText : $history->generated_alt;
        
        // Update attachment alt text
        update_post_meta($history->attachment_id, '_wp_attachment_image_alt', $finalAltText);
        
        // Update history status
        $history->status = 'accepted';
        if (!empty($altText)) {
            $history->generated_alt = $finalAltText;
        }
        $history->save();
        
        // Update stats
        $stats = Stats::getOrCreate($history->user_id, date('Y-m-d'));
        $stats->increment('images_accepted');
        $stats->save();
        
        return $this->response([
            'success' => true,
            'message' => 'Alt text saved successfully'
        ]);
    }
    
    /**
     * Reject generated alt text
     */
    public function reject($request)
    {
        $params = $request->get_json_params();
        $historyId = (int) ($params['history_id'] ?? 0);
        
        if (!$historyId) {
            return $this->response([
                'success' => false,
                'message' => 'History ID is required'
            ], 400);
        }
        
        $history = History::find($historyId);
        if (!$history) {
            return $this->response([
                'success' => false,
                'message' => 'History not found'
            ], 404);
        }
        
        $history->status = 'rejected';
        $history->save();
        
        // Update stats
        $stats = Stats::getOrCreate($history->user_id, date('Y-m-d'));
        $stats->increment('images_rejected');
        $stats->save();
        
        return $this->response([
            'success' => true,
            'message' => 'Alt text rejected'
        ]);
    }
}
