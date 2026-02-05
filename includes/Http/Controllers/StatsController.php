<?php

namespace SmartAltTextAi\Includes\Http\Controllers;

use SmartAltTextAi\Includes\Models\Stats;
use SmartAltTextAi\Includes\Models\History;
use SmartAltTextAi\Includes\Models\ApiSetting;

if (!defined('ABSPATH')) {
    exit;
}

class StatsController extends Controller
{
    /**
     * Get dashboard stats
     */
    public function dashboard($request)
    {
        $userId = get_current_user_id();
        
        // Get total images
        $totalQuery = new \WP_Query([
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'post_status' => 'inherit',
            'posts_per_page' => -1,
            'fields' => 'ids'
        ]);
        
        $missingQuery = new \WP_Query([
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'post_status' => 'inherit',
            'posts_per_page' => -1,
            'fields' => 'ids',
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Required to find images missing alt text; optimized with fields=ids
            'meta_query' => [
                'relation' => 'OR',
                [
                    'key' => '_wp_attachment_image_alt',
                    'compare' => 'NOT EXISTS'
                ],
                [
                    'key' => '_wp_attachment_image_alt',
                    'value' => '',
                    'compare' => '='
                ]
            ]
        ]);
        
        $totalImages = $totalQuery->found_posts;
        $missingAlt = $missingQuery->found_posts;
        
        // Get this month's stats
        $thisMonth = gmdate('Y-m');
        $monthStats = Stats::where('user_id', $userId)
            ->andWhere('date', 'LIKE', $thisMonth . '%')
            ->get();
        
        $thisMonthProcessed = 0;
        $thisMonthCost = 0;
        foreach ($monthStats as $stat) {
            $thisMonthProcessed += $stat->images_processed;
            $thisMonthCost += (float) $stat->total_cost_estimate;
        }
        
        // Get total processed
        $allStats = Stats::where('user_id', $userId)->get();
        $totalProcessed = 0;
        $totalCost = 0;
        foreach ($allStats as $stat) {
            $totalProcessed += $stat->images_processed;
            $totalCost += (float) $stat->total_cost_estimate;
        }
        
        // Get success rate
        $accepted = History::where('user_id', $userId)
            ->andWhere('status', 'accepted')
            ->get();
        $totalHistory = History::where('user_id', $userId)->get();
        $successRate = count($totalHistory) > 0 ? round((count($accepted) / count($totalHistory)) * 100, 2) : 0;
        
        // Get API status
        $apiSetting = ApiSetting::getActive($userId);
        $apiStatus = $apiSetting ? [
            'connected' => true,
            'provider' => $apiSetting->api_provider
        ] : [
            'connected' => false,
            'provider' => null
        ];
        
        return $this->response([
            'success' => true,
            'stats' => [
                'total_images' => $totalImages,
                'missing_alt' => $missingAlt,
                'missing_percentage' => $totalImages > 0 ? round(($missingAlt / $totalImages) * 100, 2) : 0,
                'this_month_processed' => $thisMonthProcessed,
                'total_processed' => $totalProcessed,
                'success_rate' => $successRate,
                'this_month_cost' => round($thisMonthCost, 4),
                'total_cost' => round($totalCost, 4),
                'api_status' => $apiStatus
            ]
        ]);
    }

    /**
     * SEO audit report for all media library images
     */
    public function audit($request)
    {
        $maxLength = (int) \SmartAltTextAi\Includes\Services\Settings::get('satai_max_length', 125);

        // Get all image IDs
        $allQuery = new \WP_Query([
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'post_status' => 'inherit',
            'posts_per_page' => -1,
            'fields' => 'ids',
        ]);

        $totalImages = $allQuery->found_posts;
        $imageIds = $allQuery->posts;

        $missing = 0;
        $generic = 0;
        $tooLong = 0;
        $good = 0;

        $genericExamples = [];
        $tooLongExamples = [];

        foreach ($imageIds as $id) {
            $alt = get_post_meta($id, '_wp_attachment_image_alt', true);

            if (empty($alt)) {
                $missing++;
                continue;
            }

            $issues = [];

            if ($this->isGenericAlt($alt)) {
                $generic++;
                $issues[] = 'generic';
                if (count($genericExamples) < 5) {
                    $thumbnail = wp_get_attachment_image_src($id, 'thumbnail');
                    $genericExamples[] = [
                        'id' => $id,
                        'alt_text' => $alt,
                        'filename' => basename(get_attached_file($id)),
                        'thumbnail' => $thumbnail ? $thumbnail[0] : null,
                    ];
                }
            }

            if (mb_strlen($alt) > $maxLength) {
                $tooLong++;
                $issues[] = 'too_long';
                if (count($tooLongExamples) < 5) {
                    $thumbnail = wp_get_attachment_image_src($id, 'thumbnail');
                    $tooLongExamples[] = [
                        'id' => $id,
                        'alt_text' => $alt,
                        'length' => mb_strlen($alt),
                        'filename' => basename(get_attached_file($id)),
                        'thumbnail' => $thumbnail ? $thumbnail[0] : null,
                    ];
                }
            }

            if (empty($issues)) {
                $good++;
            }
        }

        $score = $totalImages > 0 ? round(($good / $totalImages) * 100) : 100;

        return $this->response([
            'success' => true,
            'audit' => [
                'total_images' => $totalImages,
                'good' => $good,
                'missing' => $missing,
                'generic' => $generic,
                'too_long' => $tooLong,
                'score' => $score,
                'max_length' => $maxLength,
                'generic_examples' => $genericExamples,
                'too_long_examples' => $tooLongExamples,
            ],
        ]);
    }

    /**
     * Check if alt text looks like a generic/placeholder value
     */
    private function isGenericAlt(string $alt): bool
    {
        $alt = trim($alt);

        // Too short to be useful
        if (mb_strlen($alt) < 4) {
            return true;
        }

        $lower = strtolower($alt);

        // Common placeholder words
        $placeholders = [
            'image', 'photo', 'picture', 'img', 'untitled',
            'default', 'placeholder', 'no alt', 'alt text',
            'thumbnail', 'banner', 'hero', 'logo',
        ];
        if (in_array($lower, $placeholders, true)) {
            return true;
        }

        // Filename patterns: IMG_1234, DSC_0001, Screenshot 2024, DCIM, P1010234
        if (preg_match('/^(img|dsc|dcim|p\d{3,}|screenshot|screen\s*shot|photo_|vid_)/i', $alt)) {
            return true;
        }

        // Just a number or ID
        if (preg_match('/^\d+$/', $alt)) {
            return true;
        }

        // File extension still in alt text
        if (preg_match('/\.(jpe?g|png|gif|webp|svg|bmp|tiff?)$/i', $alt)) {
            return true;
        }

        return false;
    }
}
