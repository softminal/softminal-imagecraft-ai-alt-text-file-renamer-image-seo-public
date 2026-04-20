<?php

namespace Softminal\ImageCraft\Http\Controllers;

use Softminal\ImageCraft\Models\History;
use Softminal\ImageCraft\Services\AI\AIFactory;
use Softminal\ImageCraft\Services\FileBird;
use Softminal\ImageCraft\Services\Settings;
use Softminal\ImageCraft\Services\StatsTracker;
use Softminal\ImageCraft\Services\SeoIntegration;
use Softminal\ImageCraft\Services\UsageLimiter;
use Softminal\ImageCraft\Services\WooCommerce;

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
        $tone = sanitize_text_field($params['tone'] ?? Settings::get('icais_default_tone', 'professional'));

        // Daily usage limit check
        if ($attachmentId && !UsageLimiter::canGenerate(get_current_user_id(), $attachmentId)) {
            return $this->response(UsageLimiter::limitReachedError(), 429);
        }

        // Integration source check
        $source = sanitize_text_field($params['source'] ?? '');
        if (!self::isIntegrationAllowed($source)) {
            return $this->response([
                'success' => false,
                'message' => __('This integration is disabled.', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            ], 403);
        }

        // Only use WooCommerce product context when product_id is explicitly sent
        // (i.e., user is on a product edit page, not browsing the Media Library directly)
        $context = null;
        $productId = (int) ($params['product_id'] ?? 0);
        if ($productId > 0) {
            $context = $this->detectWooCommerceContext($attachmentId, $productId);

            // If product context found and no explicit tone was provided, use WooCommerce tone
            if ($context !== null && !isset($params['tone'])) {
                $tone = Settings::get('icais_woo_default_tone', 'woo_seo');
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

        // Append FileBird folder context if available and integration is enabled
        if (Settings::get('icais_integration_filebird', '1') === '1') {
            $folderContext = FileBird::buildFolderContext($attachmentId);
            if ($folderContext !== null) {
                $context = $context !== null ? $context . ' | ' . $folderContext : $folderContext;
            }
        }

        // Title-only generation mode (called from media modal "Generate Title" button)
        $titleOnly = !empty($params['title_only']);
        if ($titleOnly) {
            // Kadence block titles: generate from image only (no page/SEO context)
            if ($source === 'kadence') {
                return $this->generateTitleOnly($attachmentId, null);
            }
            $titleContext = Settings::get('icais_title_context_mode', 'image') === 'context' ? $context : null;
            return $this->generateTitleOnly($attachmentId, $titleContext);
        }

        // Link title generation mode (called from Kadence "Generate Link Title" button)
        // Link titles are general-purpose (accessibility), generate from image only
        $linkTitleOnly = !empty($params['link_title_only']);
        if ($linkTitleOnly) {
            return $this->generateLinkTitle($attachmentId, null);
        }

        // Filename generation mode (called from media modal "Rename File" button)
        $filenameOnly = !empty($params['filename_only']);
        if ($filenameOnly) {
            return $this->generateFilenameAndRename($attachmentId, $context);
        }

        $result = $this->generateAltText($attachmentId, $tone, null, $context);

        if (!$result['success']) {
            return $this->response($result, 400);
        }

        // Post-process: ensure product name is included in the alt text
        if ($context !== null && preg_match('/^Product:\s*(.+?)(?:\s*\||$)/', $context, $matches)) {
            $productName = trim($matches[1]);
            if (stripos($result['alt_text'], $productName) === false) {
                $maxLength = (int) Settings::get('icais_max_length', 125);
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
                $previewSetting = Settings::get('icais_preview_before_accept', '1');
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
    /**
     * @param bool $forceAccept When true, always save alt text to meta (skip preview setting). Used by batch processing.
     */
    public function generateAltText(int $attachmentId, string $tone = 'professional', ?int $userId = null, ?string $context = null, bool $forceAccept = false): array
    {
        if (!$attachmentId) {
            return [
                'success' => false,
                'message' => __('Attachment ID is required', 'imagecraft-ai-alt-text-file-renamer-image-seo')
            ];
        }

        if (!wp_attachment_is_image($attachmentId)) {
            return [
                'success' => false,
                'message' => __('Invalid image attachment', 'imagecraft-ai-alt-text-file-renamer-image-seo')
            ];
        }

        // Use provided user ID or fall back to current user ID
        if ($userId === null) {
            $userId = get_current_user_id();
        }

        // Daily usage limit check
        if (!UsageLimiter::canGenerate($userId, $attachmentId)) {
            return UsageLimiter::limitReachedError();
        }

        $aiProvider = AIFactory::getProviderForUser($userId);
        if (!$aiProvider) {
            return [
                'success' => false,
                'message' => __('No API key configured. Please set up your API key in settings.', 'imagecraft-ai-alt-text-file-renamer-image-seo')
            ];
        }

        $imageUrl = wp_get_attachment_url($attachmentId);
        if (!$imageUrl) {
            return [
                'success' => false,
                'message' => __('Failed to get image URL', 'imagecraft-ai-alt-text-file-renamer-image-seo')
            ];
        }
        
        // Get original alt text
        $originalAlt = get_post_meta($attachmentId, '_wp_attachment_image_alt', true);
        
        // Generate alt text
        $result = $aiProvider->generateAltText($imageUrl, $tone, $context);
        
        if (!$result['success']) {
            return [
                'success' => false,
                'message' => $result['error'] ?? __('Failed to generate alt text', 'imagecraft-ai-alt-text-file-renamer-image-seo')
            ];
        }
        
        // Determine initial status — batch always auto-accepts, single respects preview setting
        $previewSetting = Settings::get('icais_preview_before_accept', '1');
        $previewBeforeAccept = !$forceAccept && ($previewSetting === '1' || $previewSetting === 1 || $previewSetting === true);
        $initialStatus = $previewBeforeAccept ? 'pending' : 'accepted';

        // Track daily usage
        UsageLimiter::recordUsage($userId, $attachmentId);

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

        // Auto-accept: write alt text to attachment meta
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
        $generatedTitle = sanitize_text_field($params['generated_title'] ?? '');

        if (!$historyId) {
            return $this->response([
                'success' => false,
                'message' => __('History ID is required', 'imagecraft-ai-alt-text-file-renamer-image-seo')
            ], 400);
        }

        $history = History::find($historyId);
        if (!$history) {
            return $this->response([
                'success' => false,
                'message' => __('History not found', 'imagecraft-ai-alt-text-file-renamer-image-seo')
            ], 404);
        }

        // Verify ownership
        if ((int) $history->user_id !== get_current_user_id()) {
            return $this->response([
                'success' => false,
                'message' => __('Not authorized', 'imagecraft-ai-alt-text-file-renamer-image-seo')
            ], 403);
        }

        // Use provided alt text or generated one
        $finalAltText = !empty($altText) ? $altText : sanitize_text_field($history->generated_alt);

        // skip_meta_update: when true, only update history/stats without touching media library metadata.
        // Used by block-level integrations (e.g. Kadence) where alt/title are block attributes, not media metadata.
        $skipMetaUpdate = !empty($params['skip_meta_update']);

        if (!$skipMetaUpdate) {
            // Update attachment alt text
            update_post_meta($history->attachment_id, '_wp_attachment_image_alt', $finalAltText);

            // Save generated title if provided
            if (!empty($generatedTitle)) {
                update_post_meta($history->attachment_id, '_icais_generated_title', $generatedTitle);
            }
        }

        // Update history status
        $history->status = 'accepted';
        if (!empty($altText)) {
            $history->generated_alt = $finalAltText;
        }
        if (!$history->save()) {
            return $this->response([
                'success' => false,
                'message' => __('Failed to update history record', 'imagecraft-ai-alt-text-file-renamer-image-seo')
            ], 500);
        }

        StatsTracker::recordAccept($history->user_id);

        return $this->response([
            'success' => true,
            'message' => __('Alt text saved successfully', 'imagecraft-ai-alt-text-file-renamer-image-seo')
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
                'message' => __('Items array is required', 'imagecraft-ai-alt-text-file-renamer-image-seo')
            ], 400);
        }

        $saved = 0;
        $errors = [];

        // Collect valid history IDs and batch-fetch them in a single query
        $idToAlt = [];
        $idToTitle = [];
        foreach ($items as $item) {
            $historyId = (int) ($item['history_id'] ?? 0);
            if ($historyId > 0) {
                $idToAlt[$historyId] = isset($item['alt_text']) ? sanitize_text_field($item['alt_text']) : '';
                $idToTitle[$historyId] = isset($item['generated_title']) ? sanitize_text_field($item['generated_title']) : '';
            }
        }

        $histories = [];
        if (!empty($idToAlt)) {
            $fetched = History::where('id', 'IN', array_keys($idToAlt))->get();
            foreach ($fetched as $h) {
                $histories[(int) $h->id] = $h;
            }
        }

        global $wpdb;
        $historyTable = esc_sql(History::table());
        $currentUserId = get_current_user_id();

        foreach ($idToAlt as $historyId => $altText) {
            if (!isset($histories[$historyId])) {
                /* translators: %d: history record ID */
                $errors[] = sprintf(__('History %d not found', 'imagecraft-ai-alt-text-file-renamer-image-seo'), $historyId);
                continue;
            }

            $history = $histories[$historyId];

            // Verify ownership
            if ((int) $history->user_id !== $currentUserId) {
                /* translators: %d: history record ID */
                $errors[] = sprintf(__('Not authorized for history %d', 'imagecraft-ai-alt-text-file-renamer-image-seo'), $historyId);
                continue;
            }

            $finalAltText = !empty($altText) ? $altText : sanitize_text_field($history->generated_alt);
            update_post_meta($history->attachment_id, '_wp_attachment_image_alt', $finalAltText);

            // Save generated title if provided
            $title = $idToTitle[$historyId] ?? '';
            if (!empty($title)) {
                update_post_meta($history->attachment_id, '_icais_generated_title', $title);
            }

            // Direct DB update to avoid per-row cache bumps
            $updateData = ['status' => 'accepted'];
            if (!empty($altText)) {
                $updateData['generated_alt'] = $finalAltText;
            }
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bulk operation; cache invalidated once after loop
            $wpdb->update($historyTable, $updateData, ['id' => $historyId]);

            StatsTracker::recordAccept($history->user_id);
            $saved++;
        }

        // Single cache invalidation after all updates
        if ($saved > 0) {
            History::invalidateCache();
        }

        return $this->response([
            'success' => true,
            /* translators: %d: number of saved alt texts */
            'message' => sprintf(__('%d alt text(s) saved successfully', 'imagecraft-ai-alt-text-file-renamer-image-seo'), $saved),
            'saved_count' => $saved,
            'errors' => $errors
        ]);
    }

    /**
     * Generate title only (no alt text generation).
     * Used by the media modal "Generate Title" button when generate_title setting is '0'.
     */
    private function generateTitleOnly(int $attachmentId, ?string $context): \WP_REST_Response
    {
        $userId = get_current_user_id();
        $aiProvider = AIFactory::getProviderForUser($userId);

        if (!$aiProvider) {
            return $this->response([
                'success' => false,
                'message' => __('No API key configured.', 'imagecraft-ai-alt-text-file-renamer-image-seo')
            ], 400);
        }

        $imageUrl = wp_get_attachment_url($attachmentId);
        if (!$imageUrl) {
            return $this->response([
                'success' => false,
                'message' => __('Failed to get image URL', 'imagecraft-ai-alt-text-file-renamer-image-seo')
            ], 400);
        }

        // Skip if title already exists and skip_existing is enabled
        $skipExisting = Settings::get('icais_title_skip_existing', '0');
        if ($skipExisting === '1' || $skipExisting === 1 || $skipExisting === true) {
            $existingTitle = (string) get_post_meta($attachmentId, '_icais_generated_title', true);
            if (!empty($existingTitle)) {
                return $this->response([
                    'success' => true,
                    'generated_title' => $existingTitle,
                    'skipped' => true,
                    'message' => __('Title already exists', 'imagecraft-ai-alt-text-file-renamer-image-seo')
                ]);
            }
        }

        // Use title-only mode (reads tone and max_length from title settings)
        $aiProvider->setTitleOnlyMode(true);
        $result = $aiProvider->generateAltText($imageUrl, 'professional', $context);

        if (!$result['success']) {
            return $this->response([
                'success' => false,
                'message' => $result['error'] ?? __('Failed to generate title', 'imagecraft-ai-alt-text-file-renamer-image-seo')
            ], 400);
        }

        $generatedTitle = trim($result['alt_text'] ?? '');

        // Track daily usage
        UsageLimiter::recordUsage($userId, $attachmentId);

        // Cap at configured max length
        $maxLength = (int) Settings::get('icais_title_max_length', 30);
        if (mb_strlen($generatedTitle) > $maxLength) {
            $generatedTitle = mb_substr($generatedTitle, 0, $maxLength);
            $generatedTitle = preg_replace('/\s+\S*$/', '', $generatedTitle);
        }

        // Check preview before accept setting for titles
        $previewSetting = Settings::get('icais_title_preview_before_accept', '1');
        $previewBeforeAccept = ($previewSetting === '1' || $previewSetting === 1 || $previewSetting === true);

        if (!$previewBeforeAccept) {
            // Auto-accept: save to meta and apply as post_title immediately
            update_post_meta($attachmentId, '_icais_generated_title', sanitize_text_field($generatedTitle));
            global $wpdb;
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->update($wpdb->posts, ['post_title' => $generatedTitle], ['ID' => $attachmentId], ['%s'], ['%d']);
            clean_post_cache($attachmentId);
        }

        return $this->response([
            'success' => true,
            'generated_title' => $generatedTitle,
            'preview' => $previewBeforeAccept,
        ]);
    }

    /**
     * Generate link title for an image's anchor element.
     * Used by block-level integrations (e.g. Kadence) where linkTitle is a block attribute.
     */
    private function generateLinkTitle(int $attachmentId, ?string $context): \WP_REST_Response
    {
        $userId = get_current_user_id();
        $aiProvider = \Softminal\ImageCraft\Services\AI\AIFactory::getProviderForUser($userId);

        if (!$aiProvider) {
            return $this->response([
                'success' => false,
                'message' => __('No API key configured.', 'imagecraft-ai-alt-text-file-renamer-image-seo')
            ], 400);
        }

        $imageUrl = wp_get_attachment_url($attachmentId);
        if (!$imageUrl) {
            return $this->response([
                'success' => false,
                'message' => __('Failed to get image URL', 'imagecraft-ai-alt-text-file-renamer-image-seo')
            ], 400);
        }

        $aiProvider->setLinkTitleMode(true);
        $result = $aiProvider->generateAltText($imageUrl, 'professional', $context);

        if (!$result['success']) {
            return $this->response([
                'success' => false,
                'message' => $result['error'] ?? __('Failed to generate link title', 'imagecraft-ai-alt-text-file-renamer-image-seo')
            ], 400);
        }

        $generatedTitle = trim($result['alt_text'] ?? '');

        // Track daily usage
        UsageLimiter::recordUsage($userId, $attachmentId);

        $maxLength = (int) Settings::get('icais_title_max_length', 80);
        if (mb_strlen($generatedTitle) > $maxLength) {
            $generatedTitle = mb_substr($generatedTitle, 0, $maxLength);
            $generatedTitle = preg_replace('/\s+\S*$/', '', $generatedTitle);
        }

        return $this->response([
            'success' => true,
            'generated_title' => $generatedTitle,
            'preview' => true,
        ]);
    }

    /**
     * Generate an AI filename and rename the file.
     */
    private function generateFilenameAndRename(int $attachmentId, ?string $context): \WP_REST_Response
    {
        $userId = get_current_user_id();
        $aiProvider = \Softminal\ImageCraft\Services\AI\AIFactory::getProviderForUser($userId);

        if (!$aiProvider) {
            return $this->response([
                'success' => false,
                'message' => __('No API key configured.', 'imagecraft-ai-alt-text-file-renamer-image-seo')
            ], 400);
        }

        $imageUrl = wp_get_attachment_url($attachmentId);
        if (!$imageUrl) {
            return $this->response([
                'success' => false,
                'message' => __('Failed to get image URL', 'imagecraft-ai-alt-text-file-renamer-image-seo')
            ], 400);
        }

        // Use filename mode
        $aiProvider->setFilenameMode(true);
        $result = $aiProvider->generateAltText($imageUrl, 'professional', $context);

        if (!$result['success']) {
            return $this->response([
                'success' => false,
                'message' => $result['error'] ?? __('Failed to generate filename', 'imagecraft-ai-alt-text-file-renamer-image-seo')
            ], 400);
        }

        // Track daily usage
        UsageLimiter::recordUsage(get_current_user_id(), $attachmentId);

        // Clean the AI response into a valid filename slug
        $aiFilename = trim($result['alt_text'] ?? '');
        $aiFilename = strtolower($aiFilename);
        $aiFilename = remove_accents($aiFilename);
        $aiFilename = preg_replace('/[^a-z0-9\s-]/', '', $aiFilename);
        $aiFilename = preg_replace('/\s+/', '-', $aiFilename);
        $aiFilename = preg_replace('/-+/', '-', $aiFilename);
        $aiFilename = trim($aiFilename, '-');

        $maxLength = (int) Settings::get('icais_rename_max_length', 120);
        if (strlen($aiFilename) > $maxLength) {
            $aiFilename = substr($aiFilename, 0, $maxLength);
            $aiFilename = trim($aiFilename, '-');
        }

        if (empty($aiFilename)) {
            $aiFilename = 'image';
        }

        // Use FileRenamer to perform the actual rename with the AI-generated filename
        $renamer = new \Softminal\ImageCraft\Services\FileRenamer();
        $renameResult = $renamer->rename($attachmentId, $aiFilename, true);

        if (!$renameResult['success'] && empty($renameResult['already_named'])) {
            return $this->response([
                'success' => false,
                'message' => $renameResult['error'] ?? __('Failed to rename file', 'imagecraft-ai-alt-text-file-renamer-image-seo')
            ], 400);
        }

        return $this->response([
            'success' => true,
            'new_filename' => $renameResult['new_filename'] ?? '',
            'old_filename' => $renameResult['old_filename'] ?? '',
            'already_named' => !empty($renameResult['already_named']),
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
                'message' => __('Attachment ID is required', 'imagecraft-ai-alt-text-file-renamer-image-seo')
            ], 400);
        }

        if (!wp_attachment_is_image($attachmentId)) {
            return $this->response([
                'success' => false,
                'message' => __('Invalid image attachment', 'imagecraft-ai-alt-text-file-renamer-image-seo')
            ], 400);
        }

        update_post_meta($attachmentId, '_wp_attachment_image_alt', $altText);

        // Optionally update image title if provided
        if (isset($params['title'])) {
            $title = sanitize_text_field($params['title']);
            global $wpdb;
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->update($wpdb->posts, ['post_title' => $title], ['ID' => $attachmentId], ['%s'], ['%d']);
            clean_post_cache($attachmentId);
        }

        return $this->response([
            'success' => true,
            'message' => __('Alt text saved successfully', 'imagecraft-ai-alt-text-file-renamer-image-seo')
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
                'message' => __('History ID is required', 'imagecraft-ai-alt-text-file-renamer-image-seo')
            ], 400);
        }

        $history = History::find($historyId);
        if (!$history) {
            return $this->response([
                'success' => false,
                'message' => __('History not found', 'imagecraft-ai-alt-text-file-renamer-image-seo')
            ], 404);
        }

        // Verify ownership
        if ((int) $history->user_id !== get_current_user_id()) {
            return $this->response([
                'success' => false,
                'message' => __('Not authorized', 'imagecraft-ai-alt-text-file-renamer-image-seo')
            ], 403);
        }

        $history->status = 'rejected';
        if (!$history->save()) {
            return $this->response([
                'success' => false,
                'message' => __('Failed to update history record', 'imagecraft-ai-alt-text-file-renamer-image-seo')
            ], 500);
        }

        StatsTracker::recordReject($history->user_id);

        return $this->response([
            'success' => true,
            'message' => __('Alt text rejected', 'imagecraft-ai-alt-text-file-renamer-image-seo')
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

        // Check 3: Attachment is in a product's gallery (comma-separated IDs in meta)
        if (!$product) {
            global $wpdb;
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $galleryRows = $wpdb->get_results($wpdb->prepare(
                "SELECT post_id, meta_value FROM {$wpdb->postmeta}
                WHERE meta_key = '_product_image_gallery' AND meta_value LIKE %s",
                '%' . $wpdb->esc_like((string) $attachmentId) . '%'
            ));
            // Post-verify: LIKE can match partial IDs (e.g. "5" in "15,25")
            // so check that the attachment ID is an exact entry in the CSV
            foreach ($galleryRows ?: [] as $row) {
                $galleryIds = array_map('intval', explode(',', $row->meta_value));
                if (in_array($attachmentId, $galleryIds, true) && get_post_type($row->post_id) === 'product') {
                    $product = wc_get_product($row->post_id);
                    break;
                }
            }
        }

        if (!$product) {
            return null;
        }

        if (!Settings::get('icais_woo_include_product_info', '1')) {
            return null;
        }

        return WooCommerce::buildProductContextPrompt($product);
    }

    /**
     * Check if an integration source is allowed.
     * Empty source (gutenberg, media modal, batch) is always allowed.
     */
    private static function isIntegrationAllowed(string $source): bool
    {
        if ($source === '') {
            return true;
        }

        $settingKey = 'icais_integration_' . $source;
        return Settings::get($settingKey, '1') === '1';
    }
}
