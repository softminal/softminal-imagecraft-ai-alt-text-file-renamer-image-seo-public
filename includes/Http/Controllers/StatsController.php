<?php

namespace Softminal\ImageCraft\Http\Controllers;

use Softminal\ImageCraft\Models\ApiSetting;
use Softminal\ImageCraft\Models\History;
use Softminal\ImageCraft\Models\Stats;
use Softminal\ImageCraft\Services\UsageLimiter;
use Softminal\ImageCraft\Support\AltTextHelper;

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

        $imageCounts = AltTextHelper::getImageCounts();
        $totalImages = $imageCounts['total'];
        $missingAlt = $imageCounts['missing'];
        
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

        // Count history statuses with a single grouped query instead of loading all rows
        global $wpdb;
        $historyTable = esc_sql(History::table());
        $sql = sprintf('SELECT status, COUNT(*) AS cnt FROM `%s` WHERE user_id = %%d GROUP BY status', $historyTable);
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Aggregation query; table escaped via esc_sql()
        $statusCounts = $wpdb->get_results(
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Identifier escaped via esc_sql(); values via $wpdb->prepare()
            $wpdb->prepare($sql, $userId),
            ARRAY_A
        );

        $acceptedCount = 0;
        $rejectedCount = 0;
        $pendingCount = 0;
        foreach ($statusCounts ?: [] as $row) {
            $cnt = (int) $row['cnt'];
            switch ($row['status']) {
                case 'accepted':
                    $acceptedCount = $cnt;
                    break;
                case 'rejected':
                    $rejectedCount = $cnt;
                    break;
                default:
                    $pendingCount += $cnt;
                    break;
            }
        }

        $historyTotal = $acceptedCount + $rejectedCount + $pendingCount;
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
            // Prime post + meta caches for attachment IDs to avoid N+1 queries
            $attachmentIds = [];
            foreach ($recentData['data'] as $history) {
                $attachmentIds[] = (int) $history->attachment_id;
            }
            if (!empty($attachmentIds)) {
                _prime_post_caches($attachmentIds, true, true);
            }

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
            'chart_results' => $chartResults,
            'usage_limit' => [
                'daily_limit' => UsageLimiter::getLimit(),
                'daily_used' => UsageLimiter::getDailyUsage($userId),
                'daily_remaining' => UsageLimiter::getRemaining($userId),
                'reset_at' => UsageLimiter::getResetTime($userId),
                'reset_in' => UsageLimiter::getResetIn($userId),
            ],
        ]);
    }

    /**
     * SEO audit report for all media library images
     */
    public function audit($request)
    {
        $maxLength = (int) \Softminal\ImageCraft\Services\Settings::get('icais_max_length');

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

        // Batch-prime postmeta to avoid N+1 queries in classification loop
        if (!empty($imageIds)) {
            update_meta_cache('post', $imageIds);
        }

        $missing = 0;
        $generic = 0;
        $tooLong = 0;
        $good = 0;

        foreach ($imageIds as $id) {
            $alt = get_post_meta($id, '_wp_attachment_image_alt', true);

            if (empty($alt)) {
                $missing++;
                continue;
            }

            $hasIssue = false;

            if (AltTextHelper::isGeneric($alt)) {
                $generic++;
                $hasIssue = true;
            }

            if (mb_strlen($alt) > $maxLength) {
                $tooLong++;
                $hasIssue = true;
            }

            if (!$hasIssue) {
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
            ],
        ]);
    }

}
