<?php

namespace Softminal\ImageCraft\Services;

if (!defined('ABSPATH')) {
    exit;
}

use Softminal\ImageCraft\Services\AI\AIFactory;
use Softminal\ImageCraft\Services\StatsTracker;

class WooCommerce
{
    public static function isActive(): bool
    {
        return class_exists('WooCommerce');
    }

    public static function getProducts(?int $limit = -1, int $page = 1, string $filter = 'all', int $category = 0): array
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

        switch ($filter) {
            case 'missing':
                $missingIds = self::getProductIdsMissingAlt();
                $args['post__in'] = !empty($missingIds) ? $missingIds : [0];
                break;
            case 'has_alt':
                $hasAltIds = self::getProductIdsWithAlt();
                $args['post__in'] = !empty($hasAltIds) ? $hasAltIds : [0];
                break;
        }

        $query = new \WP_Query($args);

        $products = [];
        foreach ($query->posts as $post) {
            $product = wc_get_product($post->ID);
            if (!$product) {
                continue;
            }

            $thumbnailId = $product->get_image_id();
            $altText = '';

            if ($thumbnailId) {
                $altText = get_post_meta($thumbnailId, '_wp_attachment_image_alt', true);
            }

            $galleryIds = $product->get_gallery_image_ids();

            $products[] = [
                'id' => $post->ID,
                'title' => $product->get_name(),
                'sku' => $product->get_sku(),
                'thumbnail_id' => $thumbnailId,
                'thumbnail_url' => $thumbnailId ? wp_get_attachment_thumb_url($thumbnailId) : null,
                'full_url' => $thumbnailId ? wp_get_attachment_url($thumbnailId) : null,
                'alt_text' => $altText,
                'has_alt' => !empty($altText),
                'gallery_ids' => array_values($galleryIds),
                'price' => wp_kses_post($product->get_price_html()),
                'status' => $product->get_status(),
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

        $hasAlt = count(self::getProductIdsWithAlt());
        $missingAlt = $totalProducts - $hasAlt;
        $percentage = $totalProducts > 0 ? round(($missingAlt / $totalProducts) * 100, 1) : 0;

        return [
            'total_products' => $totalProducts,
            'missing_alt' => $missingAlt,
            'has_alt' => $hasAlt,
            'missing_percentage' => $percentage,
        ];
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

        $imageUrl = wp_get_attachment_url($thumbnailId);
        if (!$imageUrl) {
            return ['success' => false, 'error' => 'Failed to get image URL'];
        }

        $aiProvider = AIFactory::getProviderForUser();
        if (!$aiProvider) {
            return ['success' => false, 'error' => 'No API key configured'];
        }

        $contextPrompt = Settings::get('icais_woo_include_product_info', true)
            ? self::buildProductContextPrompt($product)
            : null;
        $result = $aiProvider->generateAltText($imageUrl, $tone, $contextPrompt);

        if (!$result['success']) {
            return $result;
        }

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
            $gallery[] = [
                'id' => $id,
                'url' => wp_get_attachment_url($id),
                'thumb_url' => wp_get_attachment_thumb_url($id),
                'alt_text' => $alt,
                'has_alt' => !empty($alt),
            ];
        }

        return $gallery;
    }

    public static function updateGalleryAltTexts(int $productId, array $altTexts): bool
    {
        $product = wc_get_product($productId);
        if (!$product) {
            return false;
        }

        $galleryIds = $product->get_gallery_image_ids();

        foreach ($galleryIds as $index => $attachmentId) {
            if (isset($altTexts[$index])) {
                update_post_meta($attachmentId, '_wp_attachment_image_alt', sanitize_text_field($altTexts[$index]));
            }
        }

        return true;
    }

    public static function isProductTypeSupported(int $productId): bool
    {
        $product = wc_get_product($productId);
        if (!$product) {
            return false;
        }

        $type = $product->get_type();

        return in_array($type, ['simple', 'variable', 'external']);
    }
}
