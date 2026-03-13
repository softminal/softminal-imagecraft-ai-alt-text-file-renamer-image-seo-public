<?php

namespace Softminal\ImageCraft\Http\Controllers;

use Softminal\ImageCraft\Services\WooCommerce;
use Softminal\ImageCraft\Services\Settings;
use Softminal\ImageCraft\Services\StatsTracker;

if (!defined('ABSPATH')) {
    exit;
}

class WooCommerceController extends Controller
{
    public function getProducts($request)
    {
        if (!WooCommerce::isActive()) {
            return $this->response([
                'success' => false,
                'message' => __('WooCommerce is not active', 'imagecraft-ai-alt-text-file-renamer-image-seo')
            ], 400);
        }

        $perPage = min((int) ($request['per_page'] ?? 20), 50);
        $page = (int) ($request['page'] ?? 1);
        $filter = sanitize_text_field($request['filter'] ?? 'all');
        $category = (int) ($request['category'] ?? 0);

        $result = WooCommerce::getProducts($perPage, $page, $filter, $category);

        return $this->response([
            'success' => true,
            'products' => $result['products'],
            'stats' => WooCommerce::getProductStats(),
            'pagination' => [
                'current_page' => $page,
                'total_pages' => $result['pages'],
                'total' => $result['total'],
            ],
        ]);
    }

    public function getStats($request)
    {
        if (!WooCommerce::isActive()) {
            return $this->response([
                'success' => false,
                'message' => __('WooCommerce is not active', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
                'is_active' => false,
            ]);
        }

        return $this->response([
            'success' => true,
            'is_active' => true,
            'stats' => WooCommerce::getProductStats(),
        ]);
    }

    public function getCategories($request)
    {
        if (!WooCommerce::isActive()) {
            return $this->response([
                'success' => false,
                'message' => __('WooCommerce is not active', 'imagecraft-ai-alt-text-file-renamer-image-seo')
            ], 400);
        }

        return $this->response([
            'success' => true,
            'categories' => WooCommerce::getCategories(),
        ]);
    }

    public function generate($request)
    {
        if (!WooCommerce::isActive()) {
            return $this->response([
                'success' => false,
                'message' => __('WooCommerce is not active', 'imagecraft-ai-alt-text-file-renamer-image-seo')
            ], 400);
        }

        $params = $request->get_json_params();
        $productId = (int) ($params['product_id'] ?? 0);
        $tone = sanitize_text_field($params['tone'] ?? Settings::get('icais_woo_default_tone', 'professional'));

        if (!$productId) {
            return $this->response([
                'success' => false,
                'message' => __('Product ID is required', 'imagecraft-ai-alt-text-file-renamer-image-seo')
            ], 400);
        }

        $result = WooCommerce::generateProductAltText($productId, $tone);

        if (!$result['success']) {
            return $this->response($result, 400);
        }

        return $this->response($result);
    }

    public function generateGallery($request)
    {
        if (!WooCommerce::isActive()) {
            return $this->response([
                'success' => false,
                'message' => __('WooCommerce is not active', 'imagecraft-ai-alt-text-file-renamer-image-seo')
            ], 400);
        }

        $params = $request->get_json_params();
        $productId = (int) ($params['product_id'] ?? 0);
        $tone = sanitize_text_field($params['tone'] ?? Settings::get('icais_woo_default_tone', 'professional'));

        if (!$productId) {
            return $this->response([
                'success' => false,
                'message' => __('Product ID is required', 'imagecraft-ai-alt-text-file-renamer-image-seo')
            ], 400);
        }

        $product = wc_get_product($productId);
        if (!$product) {
            return $this->response([
                'success' => false,
                'message' => __('Product not found', 'imagecraft-ai-alt-text-file-renamer-image-seo')
            ], 404);
        }

        $galleryIds = $product->get_gallery_image_ids();
        if (empty($galleryIds)) {
            return $this->response([
                'success' => false,
                'message' => __('Product has no gallery images', 'imagecraft-ai-alt-text-file-renamer-image-seo')
            ], 400);
        }

        $results = [];
        $aiProvider = \Softminal\ImageCraft\Services\AI\AIFactory::getProviderForUser();

        if (!$aiProvider) {
            return $this->response([
                'success' => false,
                'message' => __('No API key configured', 'imagecraft-ai-alt-text-file-renamer-image-seo')
            ], 400);
        }

        $contextPrompt = Settings::get('icais_woo_include_product_info', true)
            ? WooCommerce::buildProductContextPrompt($product)
            : null;
        $userId = get_current_user_id();

        foreach ($galleryIds as $index => $attachmentId) {
            $imageUrl = wp_get_attachment_url($attachmentId);
            if (!$imageUrl) {
                $results[$index] = [
                    'success' => false,
                    'error' => __('Failed to get image URL', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
                ];
                continue;
            }

            $existingAlt = get_post_meta($attachmentId, '_wp_attachment_image_alt', true);
            if (!empty($existingAlt) && Settings::get('icais_skip_existing', false)) {
                $results[$index] = [
                    'success' => true,
                    'attachment_id' => $attachmentId,
                    'skipped' => true,
                    'alt_text' => $existingAlt,
                ];
                continue;
            }

            $result = $aiProvider->generateAltText($imageUrl, $tone, $contextPrompt);

            if ($result['success']) {
                update_post_meta($attachmentId, '_wp_attachment_image_alt', sanitize_text_field($result['alt_text']));

                StatsTracker::recordGeneration([
                    'attachment_id' => $attachmentId,
                    'original_alt'  => $existingAlt,
                    'alt_text'      => $result['alt_text'],
                    'user_id'       => $userId,
                    'tone'          => $tone,
                    'status'        => 'accepted',
                    'tokens'        => $result['tokens'] ?? 0,
                    'cost'          => $result['cost'] ?? 0,
                ]);

                $results[$index] = [
                    'success' => true,
                    'attachment_id' => $attachmentId,
                    'alt_text' => $result['alt_text'],
                    'tokens' => $result['tokens'] ?? 0,
                    'cost' => $result['cost'] ?? 0,
                ];
            } else {
                $results[$index] = [
                    'success' => false,
                    'attachment_id' => $attachmentId,
                    'error' => $result['error'] ?? 'Unknown error',
                ];
            }
        }

        return $this->response([
            'success' => true,
            'product_id' => $productId,
            'results' => $results,
        ]);
    }

    public function getGallery($request)
    {
        if (!WooCommerce::isActive()) {
            return $this->response([
                'success' => false,
                'message' => __('WooCommerce is not active', 'imagecraft-ai-alt-text-file-renamer-image-seo')
            ], 400);
        }

        $productId = (int) ($request['product_id'] ?? 0);

        if (!$productId) {
            return $this->response([
                'success' => false,
                'message' => __('Product ID is required', 'imagecraft-ai-alt-text-file-renamer-image-seo')
            ], 400);
        }

        $gallery = WooCommerce::getGalleryImages($productId);

        return $this->response([
            'success' => true,
            'product_id' => $productId,
            'gallery' => $gallery,
        ]);
    }
}
