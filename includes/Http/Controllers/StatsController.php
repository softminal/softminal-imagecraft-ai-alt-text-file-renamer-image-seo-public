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
}
