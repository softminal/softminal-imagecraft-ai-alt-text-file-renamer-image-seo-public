<?php

namespace ImageCraft\Includes\Http\Controllers;

use ImageCraft\Includes\Models\Stats;
use ImageCraft\Includes\Models\History;
use ImageCraft\Includes\Models\ApiSetting;
use ImageCraft\Includes\Support\AltTextHelper;

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
        
        // Count total images (posts_per_page=1 avoids loading all IDs; found_posts gives the total)
        $totalQuery = new \WP_Query([
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'post_status' => 'inherit',
            'posts_per_page' => 1,
            'fields' => 'ids',
        ]);

        // Count images missing alt text
        $missingQuery = new \WP_Query([
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'post_status' => 'inherit',
            'posts_per_page' => 1,
            'fields' => 'ids',
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Required to find images missing alt text; count-only query
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
        
        // Get all stats for user in a single query, ordered by date
        $allStats = Stats::where('user_id', $userId)
            ->orderBy('date', 'ASC')
            ->get();

        $thisMonth = gmdate('Y-m');
        $totalProcessed = 0;
        $totalCost = 0;
        $thisMonthProcessed = 0;
        $thisMonthCost = 0;
        $statsByDate = [];

        foreach ($allStats as $stat) {
            $processed = (int) $stat->images_processed;
            $cost = (float) $stat->total_cost_estimate;

            $totalProcessed += $processed;
            $totalCost += $cost;

            if (strpos($stat->date, $thisMonth) === 0) {
                $thisMonthProcessed += $processed;
                $thisMonthCost += $cost;
            }

            $statsByDate[$stat->date] = [
                'processed' => $processed,
                'cost' => $cost,
            ];
        }

        // Build chart_usage: last 30 days with zero-fill for missing dates
        $chartUsage = [];
        $today = gmdate('Y-m-d');
        for ($i = 29; $i >= 0; $i--) {
            $date = gmdate('Y-m-d', strtotime("-{$i} days", strtotime($today)));
            $entry = $statsByDate[$date] ?? null;
            $chartUsage[] = [
                'date' => $date,
                'processed' => $entry ? $entry['processed'] : 0,
                'cost' => $entry ? $entry['cost'] : 0,
            ];
        }

        // Get history and compute chart_results in a single loop
        $totalHistory = History::where('user_id', $userId)->get();
        $acceptedCount = 0;
        $rejectedCount = 0;
        $pendingCount = 0;

        foreach ($totalHistory as $history) {
            switch ($history->status) {
                case 'accepted':
                    $acceptedCount++;
                    break;
                case 'rejected':
                    $rejectedCount++;
                    break;
                default:
                    $pendingCount++;
                    break;
            }
        }

        $historyTotal = count($totalHistory);
        $successRate = $historyTotal > 0 ? round(($acceptedCount / $historyTotal) * 100, 2) : 0;

        $chartResults = [
            ['name' => 'Accepted', 'value' => $acceptedCount],
            ['name' => 'Rejected', 'value' => $rejectedCount],
            ['name' => 'Pending', 'value' => $pendingCount],
        ];
        
        // Get API status
        $apiSetting = ApiSetting::getActive($userId);
        $apiStatus = $apiSetting ? [
            'connected' => true,
            'provider' => $apiSetting->api_provider
        ] : [
            'connected' => false,
            'provider' => null
        ];
        
        // Get recent activity
        $recentData = History::getRecent($userId, 10);
        $recentActivity = [];
        if (!empty($recentData['data'])) {
            foreach ($recentData['data'] as $history) {
                $attachmentId = $history->attachment_id;
                $thumbnail = wp_get_attachment_image_src($attachmentId, 'thumbnail');
                $recentActivity[] = [
                    'id' => $history->id,
                    'attachment_id' => $attachmentId,
                    'filename' => basename(get_attached_file($attachmentId) ?: ''),
                    'thumbnail' => $thumbnail ? $thumbnail[0] : null,
                    'generated_alt' => $history->generated_alt,
                    'status' => $history->status,
                ];
            }
        }

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
            ],
            'recent_activity' => $recentActivity,
            'chart_usage' => $chartUsage,
            'chart_results' => $chartResults
        ]);
    }

    /**
     * SEO audit report for all media library images
     */
    public function audit($request)
    {
        $maxLength = (int) \ImageCraft\Includes\Services\Settings::get('imgcraft_max_length', 125);

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

            if (AltTextHelper::isGeneric($alt)) {
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

}
