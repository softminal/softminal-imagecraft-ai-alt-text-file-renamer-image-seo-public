<?php

namespace Softminal\ImageCraft\Http\Controllers;

use Softminal\ImageCraft\Services\FileRenamer;

if (!defined('ABSPATH')) {
    exit;
}

class FileRenamerController extends Controller
{
    protected FileRenamer $renamer;

    public function __construct()
    {
        $this->renamer = new FileRenamer();
    }

    public function rename($request)
    {
        $params = $request->get_json_params();
        $attachmentId = (int) ($params['attachment_id'] ?? 0);
        $customFilename = sanitize_text_field($params['filename'] ?? '');

        if (!$attachmentId) {
            return $this->response([
                'success' => false,
                'message' => __('Attachment ID is required', 'imagecraft-ai-alt-text-file-renamer-image-seo')
            ], 400);
        }

        $result = $this->renamer->rename($attachmentId, $customFilename);

        if (!$result['success']) {
            return $this->response($result, 400);
        }

        if (!empty($result['already_named'])) {
            return $this->response([
                'success' => true,
                'already_named' => true,
                'message' => __('File already has the correct name', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
                'old_filename' => $result['old_filename'],
                'new_filename' => $result['new_filename']
            ]);
        }

        return $this->response([
            'success' => true,
            'message' => __('File renamed successfully', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'old_filename' => $result['old_filename'],
            'new_filename' => $result['new_filename']
        ]);
    }

    /**
     * Bulk update image titles (safe — no file rename, no URL changes).
     *
     * Title source priority:
     *   1. _icais_generated_title meta (AI-generated, Phase 3)
     *   2. Derived from alt text via ucwords(slug) (fallback)
     */
    public function renameBulk($request)
    {
        $params = $request->get_json_params();
        $attachmentIds = array_map('absint', $params['attachment_ids'] ?? []);

        if (empty($attachmentIds)) {
            return $this->response([
                'success' => false,
                'message' => __('No attachments provided', 'imagecraft-ai-alt-text-file-renamer-image-seo')
            ], 400);
        }

        if (count($attachmentIds) > 25) {
            return $this->response([
                'success' => false,
                'message' => __('Maximum 25 images per request', 'imagecraft-ai-alt-text-file-renamer-image-seo')
            ], 400);
        }

        // Safety net for shared hosting with low time limits
        // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- Necessary to prevent timeout during bulk title updates
        @set_time_limit(120);

        $results = [];
        $successCount = 0;
        $skippedCount = 0;
        $unchangedCount = 0;
        $errorCount = 0;

        foreach ($attachmentIds as $id) {
            $result = $this->updateTitle($id);
            $results[$id] = $result;

            if (!empty($result['skipped'])) {
                if (($result['reason'] ?? '') === 'title_unchanged') {
                    $unchangedCount++;
                } else {
                    $skippedCount++;
                }
            } elseif ($result['success']) {
                $successCount++;
            } else {
                $errorCount++;
            }
        }

        return $this->response([
            'success' => $errorCount === 0,
            /* translators: %1$d: number of updated titles, %2$d: total number of images */
            'message' => sprintf(__('Updated %1$d/%2$d titles', 'imagecraft-ai-alt-text-file-renamer-image-seo'), $successCount, count($attachmentIds)),
            'success_count' => $successCount,
            'skipped_count' => $skippedCount,
            'unchanged_count' => $unchangedCount,
            'error_count' => $errorCount,
            'results' => $results
        ]);
    }

    /**
     * Update a single image's post_title from AI title or alt text.
     */
    private function updateTitle(int $attachmentId): array
    {
        if (!wp_attachment_is_image($attachmentId)) {
            return ['success' => false, 'skipped' => true, 'reason' => 'not_an_image'];
        }

        // Priority 1: AI-generated title (Phase 3 will populate this)
        $aiTitle = (string) get_post_meta($attachmentId, '_icais_generated_title', true);

        // Priority 2: Derive from alt text
        if (empty($aiTitle)) {
            $altText = (string) get_post_meta($attachmentId, '_wp_attachment_image_alt', true);
            if (empty($altText)) {
                return ['success' => false, 'skipped' => true, 'reason' => 'no_alt_text'];
            }

            $slug = $this->renamer->generateFilename($altText);
            $aiTitle = ucwords(str_replace('-', ' ', $slug));
        }

        // Cap at 80 chars, trim to last full word
        if (mb_strlen($aiTitle) > 80) {
            $aiTitle = mb_substr($aiTitle, 0, 80);
            $aiTitle = preg_replace('/\s+\S*$/', '', $aiTitle);
        }

        // Check if title is already the same
        $post = get_post($attachmentId);
        if ($post && $post->post_title === $aiTitle) {
            return ['success' => true, 'skipped' => true, 'reason' => 'title_unchanged', 'title' => $aiTitle];
        }

        // Update title directly (avoid wp_update_post hooks/side-effects)
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct update to avoid wp_update_post hooks
        $updated = $wpdb->update(
            $wpdb->posts,
            ['post_title' => $aiTitle],
            ['ID' => $attachmentId],
            ['%s'],
            ['%d']
        );

        if ($updated === false) {
            return ['success' => false, 'error' => 'Failed to update title'];
        }

        clean_post_cache($attachmentId);

        return ['success' => true, 'title' => $aiTitle];
    }

    public function preview($request)
    {
        $params = $request->get_params();
        $attachmentId = (int) ($params['attachment_id'] ?? $params['id'] ?? 0);

        if (!$attachmentId) {
            return $this->response([
                'success' => false,
                'message' => __('Attachment ID is required', 'imagecraft-ai-alt-text-file-renamer-image-seo')
            ], 400);
        }

        $result = $this->renamer->previewRename($attachmentId);

        return $this->response($result);
    }
}
