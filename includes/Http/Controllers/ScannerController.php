<?php

namespace Softminal\ImageCraft\Http\Controllers;

use Softminal\ImageCraft\Support\AltTextHelper;

if (!defined('ABSPATH')) {
    exit;
}

class ScannerController extends Controller
{
    /**
     * Build base WP_Query args with filter and date range
     */
    private function buildQueryArgs(array $params, array $overrides = []): array
    {
        $filter = sanitize_text_field($params['filter'] ?? 'all');
        $dateFrom = sanitize_text_field($params['date_from'] ?? '');
        $dateTo = sanitize_text_field($params['date_to'] ?? '');

        $args = array_merge([
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'post_status' => 'inherit',
            'orderby' => 'date',
            'order' => 'DESC',
        ], $overrides);

        if ($filter === 'missing') {
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
            $args['meta_query'] = [
                'relation' => 'OR',
                ['key' => '_wp_attachment_image_alt', 'compare' => 'NOT EXISTS'],
                ['key' => '_wp_attachment_image_alt', 'value' => '', 'compare' => '='],
            ];
        } elseif ($filter === 'has_alt' || $filter === 'generic' || $filter === 'too_long') {
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
            $args['meta_query'] = [
                ['key' => '_wp_attachment_image_alt', 'compare' => 'EXISTS'],
                ['key' => '_wp_attachment_image_alt', 'value' => '', 'compare' => '!='],
            ];
        }

        if ($dateFrom || $dateTo) {
            $dateQuery = ['inclusive' => true];
            if ($dateFrom) {
                $dateQuery['after'] = $dateFrom;
            }
            if ($dateTo) {
                $dateQuery['before'] = $dateTo . ' 23:59:59';
            }
            $args['date_query'] = [$dateQuery];
        }

        return $args;
    }

    /**
     * Scan media library
     */
    public function scan($request)
    {
        $params = $request->get_params();
        $page = max(1, (int) ($params['page'] ?? 1));
        $perPage = min(max(1, (int) ($params['per_page'] ?? 50)), 100);
        $minSize = (int) ($params['min_size'] ?? 0);
        $filter = sanitize_text_field($params['filter'] ?? 'all');
        $needsPhpFilter = $minSize > 0 || $filter === 'generic' || $filter === 'too_long';

        // When min_size/generic/too_long filter is active, we need PHP-level filtering
        // so fetch all matching IDs first, filter, then paginate manually
        if ($needsPhpFilter) {
            $allArgs = $this->buildQueryArgs($params, [
                'posts_per_page' => -1,
                'fields' => 'ids',
            ]);
            $allQuery = new \WP_Query($allArgs);
            $allIds = $allQuery->posts;

            // PHP-level filtering for generic/too_long alt text
            if ($filter === 'generic' || $filter === 'too_long') {
                $maxLength = (int) \Softminal\ImageCraft\Services\Settings::get('icais_max_length', 125);
                $qualityFiltered = [];
                foreach ($allIds as $id) {
                    $alt = get_post_meta($id, '_wp_attachment_image_alt', true);
                    if (empty($alt)) {
                        continue;
                    }
                    if ($filter === 'generic' && AltTextHelper::isGeneric($alt)) {
                        $qualityFiltered[] = $id;
                    } elseif ($filter === 'too_long' && mb_strlen($alt) > $maxLength) {
                        $qualityFiltered[] = $id;
                    }
                }
                $allIds = $qualityFiltered;
            }

            // Filter by file size
            if ($minSize > 0) {
                $minBytes = $minSize * 1024;
                $sizeFiltered = [];
                foreach ($allIds as $id) {
                    $filePath = get_attached_file($id);
                    if ($filePath && file_exists($filePath) && filesize($filePath) >= $minBytes) {
                        $sizeFiltered[] = $id;
                    }
                }
                $allIds = $sizeFiltered;
            }

            $filteredIds = $allIds;

            $totalFiltered = count($filteredIds);
            $totalPages = $totalFiltered > 0 ? (int) ceil($totalFiltered / $perPage) : 1;
            $offset = ($page - 1) * $perPage;
            $pageIds = array_slice($filteredIds, $offset, $perPage);

            $images = [];
            foreach ($pageIds as $postId) {
                $post = get_post($postId);
                if (!$post) {
                    continue;
                }
                $alt = get_post_meta($postId, '_wp_attachment_image_alt', true);
                $thumbnail = wp_get_attachment_image_src($postId, 'thumbnail');

                $images[] = [
                    'id' => $postId,
                    'title' => $post->post_title,
                    'filename' => basename(get_attached_file($postId)),
                    'url' => wp_get_attachment_url($postId),
                    'thumbnail' => $thumbnail ? $thumbnail[0] : null,
                    'alt_text' => $alt ?: '',
                    'has_alt' => !empty($alt),
                    'date' => $post->post_date
                ];
            }

            $paginationData = [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $totalFiltered,
                'total_pages' => $totalPages
            ];
        } else {
            $args = $this->buildQueryArgs($params, [
                'posts_per_page' => $perPage,
                'paged' => $page,
            ]);

            $query = new \WP_Query($args);

            $images = [];
            foreach ($query->posts as $post) {
                $alt = get_post_meta($post->ID, '_wp_attachment_image_alt', true);
                $thumbnail = wp_get_attachment_image_src($post->ID, 'thumbnail');

                $images[] = [
                    'id' => $post->ID,
                    'title' => $post->post_title,
                    'filename' => basename(get_attached_file($post->ID)),
                    'url' => wp_get_attachment_url($post->ID),
                    'thumbnail' => $thumbnail ? $thumbnail[0] : null,
                    'alt_text' => $alt ?: '',
                    'has_alt' => !empty($alt),
                    'date' => $post->post_date
                ];
            }

            $paginationData = [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $query->found_posts,
                'total_pages' => $query->max_num_pages
            ];
        }

        $imageCounts = AltTextHelper::getImageCounts();
        $totalImages = $imageCounts['total'];
        $missingAlt = $imageCounts['missing'];
        $hasAlt = $totalImages - $missingAlt;

        return $this->response([
            'success' => true,
            'images' => $images,
            'pagination' => $paginationData,
            'stats' => [
                'total_images' => $totalImages,
                'missing_alt' => $missingAlt,
                'has_alt' => $hasAlt,
                'missing_percentage' => $totalImages > 0 ? round(($missingAlt / $totalImages) * 100, 2) : 0,
                'max_length' => (int) \Softminal\ImageCraft\Services\Settings::get('icais_max_length', 150),
            ]
        ]);
    }

    /**
     * Return all image IDs matching current filters (for bulk select)
     */
    public function selectAll($request)
    {
        $params = $request->get_params();
        $quality = sanitize_text_field($params['quality'] ?? '');

        $args = $this->buildQueryArgs($params, [
            'posts_per_page' => -1,
            'fields' => 'ids',
        ]);

        // Quality filters require has_alt base; override filter if needed
        if ($quality === 'generic' || $quality === 'too_long') {
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
            $args['meta_query'] = [
                ['key' => '_wp_attachment_image_alt', 'compare' => 'EXISTS'],
                ['key' => '_wp_attachment_image_alt', 'value' => '', 'compare' => '!='],
            ];
        }

        $query = new \WP_Query($args);
        $ids = $query->posts;

        // PHP-level quality filtering
        if ($quality === 'generic' || $quality === 'too_long') {
            $maxLength = (int) \Softminal\ImageCraft\Services\Settings::get('icais_max_length', 125);
            $filtered = [];
            foreach ($ids as $id) {
                $alt = get_post_meta($id, '_wp_attachment_image_alt', true);
                if (empty($alt)) {
                    continue;
                }
                if ($quality === 'generic' && AltTextHelper::isGeneric($alt)) {
                    $filtered[] = $id;
                } elseif ($quality === 'too_long' && mb_strlen($alt) > $maxLength) {
                    $filtered[] = $id;
                }
            }
            $ids = $filtered;
        }

        // PHP-level file size filtering (min_size in KB)
        $minSize = (int) ($params['min_size'] ?? 0);
        if ($minSize > 0) {
            global $wp_filesystem;
            if (empty($wp_filesystem)) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
                WP_Filesystem();
            }
            $minBytes = $minSize * 1024;
            $filtered = [];
            foreach ($ids as $id) {
                $filePath = get_attached_file($id);
                if ($filePath && $wp_filesystem->exists($filePath) && $wp_filesystem->size($filePath) >= $minBytes) {
                    $filtered[] = $id;
                }
            }
            $ids = $filtered;
        }

        return $this->response([
            'success' => true,
            'image_ids' => $ids,
            'total' => count($ids),
        ]);
    }

}
