<?php

namespace SmartAltTextAi\Includes\Services;

if (!defined('ABSPATH')) {
    exit;
}

use SmartAltTextAi\Includes\Services\AI\AIFactory;

class WooCommerce
{
    public static function isActive(): bool
    {
        return class_exists('WooCommerce');
    }

    public static function getProducts(?int $limit = -1, int $page = 1, string $filter = 'all'): array
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

        switch ($filter) {
            case 'missing':
                 // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
                $args['meta_query'] = [
                    [
                        //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
                        'key' => '_thumbnail_id',
                        'compare' => 'EXISTS',
                    ],
                ];
                // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
                $args['tax_query'] = [
                    [
                        'taxonomy' => 'product_type',
                        'operator' => 'NOT IN',
                        'terms' => ['grouped'],
                    ],
                ];
                break;
            case 'has_alt':
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
                'price' => $product->get_price_html(),
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

        $totalArgs = [
            'post_type' => 'product',
            'posts_per_page' => 1,
            'post_status' => 'publish',
            'fields' => 'ids',
        ];

        $totalQuery = new \WP_Query($totalArgs);
        $totalProducts = $totalQuery->found_posts;

        $missingArgs = [
            'post_type' => 'product',
            'posts_per_page' => 1,
            'post_status' => 'publish',
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
            'meta_query' => [
                [
                    'key' => '_thumbnail_id',
                    'compare' => 'EXISTS',
                ],
            ],
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
            'tax_query' => [
                [
                    'taxonomy' => 'product_type',
                    'operator' => 'NOT IN',
                    'terms' => ['grouped'],
                ],
            ],
            'fields' => 'ids',
        ];

        $missingQuery = new \WP_Query($missingArgs);
        $missingAlt = $missingQuery->found_posts;

        $hasAlt = $totalProducts - $missingAlt;
        $percentage = $totalProducts > 0 ? round(($missingAlt / $totalProducts) * 100, 1) : 0;

        return [
            'total_products' => $totalProducts,
            'missing_alt' => $missingAlt,
            'has_alt' => $hasAlt,
            'missing_percentage' => $percentage,
        ];
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

        $contextPrompt = self::buildProductContextPrompt($product);
        $result = $aiProvider->generateAltText($imageUrl, $tone, $contextPrompt);

        if (!$result['success']) {
            return $result;
        }

        $originalAlt = get_post_meta($thumbnailId, '_wp_attachment_image_alt', true);

        $apiSetting = \SmartAltTextAi\Includes\Models\ApiSetting::getActive(get_current_user_id());

        update_post_meta($thumbnailId, '_wp_attachment_image_alt', $result['alt_text']);

        $history = \SmartAltTextAi\Includes\Models\History::create([
            'attachment_id' => $thumbnailId,
            'original_alt' => $originalAlt,
            'generated_alt' => $result['alt_text'],
            'user_id' => get_current_user_id(),
            'api_provider' => $apiSetting ? $apiSetting->api_provider : 'unknown',
            'tone' => $tone,
            'status' => 'accepted',
            'tokens_used' => $result['tokens'] ?? 0,
            'cost_estimate' => $result['cost'] ?? 0,
        ]);

        $stats = \SmartAltTextAi\Includes\Models\Stats::getOrCreate(get_current_user_id(), gmdate('Y-m-d'));
        $stats->increment('images_processed');
        $stats->increment('images_accepted');
        $stats->total_cost_estimate = (float) $stats->total_cost_estimate + ($result['cost'] ?? 0);
        if ($apiSetting) {
            $stats->api_provider = $apiSetting->api_provider;
        }
        $stats->save();

        return [
            'success' => true,
            'product_id' => $productId,
            'alt_text' => $result['alt_text'],
            'history_id' => $history->id,
            'tokens' => $result['tokens'] ?? 0,
            'cost' => $result['cost'] ?? 0,
        ];
    }

    private static function buildProductContextPrompt($product): string
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
            $gallery[] = [
                'id' => $id,
                'url' => wp_get_attachment_url($id),
                'thumb_url' => wp_get_attachment_thumb_url($id),
                'alt_text' => get_post_meta($id, '_wp_attachment_image_alt', true),
                'has_alt' => !empty(get_post_meta($id, '_wp_attachment_image_alt', true)),
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
