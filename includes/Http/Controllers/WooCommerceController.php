<?php

namespace SmartAltTextAi\Includes\Http\Controllers;

use SmartAltTextAi\Includes\Models\History;
use SmartAltTextAi\Includes\Models\ApiSetting;
use SmartAltTextAi\Includes\Models\Stats;
use SmartAltTextAi\Includes\Services\WooCommerce;
use SmartAltTextAi\Includes\Services\Settings;

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
                'message' => 'WooCommerce is not active'
            ], 400);
        }

        $perPage = min((int) ($request['per_page'] ?? 20), 50);
        $page = (int) ($request['page'] ?? 1);
        $filter = sanitize_text_field($request['filter'] ?? 'all');

        $result = WooCommerce::getProducts($perPage, $page, $filter);

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
                'message' => 'WooCommerce is not active',
                'is_active' => false,
            ]);
        }

        return $this->response([
            'success' => true,
            'is_active' => true,
            'stats' => WooCommerce::getProductStats(),
        ]);
    }

    public function generate($request)
    {
        if (!WooCommerce::isActive()) {
            return $this->response([
                'success' => false,
                'message' => 'WooCommerce is not active'
            ], 400);
        }

        $params = $request->get_json_params();
        $productId = (int) ($params['product_id'] ?? 0);
        $tone = sanitize_text_field($params['tone'] ?? Settings::get('satai_woo_default_tone', 'professional'));

        if (!$productId) {
            return $this->response([
                'success' => false,
                'message' => 'Product ID is required'
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
                'message' => 'WooCommerce is not active'
            ], 400);
        }

        $params = $request->get_json_params();
        $productId = (int) ($params['product_id'] ?? 0);
        $tone = sanitize_text_field($params['tone'] ?? Settings::get('satai_woo_default_tone', 'professional'));

        if (!$productId) {
            return $this->response([
                'success' => false,
                'message' => 'Product ID is required'
            ], 400);
        }

        $product = wc_get_product($productId);
        if (!$product) {
            return $this->response([
                'success' => false,
                'message' => 'Product not found'
            ], 404);
        }

        $galleryIds = $product->get_gallery_image_ids();
        if (empty($galleryIds)) {
            return $this->response([
                'success' => false,
                'message' => 'Product has no gallery images'
            ], 400);
        }

        $results = [];
        $aiProvider = \SmartAltTextAi\Includes\Services\AI\AIFactory::getProviderForUser();

        if (!$aiProvider) {
            return $this->response([
                'success' => false,
                'message' => 'No API key configured'
            ], 400);
        }

        $contextPrompt = WooCommerce::buildProductContextPrompt($product);
        $userId = get_current_user_id();
        $apiSetting = ApiSetting::getActive($userId);

        foreach ($galleryIds as $index => $attachmentId) {
            $imageUrl = wp_get_attachment_url($attachmentId);
            if (!$imageUrl) {
                $results[$index] = [
                    'success' => false,
                    'error' => 'Failed to get image URL',
                ];
                continue;
            }

            $altText = get_post_meta($attachmentId, '_wp_attachment_image_alt', true);
            if (!empty($altText) && Settings::get('satai_skip_existing', false)) {
                $results[$index] = [
                    'success' => true,
                    'attachment_id' => $attachmentId,
                    'skipped' => true,
                    'alt_text' => $altText,
                ];
                continue;
            }

            $result = $aiProvider->generateAltText($imageUrl, $tone, $contextPrompt);

            if ($result['success']) {
                $originalAlt = get_post_meta($attachmentId, '_wp_attachment_image_alt', true);
                update_post_meta($attachmentId, '_wp_attachment_image_alt', $result['alt_text']);

                History::create([
                    'attachment_id' => $attachmentId,
                    'original_alt' => $originalAlt,
                    'generated_alt' => $result['alt_text'],
                    'user_id' => $userId,
                    'api_provider' => $apiSetting ? $apiSetting->api_provider : 'unknown',
                    'tone' => $tone,
                    'status' => 'accepted',
                    'tokens_used' => $result['tokens'] ?? 0,
                    'cost_estimate' => $result['cost'] ?? 0,
                ]);

                $stats = Stats::getOrCreate($userId, gmdate('Y-m-d'));
                $stats->increment('images_processed');
                $stats->increment('images_accepted');
                $stats->total_cost_estimate = (float) $stats->total_cost_estimate + ($result['cost'] ?? 0);
                if ($apiSetting) {
                    $stats->api_provider = $apiSetting->api_provider;
                }
                $stats->save();

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
                'message' => 'WooCommerce is not active'
            ], 400);
        }

        $productId = (int) ($request['product_id'] ?? 0);

        if (!$productId) {
            return $this->response([
                'success' => false,
                'message' => 'Product ID is required'
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
