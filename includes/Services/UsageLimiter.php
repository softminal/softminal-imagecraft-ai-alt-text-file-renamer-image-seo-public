<?php

namespace Softminal\ImageCraft\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Enforces the daily image generation limit.
 *
 * Tracks distinct attachment IDs processed in the last 24 hours per user.
 * Alt text + title + rename on the same image counts as 1 image.
 * Resets 24 hours after the first generation in the current window.
 */
class UsageLimiter
{
    /** @var int Free tier daily image limit */
    private const DAILY_LIMIT = 50;

    /** @var int Free tier daily image-compression limit (tracked separately) */
    private const COMPRESS_DAILY_LIMIT = 20;

    /** @var int Window duration in seconds (24 hours) */
    private const WINDOW_SECONDS = 86400;

    /**
     * Check if Pro add-on is active (unlimited generations).
     */
    public static function isPro(): bool
    {
        return defined('ICAIS_PRO') && \ICAIS_PRO;
    }

    /**
     * Check if the user can generate for this attachment.
     *
     * Returns true if Pro is active, within the daily limit,
     * or if the attachment was already counted in the current window.
     */
    public static function canGenerate(int $userId, int $attachmentId): bool
    {
        if (self::isPro()) {
            return true;
        }

        $data = self::getWindowData($userId);

        // Already processed this image in this window — allow without counting again
        if (in_array($attachmentId, $data['ids'], true)) {
            return true;
        }

        return count($data['ids']) < self::getLimit();
    }

    /**
     * Record that an attachment was processed.
     * Call this after a successful generation (any type).
     */
    public static function recordUsage(int $userId, int $attachmentId): void
    {
        $data = self::getWindowData($userId);

        if (in_array($attachmentId, $data['ids'], true)) {
            return; // Already counted
        }

        // Start the window on first usage
        if (empty($data['ids'])) {
            $data['started_at'] = time();
        }

        $data['ids'][] = $attachmentId;
        self::saveWindowData($userId, $data);
    }

    /**
     * Get the number of distinct images processed in the current window.
     */
    public static function getDailyUsage(int $userId): int
    {
        $data = self::getWindowData($userId);
        return count($data['ids']);
    }

    /**
     * Get the daily limit.
     * Returns -1 when Pro is active (unlimited).
     */
    public static function getLimit(): int
    {
        if (self::isPro()) {
            return -1;
        }

        return self::DAILY_LIMIT;
    }

    /**
     * Get remaining generations in the current window.
     * Returns -1 when Pro is active (unlimited).
     */
    public static function getRemaining(int $userId): int
    {
        if (self::isPro()) {
            return -1;
        }

        return max(0, self::getLimit() - self::getDailyUsage($userId));
    }

    /**
     * Get the timestamp when the current window resets.
     * Returns null if no generations have been made yet.
     */
    public static function getResetTime(int $userId): ?int
    {
        $data = self::getWindowData($userId);

        if (empty($data['ids']) || empty($data['started_at'])) {
            return null;
        }

        return (int) $data['started_at'] + self::WINDOW_SECONDS;
    }

    /**
     * Get human-readable time until reset.
     * Returns null if no active window.
     */
    public static function getResetIn(int $userId): ?string
    {
        $resetTime = self::getResetTime($userId);
        if ($resetTime === null) {
            return null;
        }

        $diff = $resetTime - time();
        if ($diff <= 0) {
            return null;
        }

        $hours = (int) floor($diff / 3600);
        $minutes = (int) floor(($diff % 3600) / 60);

        if ($hours > 0) {
            return sprintf('%dh %dm', $hours, $minutes);
        }

        return sprintf('%dm', $minutes);
    }

    /**
     * Build a standard limit-reached error response array.
     */
    public static function limitReachedError(): array
    {
        return [
            'success' => false,
            'limit_reached' => true,
            'message' => sprintf(
                /* translators: %d: daily image limit */
                __('Daily limit of %d images reached. Upgrade to Pro for unlimited generations.', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
                self::getLimit()
            ),
        ];
    }

    /**
     * Get the current window's usage data for a user.
     * Auto-resets if the 24-hour window has expired.
     *
     * @param string $bucket Counter bucket: 'gen' (alt/title/rename) or 'compress'.
     * @return array{started_at: int|null, ids: int[]}
     */
    private static function getWindowData(int $userId, string $bucket = 'gen'): array
    {
        $key = self::optionKey($userId, $bucket);
        $data = get_option($key, null);

        // No data yet
        if (!is_array($data) || !isset($data['ids'])) {
            return ['started_at' => null, 'ids' => []];
        }

        // Check if 24-hour window has expired
        $startedAt = $data['started_at'] ?? 0;
        if ($startedAt > 0 && (time() - $startedAt) >= self::WINDOW_SECONDS) {
            // Window expired — reset
            return ['started_at' => null, 'ids' => []];
        }

        return $data;
    }

    /**
     * Save the window data for a user.
     */
    private static function saveWindowData(int $userId, array $data, string $bucket = 'gen'): void
    {
        update_option(self::optionKey($userId, $bucket), $data, false);
    }

    /**
     * wp_options key for a user's daily usage.
     *
     * The 'gen' bucket keeps its legacy key for backwards compatibility; the
     * 'compress' bucket uses a distinct key so the two limits never interfere.
     */
    private static function optionKey(int $userId, string $bucket = 'gen'): string
    {
        if ($bucket === 'compress') {
            return 'icais_daily_compress_' . $userId;
        }

        return 'icais_daily_usage_' . $userId;
    }

    // ---------------------------------------------------------------------
    // Image compression daily limit (separate counter from generation)
    // ---------------------------------------------------------------------

    /**
     * Check if the user can compress this attachment.
     *
     * Returns true if Pro is active, within the daily compression limit, or if
     * the attachment was already counted in the current window.
     */
    public static function canCompress(int $userId, int $attachmentId): bool
    {
        if (self::isPro()) {
            return true;
        }

        $data = self::getWindowData($userId, 'compress');

        if (in_array($attachmentId, $data['ids'], true)) {
            return true;
        }

        return count($data['ids']) < self::getCompressLimit();
    }

    /**
     * Record that an attachment was compressed.
     * Call this after a successful compression.
     */
    public static function recordCompressUsage(int $userId, int $attachmentId): void
    {
        $data = self::getWindowData($userId, 'compress');

        if (in_array($attachmentId, $data['ids'], true)) {
            return; // Already counted
        }

        if (empty($data['ids'])) {
            $data['started_at'] = time();
        }

        $data['ids'][] = $attachmentId;
        self::saveWindowData($userId, $data, 'compress');
    }

    /**
     * Number of distinct images compressed in the current window.
     */
    public static function getCompressDailyUsage(int $userId): int
    {
        return count(self::getWindowData($userId, 'compress')['ids']);
    }

    /**
     * Daily compression limit. Returns -1 when Pro is active (unlimited).
     */
    public static function getCompressLimit(): int
    {
        if (self::isPro()) {
            return -1;
        }

        return self::COMPRESS_DAILY_LIMIT;
    }

    /**
     * Remaining compressions in the current window. -1 when Pro (unlimited).
     */
    public static function getCompressRemaining(int $userId): int
    {
        if (self::isPro()) {
            return -1;
        }

        return max(0, self::getCompressLimit() - self::getCompressDailyUsage($userId));
    }

    /**
     * Timestamp when the compression window resets, or null if none active.
     */
    public static function getCompressResetTime(int $userId): ?int
    {
        $data = self::getWindowData($userId, 'compress');

        if (empty($data['ids']) || empty($data['started_at'])) {
            return null;
        }

        return (int) $data['started_at'] + self::WINDOW_SECONDS;
    }

    /**
     * Build a standard compression-limit-reached error response array.
     */
    public static function compressLimitReachedError(): array
    {
        return [
            'success' => false,
            'limit_reached' => true,
            'message' => sprintf(
                /* translators: %d: daily image-compression limit */
                __('Daily limit of %d image compressions reached. Upgrade to Pro for unlimited compression.', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
                self::getCompressLimit()
            ),
        ];
    }
}
