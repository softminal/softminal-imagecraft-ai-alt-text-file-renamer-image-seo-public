<?php

namespace SmartAltTextAi\Includes\Http\Controllers;

use SmartAltTextAi\Includes\Models\History;
use SmartAltTextAi\Includes\Services\AI\AIFactory;
use SmartAltTextAi\Includes\Services\Settings;
use SmartAltTextAi\Includes\Services\StatsTracker;
use SmartAltTextAi\Includes\Services\SeoIntegration;
use SmartAltTextAi\Includes\Services\WooCommerce;

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
        $tone = sanitize_text_field($params['tone'] ?? Settings::get('satai_default_tone', 'professional'));

        // Only use WooCommerce product context when product_id is explicitly sent
        // (i.e., user is on a product edit page, not browsing the Media Library directly)
        $context = null;
        $productId = (int) ($params['product_id'] ?? 0);
        if ($productId > 0) {
            $context = $this->detectWooCommerceContext($attachmentId, $productId);

            // If product context found and no explicit tone was provided, use WooCommerce tone
            if ($context !== null && !isset($params['tone'])) {
                $tone = Settings::get('satai_woo_default_tone', 'woo_seo');
            }
        }

        // Lightweight post/page title context (no aggressive post-processing like WooCommerce)
        if ($context === null) {
            $postTitle = sanitize_text_field($params['post_title'] ?? '');
            if (!empty($postTitle)) {
                $context = 'Page: ' . $postTitle;
            }
        }

        // Append SEO focus keyphrase context if available
        // Use explicit post_id from the editor if provided (more reliable than post_parent)
        $postId = (int) ($params['post_id'] ?? 0);
        $seoContext = SeoIntegration::buildSeoContext($attachmentId, $postId);
        if ($seoContext !== null) {
            $context = $context !== null ? $context . ' | ' . $seoContext : $seoContext;
        }

        $result = $this->generateAltText($attachmentId, $tone, null, $context);

        if (!$result['success']) {
            return $this->response($result, 400);
        }

        // Post-process: ensure product name is included in the alt text
        if ($context !== null && preg_match('/^Product:\s*(.+?)(?:\s*\||$)/', $context, $matches)) {
            $productName = trim($matches[1]);
            if (stripos($result['alt_text'], $productName) === false) {
                $maxLength = (int) Settings::get('satai_max_length', 150);
                $newAlt = $productName . ' - ' . $result['alt_text'];
                if (mb_strlen($newAlt) > $maxLength) {
                    $available = $maxLength - mb_strlen($productName) - 3;
                    if ($available > 20) {
                        $newAlt = $productName . ' - ' . mb_substr($result['alt_text'], 0, $available);
                    }
                }
                $result['alt_text'] = $newAlt;

                // Update the saved history record
                if (!empty($result['history_id'])) {
                    $history = History::find($result['history_id']);
                    if ($history) {
                        $history->generated_alt = $newAlt;
                        $history->save();
                    }
                }

                // Update attachment meta if auto-accepted (preview disabled)
                $previewSetting = Settings::get('satai_preview_before_accept', '1');
                if ($previewSetting !== '1' && $previewSetting !== 1 && $previewSetting !== true) {
                    update_post_meta($attachmentId, '_wp_attachment_image_alt', sanitize_text_field($newAlt));
                }
            }
        }

        return $this->response($result);
    }
    
    /**
     * Generate alt text for a single image (internal method, can be called directly)
     *
     * @param int $attachmentId
     * @param string $tone
     * @param int|null $userId Optional user ID (for batch processing when current user might not be available)
     * @param string|null $context Optional product/page context for enhanced alt text
     * @return array
     */
    public function generateAltText(int $attachmentId, string $tone = 'professional', ?int $userId = null, ?string $context = null): array
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
        $result = $aiProvider->generateAltText($imageUrl, $tone, $context);
        
        if (!$result['success']) {
            return [
                'success' => false,
                'message' => $result['error'] ?? 'Failed to generate alt text'
            ];
        }
        
        // Determine initial status based on preview setting
        $previewSetting = Settings::get('satai_preview_before_accept', '1');
        $previewBeforeAccept = ($previewSetting === '1' || $previewSetting === 1 || $previewSetting === true);
        $initialStatus = $previewBeforeAccept ? 'pending' : 'accepted';

        // Record history and stats in one call
        $history = StatsTracker::recordGeneration([
            'attachment_id' => $attachmentId,
            'original_alt'  => $originalAlt,
            'alt_text'      => $result['alt_text'],
            'user_id'       => $userId,
            'tone'          => $tone,
            'status'        => $initialStatus,
            'tokens'        => $result['tokens'] ?? 0,
            'cost'          => $result['cost'] ?? 0,
        ]);

        // Auto-accept: also write alt text to attachment meta
        if (!$previewBeforeAccept) {
            update_post_meta($attachmentId, '_wp_attachment_image_alt', sanitize_text_field($result['alt_text']));
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

        StatsTracker::recordAccept($history->user_id);

        return $this->response([
            'success' => true,
            'message' => 'Alt text saved successfully'
        ]);
    }

    /**
     * Accept multiple generated alt texts (bulk save from review modal)
     */
    public function acceptBulk($request)
    {
        $params = $request->get_json_params();
        $items = $params['items'] ?? [];

        if (empty($items) || !is_array($items)) {
            return $this->response([
                'success' => false,
                'message' => 'Items array is required'
            ], 400);
        }

        $saved = 0;
        $errors = [];

        foreach ($items as $item) {
            $historyId = (int) ($item['history_id'] ?? 0);
            $altText = isset($item['alt_text']) ? sanitize_text_field($item['alt_text']) : '';

            if (!$historyId) {
                continue;
            }

            $history = History::find($historyId);
            if (!$history) {
                $errors[] = sprintf('History %d not found', $historyId);
                continue;
            }

            $finalAltText = !empty($altText) ? $altText : $history->generated_alt;
            update_post_meta($history->attachment_id, '_wp_attachment_image_alt', $finalAltText);
            $history->status = 'accepted';
            if (!empty($altText)) {
                $history->generated_alt = $finalAltText;
            }
            $history->save();

            StatsTracker::recordAccept($history->user_id);
            $saved++;
        }

        return $this->response([
            'success' => true,
            'message' => sprintf('%d alt text(s) saved successfully', $saved),
            'saved_count' => $saved,
            'errors' => $errors
        ]);
    }

    /**
     * Update attachment alt text directly (no history required)
     */
    public function updateAlt($request)
    {
        $params = $request->get_json_params();
        $attachmentId = (int) ($params['attachment_id'] ?? 0);
        $altText = isset($params['alt_text']) ? sanitize_text_field($params['alt_text']) : '';

        if (!$attachmentId) {
            return $this->response([
                'success' => false,
                'message' => 'Attachment ID is required'
            ], 400);
        }

        if (!wp_attachment_is_image($attachmentId)) {
            return $this->response([
                'success' => false,
                'message' => 'Invalid image attachment'
            ], 400);
        }

        update_post_meta($attachmentId, '_wp_attachment_image_alt', $altText);

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

        StatsTracker::recordReject($history->user_id);
        
        return $this->response([
            'success' => true,
            'message' => 'Alt text rejected'
        ]);
    }

    /**
     * Detect WooCommerce product context for an attachment
     *
     * Checks if the attachment belongs to a WooCommerce product via:
     * 0. Explicit product_id hint from the frontend (most reliable - sent when on product edit page)
     * 1. post_parent is a product
     * 2. Attachment is a product's featured image (_thumbnail_id)
     * 3. Attachment is in a product's gallery (_product_image_gallery)
     *
     * @param int $attachmentId
     * @param int $productIdHint Optional product ID hint from the frontend
     * @return string|null Product context string or null
     */
    private function detectWooCommerceContext(int $attachmentId, int $productIdHint = 0): ?string
    {
        if (!WooCommerce::isActive()) {
            return null;
        }

        $product = null;

        // Check 0: Explicit product_id from the frontend (user is on product edit page)
        if ($productIdHint > 0 && get_post_type($productIdHint) === 'product') {
            $product = wc_get_product($productIdHint);
        }

        // Check 1: post_parent is a product
        if (!$product) {
            $attachment = get_post($attachmentId);
            if ($attachment && $attachment->post_parent > 0) {
                $parentType = get_post_type($attachment->post_parent);
                if ($parentType === 'product') {
                    $product = wc_get_product($attachment->post_parent);
                }
            }
        }

        // Check 2: Attachment is a product's featured image
        if (!$product) {
            global $wpdb;
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $foundProductId = $wpdb->get_var($wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta}
                WHERE meta_key = '_thumbnail_id' AND meta_value = %s
                LIMIT 1",
                (string) $attachmentId
            ));
            if ($foundProductId && get_post_type($foundProductId) === 'product') {
                $product = wc_get_product($foundProductId);
            }
        }

        // Check 3: Attachment is in a product's gallery
        if (!$product) {
            global $wpdb;
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $foundProductId = $wpdb->get_var($wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta}
                WHERE meta_key = '_product_image_gallery' AND meta_value LIKE %s
                LIMIT 1",
                '%' . $wpdb->esc_like((string) $attachmentId) . '%'
            ));
            if ($foundProductId && get_post_type($foundProductId) === 'product') {
                $product = wc_get_product($foundProductId);
            }
        }

        if (!$product) {
            return null;
        }

        return WooCommerce::buildProductContextPrompt($product);
    }
}
