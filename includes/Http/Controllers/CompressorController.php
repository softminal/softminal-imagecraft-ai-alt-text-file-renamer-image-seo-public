<?php

namespace Softminal\ImageCraft\Http\Controllers;

use Softminal\ImageCraft\Models\Compression;
use Softminal\ImageCraft\Services\ImageCompressor;
use Softminal\ImageCraft\Services\NextGenConverter;
use Softminal\ImageCraft\Services\UsageLimiter;

if (!defined('ABSPATH')) {
    exit;
}

class CompressorController extends Controller
{
    protected ImageCompressor $compressor;

    public function __construct()
    {
        $this->compressor = new ImageCompressor();
    }

    /**
     * Scan the media library for images and their compression status.
     */
    public function scan($request)
    {
        $params  = $request->get_params();
        $page    = max(1, (int) ($params['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($params['per_page'] ?? 24)));
        $filter  = sanitize_text_field($params['filter'] ?? 'all');
        $sort    = sanitize_text_field($params['sort'] ?? 'date');
        $minSize = max(0, (int) ($params['min_size'] ?? 0)) * 1024; // KB -> bytes

        $metaQuery = [];
        if ($filter === 'uncompressed') {
            $metaQuery[] = ['key' => '_icais_compressed', 'compare' => 'NOT EXISTS'];
        } elseif ($filter === 'compressed') {
            $metaQuery[] = ['key' => '_icais_compressed', 'compare' => 'EXISTS'];
        }

        $args = [
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'post_mime_type' => ['image/jpeg', 'image/png', 'image/webp'],
            'posts_per_page' => $perPage,
            'paged'          => $page,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'fields'         => 'ids',
        ];
        if (!empty($metaQuery)) {
            $args['meta_query'] = $metaQuery; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Indexed meta key, paginated.
        }

        if ($sort === 'largest' || $minSize > 0) {
            // Sort by file size and/or filter by a minimum size. File size isn't a
            // queryable column, so fetch all matching IDs, size each, filter/sort,
            // and paginate in PHP. Opt-in only, so the cost is paid only when the
            // user explicitly sorts by size or sets a minimum size filter.
            $allArgs = $args;
            $allArgs['posts_per_page'] = -1;
            unset($allArgs['paged']);
            $allIds = (new \WP_Query($allArgs))->posts;

            $sizes = [];
            foreach ($allIds as $id) {
                $path = get_attached_file($id);
                $sizes[$id] = ($path && file_exists($path)) ? (int) filesize($path) : 0;
            }

            if ($minSize > 0) {
                $sizes = array_filter($sizes, function ($size) use ($minSize) {
                    return $size >= $minSize;
                });
            }

            if ($sort === 'largest') {
                arsort($sizes, SORT_NUMERIC);
            }

            $sortedIds = array_keys($sizes);

            $total      = count($sortedIds);
            $totalPages = (int) ceil($total / $perPage);
            $pageIds    = array_slice($sortedIds, ($page - 1) * $perPage, $perPage);
        } else {
            $query      = new \WP_Query($args);
            $pageIds    = $query->posts;
            $total      = (int) $query->found_posts;
            $totalPages = (int) $query->max_num_pages;
        }

        if (!empty($pageIds)) {
            _prime_post_caches($pageIds, true, true);
        }

        $images = [];
        foreach ($pageIds as $postId) {
            $images[] = $this->imageRow((int) $postId);
        }

        return $this->response([
            'success'    => true,
            'images'     => $images,
            'pagination' => [
                'current_page' => $page,
                'per_page'     => $perPage,
                'total'        => $total,
                'total_pages'  => $totalPages,
            ],
            'stats' => $this->buildStats(),
        ]);
    }

    /**
     * Compress a single attachment.
     */
    public function compress($request)
    {
        $params       = $request->get_json_params();
        $attachmentId = (int) ($params['attachment_id'] ?? 0);
        $force        = !empty($params['force']);

        if (!$attachmentId) {
            return $this->response([
                'success' => false,
                'message' => __('Attachment ID is required', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            ], 400);
        }

        $authError = $this->authorize($attachmentId);
        if ($authError) {
            return $authError;
        }

        $userId = get_current_user_id();
        if (!UsageLimiter::canCompress($userId, $attachmentId)) {
            return $this->response(UsageLimiter::compressLimitReachedError(), 429);
        }

        $result = $this->compressor->compress($attachmentId, ['force' => $force]);

        if (empty($result['success'])) {
            return $this->response([
                'success' => false,
                'message' => $result['error'] ?? __('Compression failed', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            ], 400);
        }

        // Only count usage when we actually compressed (not skipped).
        if (empty($result['skipped'])) {
            UsageLimiter::recordCompressUsage($userId, $attachmentId);
        }

        $result['image'] = $this->imageRow($attachmentId);
        $result['usage'] = $this->usageBlock($userId);
        return $this->response($result);
    }

    /**
     * Generate next-gen (WebP/AVIF) sidecars for a single attachment.
     * Shares the daily compress counter — no separate limit.
     */
    public function convert($request)
    {
        $params       = $request->get_json_params();
        $attachmentId = (int) ($params['attachment_id'] ?? 0);

        if (!$attachmentId) {
            return $this->response([
                'success' => false,
                'message' => __('Attachment ID is required', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            ], 400);
        }

        $authError = $this->authorize($attachmentId);
        if ($authError) {
            return $authError;
        }

        $formats = NextGenConverter::enabledFormats();
        if ($formats === []) {
            return $this->response([
                'success' => false,
                'message' => __('This server cannot create WebP or AVIF images', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            ], 400);
        }

        $userId = get_current_user_id();
        if (!UsageLimiter::canCompress($userId, $attachmentId)) {
            return $this->response(UsageLimiter::compressLimitReachedError(), 429);
        }

        $converter = new NextGenConverter();
        $result = $converter->convert($attachmentId, $formats);

        if (empty($result['success'])) {
            return $this->response([
                'success' => false,
                'skipped' => !empty($result['skipped']),
                'reason'  => $result['reason'] ?? '',
                'message' => $result['error'] ?? __('Nothing to convert for this image', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            ], empty($result['skipped']) ? 400 : 200);
        }

        // Only count usage for real work (idempotent re-runs are free).
        if (!empty($result['generated'])) {
            UsageLimiter::recordCompressUsage($userId, $attachmentId);
        }

        $result['usage'] = $this->usageBlock($userId);
        return $this->response($result);
    }

    /**
     * Scan for images that could get next-gen sidecars + server capabilities.
     */
    public function convertScan($request)
    {
        $params  = $request->get_params();
        $page    = max(1, (int) ($params['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($params['per_page'] ?? 24)));

        $query = new \WP_Query([
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'post_mime_type' => ['image/jpeg', 'image/png', 'image/webp'],
            'posts_per_page' => $perPage,
            'paged'          => $page,
            'fields'         => 'ids',
            'no_found_rows'  => false,
        ]);

        $images = [];
        foreach ($query->posts as $id) {
            $manifest = get_post_meta($id, NextGenConverter::META_MANIFEST, true);
            $images[] = [
                'id'          => (int) $id,
                'title'       => get_the_title($id),
                'thumb'       => wp_get_attachment_image_url($id, 'thumbnail') ?: '',
                'mime'        => get_post_mime_type($id),
                'converted'   => is_array($manifest) && !empty($manifest['files']),
                'files'       => is_array($manifest) && !empty($manifest['files']) ? count($manifest['files']) : 0,
                'saved_human' => is_array($manifest) && !empty($manifest['saved']) ? size_format((int) $manifest['saved']) : '',
            ];
        }

        return $this->response([
            'success'      => true,
            'images'       => $images,
            'capabilities' => NextGenConverter::capabilities(),
            'formats'      => NextGenConverter::enabledFormats(),
            'pagination'   => [
                'page'        => $page,
                'per_page'    => $perPage,
                'total'       => (int) $query->found_posts,
                'total_pages' => (int) $query->max_num_pages,
            ],
        ]);
    }

    /**
     * Remove an attachment's next-gen sidecars (fully reversible feature).
     */
    public function convertClean($request)
    {
        $params       = $request->get_json_params();
        $attachmentId = (int) ($params['attachment_id'] ?? 0);

        if (!$attachmentId) {
            return $this->response([
                'success' => false,
                'message' => __('Attachment ID is required', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            ], 400);
        }

        $authError = $this->authorize($attachmentId);
        if ($authError) {
            return $authError;
        }

        $converter = new NextGenConverter();
        return $this->response($converter->clean($attachmentId));
    }

    /**
     * Restore an attachment's original (pre-compression) files.
     */
    public function restore($request)
    {
        $params       = $request->get_json_params();
        $attachmentId = (int) ($params['attachment_id'] ?? 0);

        if (!$attachmentId) {
            return $this->response([
                'success' => false,
                'message' => __('Attachment ID is required', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            ], 400);
        }

        $authError = $this->authorize($attachmentId);
        if ($authError) {
            return $authError;
        }

        $result = $this->compressor->restore($attachmentId);

        if (empty($result['success'])) {
            return $this->response([
                'success' => false,
                'message' => $result['error'] ?? __('Restore failed', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            ], 400);
        }

        $result['image'] = $this->imageRow($attachmentId);
        return $this->response($result);
    }

    /**
     * Preview potential savings for an attachment.
     */
    public function preview($request)
    {
        $params       = $request->get_params();
        $attachmentId = (int) ($params['attachment_id'] ?? $params['id'] ?? 0);

        if (!$attachmentId) {
            return $this->response([
                'success' => false,
                'message' => __('Attachment ID is required', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            ], 400);
        }

        return $this->response($this->compressor->preview($attachmentId));
    }

    /**
     * Compression dashboard stats.
     */
    public function stats($request)
    {
        return $this->response([
            'success' => true,
            'stats'   => $this->buildStats(),
            'usage'   => $this->usageBlock(get_current_user_id()),
        ]);
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    /**
     * Verify the current user may modify this attachment. Returns a response on
     * failure, or null when authorized.
     */
    private function authorize(int $attachmentId)
    {
        $attachment = get_post($attachmentId);
        if (!$attachment || ((int) $attachment->post_author !== get_current_user_id() && !current_user_can('manage_options'))) {
            return $this->response([
                'success' => false,
                'message' => __('Not authorized to modify this attachment', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            ], 403);
        }
        return null;
    }

    /**
     * Build the per-image payload for the scan grid.
     */
    private function imageRow(int $postId): array
    {
        $post = get_post($postId);
        $file = get_attached_file($postId);
        // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Suppress unserialize warnings from corrupted metadata
        $thumb = @wp_get_attachment_image_src($postId, 'thumbnail');

        $stats      = get_post_meta($postId, '_icais_compression_stats', true);
        $compressed = (bool) get_post_meta($postId, '_icais_compressed', true);

        $sizeBytes = 0;
        if (!empty($file) && file_exists($file)) {
            $sizeBytes = (int) filesize($file);
        }

        return [
            'id'             => $postId,
            'title'          => $post ? $post->post_title : '',
            'filename'       => $file ? basename($file) : '',
            'url'            => wp_get_attachment_url($postId),
            'thumbnail'      => $thumb ? $thumb[0] : null,
            'size_bytes'     => $sizeBytes,
            'size_human'     => size_format($sizeBytes),
            'compressed'     => $compressed,
            'saved_bytes'    => is_array($stats) ? (int) ($stats['saved_bytes'] ?? 0) : 0,
            'saved_percent'  => is_array($stats) ? (float) ($stats['saved_percent'] ?? 0) : 0,
            'saved_human'    => is_array($stats) ? size_format((int) ($stats['saved_bytes'] ?? 0)) : '',
            'can_restore'    => (bool) get_post_meta($postId, '_icais_compression_backup', true),
        ];
    }

    /**
     * Aggregate savings + counts from the compressions table.
     */
    private function buildStats(): array
    {
        global $wpdb;
        $table = esc_sql(Compression::table());

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name escaped via esc_sql(); no user input.
        $row = $wpdb->get_row("SELECT COUNT(*) AS cnt, COALESCE(SUM(saved_bytes),0) AS saved FROM `{$table}` WHERE status = 'completed'", ARRAY_A);

        $savedBytes      = $row ? (int) $row['saved'] : 0;
        $compressedCount = $row ? (int) $row['cnt'] : 0;

        return [
            'total_compressed' => $compressedCount,
            'total_saved_bytes' => $savedBytes,
            'total_saved_human' => size_format($savedBytes),
        ];
    }

    /**
     * Current user's compression usage block for the free-tier meter.
     */
    private function usageBlock(int $userId): array
    {
        return [
            'is_pro'    => UsageLimiter::isPro(),
            'used'      => UsageLimiter::getCompressDailyUsage($userId),
            'limit'     => UsageLimiter::getCompressLimit(),
            'remaining' => UsageLimiter::getCompressRemaining($userId),
        ];
    }
}
