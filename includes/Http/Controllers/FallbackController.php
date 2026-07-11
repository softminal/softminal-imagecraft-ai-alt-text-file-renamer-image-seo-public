<?php

namespace Softminal\ImageCraft\Http\Controllers;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Image Fallbacks — admin-side broken-image scan.
 *
 * Iterates image attachments and flags those whose file is missing on disk.
 * Results are cached in a transient so the Dashboard tile is cheap. On large
 * media libraries the scan runs under a time budget and resumes across
 * requests (cursor stored in the transient, `partial: true` in the response),
 * mirroring the batch/process chunking pattern.
 *
 * scanBrokenCore() is a public reusable core (mirrors
 * GeneratorController::generateMetaForImage) so the Pro add-on's scheduled
 * scan cron can call it directly.
 */
class FallbackController extends Controller
{
    /** Transient holding the finished (or in-progress) scan result. */
    public const TRANSIENT = 'icais_broken_scan';

    /** Attachments fetched per WP_Query page. */
    private const PAGE_SIZE = 500;

    /** Wall-clock budget per request, in seconds. */
    private const TIME_BUDGET = 10;

    /** Max broken-image samples returned/stored. */
    private const SAMPLE_LIMIT = 20;

    /**
     * GET /fallback/scan-broken — REST wrapper around the reusable core.
     */
    public function scanBroken($request)
    {
        try {
            $params  = $request->get_params();
            $refresh = !empty($params['refresh']);

            $result = $this->scanBrokenCore($refresh);

            return $this->response(array_merge(['success' => true], $result));
        } catch (\Throwable $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging only when WP_DEBUG is enabled
                error_log('ImageCraft broken scan error: ' . $e->getMessage());
            }
            return $this->response([
                'success' => false,
                'message' => __('Something went wrong', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            ], 500);
        }
    }

    /**
     * Reusable scan core (public — also called by the Pro scheduled-scan cron).
     *
     * Runs (or resumes) the broken-image scan under a time budget. Returns:
     * ['broken_count' => int, 'scanned' => int, 'total' => int,
     *  'partial' => bool, 'sample' => [{id, url, title}], 'scanned_at' => int]
     *
     * @param bool $refresh Discard any cached/in-progress result and rescan.
     */
    public function scanBrokenCore(bool $refresh = false): array
    {
        $state = $refresh ? false : get_transient(self::TRANSIENT);

        // A finished scan is served straight from cache.
        if (is_array($state) && empty($state['partial'])) {
            return $state;
        }

        // Fresh scan or resume of a partial one.
        if (!is_array($state)) {
            $state = [
                'broken_count' => 0,
                'scanned'      => 0,
                'total'        => 0,
                'partial'      => true,
                'sample'       => [],
                'scanned_at'   => time(),
                'page'         => 1, // internal cursor (next page to scan)
            ];
        }

        $started = microtime(true);
        $page    = max(1, (int) ($state['page'] ?? 1));

        do {
            $query = new \WP_Query([
                'post_type'      => 'attachment',
                'post_status'    => 'inherit',
                'post_mime_type' => 'image',
                'posts_per_page' => self::PAGE_SIZE,
                'paged'          => $page,
                'orderby'        => 'ID',
                'order'          => 'ASC',
                'fields'         => 'ids',
                'no_found_rows'  => false,
            ]);

            $state['total'] = (int) $query->found_posts;

            foreach ($query->posts as $attachmentId) {
                $attachmentId = (int) $attachmentId;
                $file = get_attached_file($attachmentId);
                // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_exists -- Read-only existence check on uploads; WP_Filesystem is unnecessary overhead here
                if (!$file || !file_exists($file)) {
                    $state['broken_count']++;
                    if (count($state['sample']) < self::SAMPLE_LIMIT) {
                        $state['sample'][] = [
                            'id'    => $attachmentId,
                            'url'   => (string) wp_get_attachment_url($attachmentId),
                            'title' => get_the_title($attachmentId),
                        ];
                    }
                }
                $state['scanned']++;
            }

            $hasMore = $page < (int) $query->max_num_pages;
            $page++;

            // Stop when done or when the time budget for this request is spent.
        } while ($hasMore && (microtime(true) - $started) < self::TIME_BUDGET);

        $state['partial'] = $hasMore;
        $state['page']    = $page;

        if (!$hasMore) {
            $state['scanned_at'] = time();
        }

        // Partial states persist briefly (resume window); finished scans for an hour.
        set_transient(self::TRANSIENT, $state, $hasMore ? 5 * MINUTE_IN_SECONDS : HOUR_IN_SECONDS);

        // The internal cursor stays out of API responses (additive-only shape).
        $public = $state;
        unset($public['page']);

        return $public;
    }
}
