<?php

namespace Softminal\ImageCraft\Services;

if (!defined('ABSPATH')) {
    exit;
}

use Softminal\ImageCraft\Services\AI\AIFactory;
use Softminal\ImageCraft\Services\StatsTracker;
use Softminal\ImageCraft\Services\UsageLimiter;
use Softminal\ImageCraft\Support\AltTextHelper;

class WooCommerce
{
    public static function isActive(): bool
    {
        return class_exists('WooCommerce');
    }

    public static function getProducts(?int $limit = -1, int $page = 1, string $filter = 'all', int $category = 0, array $advanced = []): array
    {
        if (!self::isActive()) {
            return ['products' => [], 'total' => 0, 'pages' => 0];
        }

        $args = [
            'post_type' => 'product',
            'posts_per_page' => $limit > 0 ? $limit : 20,
            'paged' => $page,
            'post_status' => 'publish',
        ];

        // Category filter
        if ($category > 0) {
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
            $args['tax_query'] = [
                [
                    'taxonomy' => 'product_cat',
                    'field' => 'term_id',
                    'terms' => $category,
                ],
            ];
        }

        // Date range filter (by product created date)
        $dateFrom = $advanced['date_from'] ?? '';
        $dateTo = $advanced['date_to'] ?? '';
        if ($dateFrom || $dateTo) {
            $dateQuery = [];
            if ($dateFrom) {
                $dateQuery['after'] = sanitize_text_field($dateFrom);
            }
            if ($dateTo) {
                $dateQuery['before'] = sanitize_text_field($dateTo);
            }
            $dateQuery['inclusive'] = true;
            $args['date_query'] = [$dateQuery];
        }

        // Price range filter
        $minPrice = isset($advanced['min_price']) ? (float) $advanced['min_price'] : 0;
        $maxPrice = isset($advanced['max_price']) ? (float) $advanced['max_price'] : 0;
        if ($minPrice > 0 || $maxPrice > 0) {
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
            $priceQuery = ['relation' => 'AND'];
            if ($minPrice > 0) {
                $priceQuery[] = [
                    'key' => '_price',
                    'value' => $minPrice,
                    'compare' => '>=',
                    'type' => 'DECIMAL',
                ];
            }
            if ($maxPrice > 0) {
                $priceQuery[] = [
                    'key' => '_price',
                    'value' => $maxPrice,
                    'compare' => '<=',
                    'type' => 'DECIMAL',
                ];
            }
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
            $args['meta_query'] = $priceQuery;
        }

        switch ($filter) {
            case 'missing':
                $missingIds = self::getProductIdsMissingAlt();
                $args['post__in'] = !empty($missingIds) ? $missingIds : [0];
                break;
            case 'has_alt':
                $hasAltIds = self::getProductIdsWithAlt();
                $args['post__in'] = !empty($hasAltIds) ? $hasAltIds : [0];
                break;
            case 'generic':
                $genericIds = self::getProductIdsWithQualityIssues('generic');
                $args['post__in'] = !empty($genericIds) ? $genericIds : [0];
                break;
            case 'too_long':
                $tooLongIds = self::getProductIdsWithQualityIssues('too_long');
                $args['post__in'] = !empty($tooLongIds) ? $tooLongIds : [0];
                break;
            case 'missing_title':
                $missingTitleIds = self::getProductIdsMissingTitle();
                $args['post__in'] = !empty($missingTitleIds) ? $missingTitleIds : [0];
                break;
            case 'has_title':
                $hasTitleIds = self::getProductIdsWithTitle();
                $args['post__in'] = !empty($hasTitleIds) ? $hasTitleIds : [0];
                break;
            case 'title_too_long':
                $titleTooLongIds = self::getProductIdsWithTitleTooLong();
                $args['post__in'] = !empty($titleTooLongIds) ? $titleTooLongIds : [0];
                break;
            case 'missing_caption':
                $missingCaptionIds = self::getProductIdsMissingCaption();
                $args['post__in'] = !empty($missingCaptionIds) ? $missingCaptionIds : [0];
                break;
            case 'has_caption':
                $hasCaptionIds = self::getProductIdsWithCaption();
                $args['post__in'] = !empty($hasCaptionIds) ? $hasCaptionIds : [0];
                break;
            case 'caption_too_long':
                $captionTooLongIds = self::getProductIdsWithCaptionTooLong();
                $args['post__in'] = !empty($captionTooLongIds) ? $captionTooLongIds : [0];
                break;
        }

        $query = new \WP_Query($args);

        // Collect all gallery IDs across products for meta cache priming
        $allImageIds = [];
        $productGalleryMap = [];
        foreach ($query->posts as $post) {
            $product = wc_get_product($post->ID);
            if (!$product) {
                continue;
            }
            $thumbnailId = $product->get_image_id();
            $galleryIds = $product->get_gallery_image_ids();
            if ($thumbnailId) {
                $allImageIds[] = $thumbnailId;
            }
            foreach ($galleryIds as $gId) {
                $allImageIds[] = $gId;
            }
            $productGalleryMap[$post->ID] = [
                'product' => $product,
                'thumbnail_id' => $thumbnailId,
                'gallery_ids' => $galleryIds,
            ];
        }

        // Prime meta cache for all image IDs in one query
        if (!empty($allImageIds)) {
            update_meta_cache('post', array_unique($allImageIds));
        }

        $products = [];
        foreach ($query->posts as $post) {
            if (!isset($productGalleryMap[$post->ID])) {
                continue;
            }

            $data = $productGalleryMap[$post->ID];
            $product = $data['product'];
            $thumbnailId = $data['thumbnail_id'];
            $galleryIds = $data['gallery_ids'];

            $altText = '';
            $imageTitle = '';
            $imageCaption = '';
            if ($thumbnailId) {
                $altText = get_post_meta($thumbnailId, '_wp_attachment_image_alt', true);
                $thumbPost = get_post($thumbnailId);
                $imageTitle = $thumbPost ? $thumbPost->post_title : '';
                $imageCaption = $thumbPost ? $thumbPost->post_excerpt : '';
            }

            // Calculate image coverage (featured + gallery)
            $allIds = $thumbnailId ? array_merge([$thumbnailId], $galleryIds) : $galleryIds;
            $totalImages = count($allIds);
            $hasAltCount = 0;
            $hasTitleCount = 0;
            $hasCaptionCount = 0;
            foreach ($allIds as $imgId) {
                $imgAlt = get_post_meta($imgId, '_wp_attachment_image_alt', true);
                if (!empty($imgAlt)) {
                    $hasAltCount++;
                }
                $imgTitleMeta = get_post_meta($imgId, '_icais_generated_title', true);
                if (!empty($imgTitleMeta)) {
                    $hasTitleCount++;
                }
                $imgCaptionMeta = get_post_meta($imgId, '_icais_generated_caption', true);
                if (!empty($imgCaptionMeta)) {
                    $hasCaptionCount++;
                }
            }

            // First 4 gallery thumbnail URLs for card preview strip
            $galleryThumbUrls = [];
            foreach (array_slice($galleryIds, 0, 4) as $gId) {
                $thumbUrl = wp_get_attachment_thumb_url($gId);
                if ($thumbUrl) {
                    $galleryThumbUrls[] = $thumbUrl;
                }
            }

            $products[] = [
                'id' => $post->ID,
                'title' => $product->get_name(),
                'sku' => $product->get_sku(),
                'thumbnail_id' => $thumbnailId,
                'thumbnail_url' => $thumbnailId ? wp_get_attachment_thumb_url($thumbnailId) : null,
                'full_url' => $thumbnailId ? wp_get_attachment_url($thumbnailId) : null,
                'alt_text' => $altText,
                'image_title' => $imageTitle,
                'image_caption' => $imageCaption,
                'has_alt' => !empty($altText),
                'gallery_ids' => array_values($galleryIds),
                'price' => wp_kses_post($product->get_price_html()),
                'status' => $product->get_status(),
                'image_coverage' => [
                    'total' => $totalImages,
                    'has_alt' => $hasAltCount,
                    'has_title' => $hasTitleCount,
                    'has_caption' => $hasCaptionCount,
                ],
                'gallery_thumb_urls' => $galleryThumbUrls,
            ];
        }

        return [
            'products' => $products,
            'total' => $query->found_posts,
            'pages' => $query->max_num_pages,
        ];
    }

    public static function getProductStats(): array
    {
        if (!self::isActive()) {
            return [
                'total_products' => 0,
                'missing_alt' => 0,
                'has_alt' => 0,
                'missing_percentage' => 0,
            ];
        }

        global $wpdb;

        // Total published products
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $totalProducts = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s",
                'product',
                'publish'
            )
        );

        $hasAlt = self::countProductsWithAlt();
        $missingAlt = $totalProducts - $hasAlt;
        $percentage = $totalProducts > 0 ? round(($missingAlt / $totalProducts) * 100, 1) : 0;

        $hasTitle = self::countProductsWithTitle();
        $missingTitle = $totalProducts - $hasTitle;
        $hasCaption = self::countProductsWithCaption();
        $missingCaption = $totalProducts - $hasCaption;

        return [
            'total_products' => $totalProducts,
            'missing_alt' => $missingAlt,
            'has_alt' => $hasAlt,
            'missing_percentage' => $percentage,
            'max_length' => (int) Settings::get('icais_max_length', 125),
            'has_title' => $hasTitle,
            'missing_title' => $missingTitle,
            'title_max_length' => (int) Settings::get('icais_title_max_length', 30),
            'has_caption' => $hasCaption,
            'missing_caption' => $missingCaption,
            'caption_max_length' => (int) Settings::get('icais_caption_max_length', 150),
        ];
    }

    /**
     * Count published products whose featured image has non-empty alt text.
     * Uses COUNT(*) to avoid materialising a potentially large ID array just for stats.
     */
    private static function countProductsWithAlt(): int
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT p.ID)
                 FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} thumb
                     ON p.ID = thumb.post_id AND thumb.meta_key = %s
                 INNER JOIN {$wpdb->postmeta} alt
                     ON thumb.meta_value = alt.post_id
                     AND alt.meta_key = %s
                     AND alt.meta_value != ''
                 WHERE p.post_type = %s AND p.post_status = %s",
                '_thumbnail_id',
                '_wp_attachment_image_alt',
                'product',
                'publish'
            )
        );
    }

    /**
     * Get IDs of published products whose featured image has non-empty alt text.
     *
     * Alt text lives on the attachment post (_wp_attachment_image_alt), not on
     * the product, so we need a cross-post join to check it.
     *
     * @return int[]
     */
    private static ?array $productIdsWithAltCache = null;

    private static function getProductIdsWithAlt(): array
    {
        if (self::$productIdsWithAltCache !== null) {
            return self::$productIdsWithAltCache;
        }

        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT p.ID
                 FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} thumb
                     ON p.ID = thumb.post_id AND thumb.meta_key = %s
                 INNER JOIN {$wpdb->postmeta} alt
                     ON thumb.meta_value = alt.post_id
                     AND alt.meta_key = %s
                     AND alt.meta_value != ''
                 WHERE p.post_type = %s AND p.post_status = %s",
                '_thumbnail_id',
                '_wp_attachment_image_alt',
                'product',
                'publish'
            )
        );

        self::$productIdsWithAltCache = array_map('intval', $ids);

        return self::$productIdsWithAltCache;
    }

    /**
     * Get IDs of published products whose featured image has no alt text or no featured image.
     *
     * @return int[]
     */
    private static function getProductIdsMissingAlt(): array
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT p.ID
                 FROM {$wpdb->posts} p
                 LEFT JOIN {$wpdb->postmeta} thumb
                     ON p.ID = thumb.post_id AND thumb.meta_key = %s
                 LEFT JOIN {$wpdb->postmeta} alt
                     ON thumb.meta_value = alt.post_id
                     AND alt.meta_key = %s
                     AND alt.meta_value != ''
                 WHERE p.post_type = %s
                     AND p.post_status = %s
                     AND (thumb.meta_value IS NULL OR alt.meta_value IS NULL OR alt.meta_value = '')",
                '_thumbnail_id',
                '_wp_attachment_image_alt',
                'product',
                'publish'
            )
        );

        return array_map('intval', $ids);
    }

    /**
     * Get product IDs whose featured image alt text has quality issues (generic or too long).
     *
     * @param string $quality 'generic' or 'too_long'
     * @return int[]
     */
    private static function getProductIdsWithQualityIssues(string $quality): array
    {
        global $wpdb;

        // Get product IDs and their featured image alt text in one query
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DISTINCT p.ID, alt.meta_value AS alt_text
                 FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} thumb
                     ON p.ID = thumb.post_id AND thumb.meta_key = %s
                 INNER JOIN {$wpdb->postmeta} alt
                     ON thumb.meta_value = alt.post_id
                     AND alt.meta_key = %s
                     AND alt.meta_value != ''
                 WHERE p.post_type = %s AND p.post_status = %s",
                '_thumbnail_id',
                '_wp_attachment_image_alt',
                'product',
                'publish'
            )
        );

        if (empty($rows)) {
            return [];
        }

        $maxLength = (int) Settings::get('icais_max_length', 125);
        $filtered = [];

        foreach ($rows as $row) {
            $alt = $row->alt_text;
            if ($quality === 'generic' && AltTextHelper::isGeneric($alt)) {
                $filtered[] = (int) $row->ID;
            } elseif ($quality === 'too_long' && mb_strlen($alt) > $maxLength) {
                $filtered[] = (int) $row->ID;
            }
        }

        return $filtered;
    }

    /**
     * Count published products whose featured image has a non-empty generated title.
     * Title is tracked in _icais_generated_title on the attachment (mirrors Scanner).
     */
    private static function countProductsWithTitle(): int
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT p.ID)
                 FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} thumb
                     ON p.ID = thumb.post_id AND thumb.meta_key = %s
                 INNER JOIN {$wpdb->postmeta} ttl
                     ON thumb.meta_value = ttl.post_id
                     AND ttl.meta_key = %s
                     AND ttl.meta_value != ''
                 WHERE p.post_type = %s AND p.post_status = %s",
                '_thumbnail_id',
                '_icais_generated_title',
                'product',
                'publish'
            )
        );
    }

    /**
     * Get IDs of published products whose featured image has a non-empty generated title.
     *
     * @return int[]
     */
    private static function getProductIdsWithTitle(): array
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT p.ID
                 FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} thumb
                     ON p.ID = thumb.post_id AND thumb.meta_key = %s
                 INNER JOIN {$wpdb->postmeta} ttl
                     ON thumb.meta_value = ttl.post_id
                     AND ttl.meta_key = %s
                     AND ttl.meta_value != ''
                 WHERE p.post_type = %s AND p.post_status = %s",
                '_thumbnail_id',
                '_icais_generated_title',
                'product',
                'publish'
            )
        );

        return array_map('intval', $ids);
    }

    /**
     * Get IDs of published products whose featured image has no generated title
     * (or no featured image at all).
     *
     * @return int[]
     */
    private static function getProductIdsMissingTitle(): array
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT p.ID
                 FROM {$wpdb->posts} p
                 LEFT JOIN {$wpdb->postmeta} thumb
                     ON p.ID = thumb.post_id AND thumb.meta_key = %s
                 LEFT JOIN {$wpdb->postmeta} ttl
                     ON thumb.meta_value = ttl.post_id
                     AND ttl.meta_key = %s
                     AND ttl.meta_value != ''
                 WHERE p.post_type = %s
                     AND p.post_status = %s
                     AND (thumb.meta_value IS NULL OR ttl.meta_value IS NULL OR ttl.meta_value = '')",
                '_thumbnail_id',
                '_icais_generated_title',
                'product',
                'publish'
            )
        );

        return array_map('intval', $ids);
    }

    /**
     * Get IDs of products whose featured image has a generated title whose
     * attachment post_title exceeds the configured title length (mirrors Scanner).
     *
     * @return int[]
     */
    private static function getProductIdsWithTitleTooLong(): array
    {
        global $wpdb;

        // Featured-image attachment post_title for products that have a generated title.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DISTINCT p.ID, att.post_title AS image_title
                 FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} thumb
                     ON p.ID = thumb.post_id AND thumb.meta_key = %s
                 INNER JOIN {$wpdb->postmeta} ttl
                     ON thumb.meta_value = ttl.post_id
                     AND ttl.meta_key = %s
                     AND ttl.meta_value != ''
                 INNER JOIN {$wpdb->posts} att
                     ON att.ID = thumb.meta_value
                 WHERE p.post_type = %s AND p.post_status = %s",
                '_thumbnail_id',
                '_icais_generated_title',
                'product',
                'publish'
            )
        );

        if (empty($rows)) {
            return [];
        }

        $maxLength = (int) Settings::get('icais_title_max_length', 30);
        $filtered = [];

        foreach ($rows as $row) {
            if (mb_strlen((string) $row->image_title) > $maxLength) {
                $filtered[] = (int) $row->ID;
            }
        }

        return $filtered;
    }

    /**
     * Count published products whose featured image has a non-empty generated caption.
     * Caption is tracked in _icais_generated_caption on the attachment (mirrors Scanner).
     */
    private static function countProductsWithCaption(): int
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT p.ID)
                 FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} thumb
                     ON p.ID = thumb.post_id AND thumb.meta_key = %s
                 INNER JOIN {$wpdb->postmeta} cap
                     ON thumb.meta_value = cap.post_id
                     AND cap.meta_key = %s
                     AND cap.meta_value != ''
                 WHERE p.post_type = %s AND p.post_status = %s",
                '_thumbnail_id',
                '_icais_generated_caption',
                'product',
                'publish'
            )
        );
    }

    /**
     * Get IDs of published products whose featured image has a non-empty generated caption.
     *
     * @return int[]
     */
    private static function getProductIdsWithCaption(): array
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT p.ID
                 FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} thumb
                     ON p.ID = thumb.post_id AND thumb.meta_key = %s
                 INNER JOIN {$wpdb->postmeta} cap
                     ON thumb.meta_value = cap.post_id
                     AND cap.meta_key = %s
                     AND cap.meta_value != ''
                 WHERE p.post_type = %s AND p.post_status = %s",
                '_thumbnail_id',
                '_icais_generated_caption',
                'product',
                'publish'
            )
        );

        return array_map('intval', $ids);
    }

    /**
     * Get IDs of published products whose featured image has no generated caption
     * (or no featured image at all).
     *
     * @return int[]
     */
    private static function getProductIdsMissingCaption(): array
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT p.ID
                 FROM {$wpdb->posts} p
                 LEFT JOIN {$wpdb->postmeta} thumb
                     ON p.ID = thumb.post_id AND thumb.meta_key = %s
                 LEFT JOIN {$wpdb->postmeta} cap
                     ON thumb.meta_value = cap.post_id
                     AND cap.meta_key = %s
                     AND cap.meta_value != ''
                 WHERE p.post_type = %s
                     AND p.post_status = %s
                     AND (thumb.meta_value IS NULL OR cap.meta_value IS NULL OR cap.meta_value = '')",
                '_thumbnail_id',
                '_icais_generated_caption',
                'product',
                'publish'
            )
        );

        return array_map('intval', $ids);
    }

    /**
     * Get IDs of products whose featured image has a generated caption whose
     * attachment post_excerpt exceeds the configured caption length (mirrors Scanner).
     *
     * @return int[]
     */
    private static function getProductIdsWithCaptionTooLong(): array
    {
        global $wpdb;

        // Featured-image attachment post_excerpt for products that have a generated caption.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DISTINCT p.ID, att.post_excerpt AS image_caption
                 FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} thumb
                     ON p.ID = thumb.post_id AND thumb.meta_key = %s
                 INNER JOIN {$wpdb->postmeta} cap
                     ON thumb.meta_value = cap.post_id
                     AND cap.meta_key = %s
                     AND cap.meta_value != ''
                 INNER JOIN {$wpdb->posts} att
                     ON att.ID = thumb.meta_value
                 WHERE p.post_type = %s AND p.post_status = %s",
                '_thumbnail_id',
                '_icais_generated_caption',
                'product',
                'publish'
            )
        );

        if (empty($rows)) {
            return [];
        }

        $maxLength = (int) Settings::get('icais_caption_max_length', 150);
        $filtered = [];

        foreach ($rows as $row) {
            if (mb_strlen((string) $row->image_caption) > $maxLength) {
                $filtered[] = (int) $row->ID;
            }
        }

        return $filtered;
    }

    /**
     * Get product categories with product counts.
     *
     * @return array
     */
    public static function getCategories(): array
    {
        if (!self::isActive()) {
            return [];
        }

        $terms = get_terms([
            'taxonomy' => 'product_cat',
            'hide_empty' => true,
            'orderby' => 'name',
            'order' => 'ASC',
        ]);

        if (is_wp_error($terms)) {
            return [];
        }

        $categories = [];
        foreach ($terms as $term) {
            // Skip "Uncategorized" if it has the default slug
            if ($term->slug === 'uncategorized' && $term->count === 0) {
                continue;
            }
            $categories[] = [
                'id' => $term->term_id,
                'name' => $term->name,
                'slug' => $term->slug,
                'count' => $term->count,
                'parent' => $term->parent,
            ];
        }

        return $categories;
    }

    public static function generateProductAltText(int $productId, string $tone = 'professional'): array
    {
        if (!self::isActive()) {
            return ['success' => false, 'error' => 'WooCommerce is not active'];
        }

        $product = wc_get_product($productId);
        if (!$product) {
            return ['success' => false, 'error' => 'Product not found'];
        }

        $thumbnailId = $product->get_image_id();
        if (!$thumbnailId) {
            return ['success' => false, 'error' => 'Product has no featured image'];
        }

        // Daily usage limit check
        if (!UsageLimiter::canGenerate(get_current_user_id(), (int) $thumbnailId)) {
            return UsageLimiter::limitReachedError();
        }

        $imageUrl = wp_get_attachment_url($thumbnailId);
        if (!$imageUrl) {
            return ['success' => false, 'error' => 'Failed to get image URL'];
        }

        $aiProvider = AIFactory::getProviderForUser();
        if (!$aiProvider) {
            return ['success' => false, 'error' => 'No API key configured'];
        }

        $contextPrompt = Settings::get('icais_woo_include_product_info', '1')
            ? self::buildProductContextPrompt($product)
            : null;
        $result = $aiProvider->generateAltText($imageUrl, $tone, $contextPrompt);

        if (!$result['success']) {
            return $result;
        }

        // Track daily usage
        UsageLimiter::recordUsage(get_current_user_id(), (int) $thumbnailId);

        $originalAlt = get_post_meta($thumbnailId, '_wp_attachment_image_alt', true);

        update_post_meta($thumbnailId, '_wp_attachment_image_alt', sanitize_text_field($result['alt_text']));

        $history = StatsTracker::recordGeneration([
            'attachment_id' => $thumbnailId,
            'original_alt'  => $originalAlt,
            'alt_text'      => $result['alt_text'],
            'user_id'       => get_current_user_id(),
            'tone'          => $tone,
            'status'        => 'accepted',
            'tokens'        => $result['tokens'] ?? 0,
            'cost'          => $result['cost'] ?? 0,
        ]);

        return [
            'success' => true,
            'product_id' => $productId,
            'alt_text' => $result['alt_text'],
            'history_id' => $history->id,
            'tokens' => $result['tokens'] ?? 0,
            'cost' => $result['cost'] ?? 0,
        ];
    }

    public static function buildProductContextPrompt($product): string
    {
        $context = [];

        $context[] = 'Product: ' . $product->get_name();

        $sku = $product->get_sku();
        if ($sku) {
            $context[] = 'SKU: ' . $sku;
        }

        $categories = wp_get_post_terms($product->get_id(), 'product_cat', ['fields' => 'names']);
        if (!is_wp_error($categories) && !empty($categories)) {
            $context[] = 'Categories: ' . implode(', ', array_slice($categories, 0, 3));
        }

        $tags = wp_get_post_terms($product->get_id(), 'product_tag', ['fields' => 'names']);
        if (!is_wp_error($tags) && !empty($tags)) {
            $context[] = 'Tags: ' . implode(', ', array_slice($tags, 0, 5));
        }

        $price = $product->get_price_html();
        if ($price) {
            $context[] = 'Price: ' . wp_strip_all_tags($price);
        }

        return implode(' | ', $context);
    }

    public static function getGalleryImages(int $productId): array
    {
        $product = wc_get_product($productId);
        if (!$product) {
            return [];
        }

        $galleryIds = $product->get_gallery_image_ids();
        $gallery = [];

        foreach ($galleryIds as $id) {
            $alt = get_post_meta($id, '_wp_attachment_image_alt', true);
            $galleryPost = get_post($id);
            $title = $galleryPost ? $galleryPost->post_title : '';
            $gallery[] = [
                'id' => $id,
                'url' => wp_get_attachment_url($id),
                'thumb_url' => wp_get_attachment_thumb_url($id),
                'alt_text' => $alt,
                'has_alt' => !empty($alt),
                'title' => $title,
                'has_title' => !empty($title),
            ];
        }

        return $gallery;
    }

}
