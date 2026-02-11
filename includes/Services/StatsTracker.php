<?php

namespace SmartAltTextAi\Includes\Services;

use SmartAltTextAi\Includes\Models\History;
use SmartAltTextAi\Includes\Models\Stats;
use SmartAltTextAi\Includes\Models\ApiSetting;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Centralised helper for recording generation history and daily stats.
 *
 * Every place that generates alt text (GeneratorController, WooCommerce
 * service, WooCommerceController gallery) was duplicating the same
 * history-create + stats-increment pattern. This class eliminates that.
 */
class StatsTracker
{
    /**
     * Record a successful alt text generation.
     *
     * Creates a History row and increments the daily Stats counters.
     *
     * @param array $params {
     *   @type int    $attachment_id  Required.
     *   @type string $alt_text       The generated alt text.
     *   @type int    $user_id        WordPress user ID.
     *   @type string $tone           Tone used for generation.
     *   @type string $status         'accepted' or 'pending'.
     *   @type int    $tokens         Token count from API response.
     *   @type float  $cost           Estimated cost from API response.
     *   @type string $original_alt   Original alt text before generation.
     * }
     * @return History The created History model instance.
     */
    public static function recordGeneration(array $params): History
    {
        $userId       = (int) ($params['user_id'] ?? get_current_user_id());
        $status       = $params['status'] ?? 'pending';
        $tokens       = (int) ($params['tokens'] ?? 0);
        $cost         = (float) ($params['cost'] ?? 0);
        $apiSetting   = ApiSetting::getActive($userId);

        $history = History::create([
            'attachment_id' => (int) $params['attachment_id'],
            'original_alt'  => $params['original_alt'] ?? '',
            'generated_alt' => $params['alt_text'] ?? '',
            'user_id'       => $userId,
            'api_provider'  => $apiSetting ? $apiSetting->api_provider : 'unknown',
            'tone'          => $params['tone'] ?? 'professional',
            'status'        => $status,
            'tokens_used'   => $tokens,
            'cost_estimate' => $cost,
        ]);

        $stats = Stats::getOrCreate($userId, gmdate('Y-m-d'));
        $stats->increment('images_processed');

        if ($status === 'accepted') {
            $stats->increment('images_accepted');
        }

        $stats->total_cost_estimate = (float) $stats->total_cost_estimate + $cost;
        if ($apiSetting) {
            $stats->api_provider = $apiSetting->api_provider;
        }
        $stats->save();

        return $history;
    }

    /**
     * Record an accept action on an existing pending history entry.
     */
    public static function recordAccept(int $userId): void
    {
        $stats = Stats::getOrCreate($userId, gmdate('Y-m-d'));
        $stats->increment('images_accepted');
        $stats->save();
    }

    /**
     * Record a reject action on an existing pending history entry.
     */
    public static function recordReject(int $userId): void
    {
        $stats = Stats::getOrCreate($userId, gmdate('Y-m-d'));
        $stats->increment('images_rejected');
        $stats->save();
    }
}
