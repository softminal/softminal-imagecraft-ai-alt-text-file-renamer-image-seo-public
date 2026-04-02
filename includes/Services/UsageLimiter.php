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

    /** @var int Window duration in seconds (24 hours) */
    private const WINDOW_SECONDS = 86400;

    /**
     * Check if the user can generate for this attachment.
     *
     * Returns true if within the daily limit OR if the attachment
     * was already counted in the current window (no double-count).
     */
    public static function canGenerate(int $userId, int $attachmentId): bool
    {
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
     */
    public static function getLimit(): int
    {
        return self::DAILY_LIMIT;
    }

    /**
     * Get remaining generations in the current window.
     */
    public static function getRemaining(int $userId): int
    {
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
     * @return array{started_at: int|null, ids: int[]}
     */
    private static function getWindowData(int $userId): array
    {
        $key = self::optionKey($userId);
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
    private static function saveWindowData(int $userId, array $data): void
    {
        update_option(self::optionKey($userId), $data, false);
    }

    /**
     * wp_options key for a user's daily usage.
     */
    private static function optionKey(int $userId): string
    {
        return 'icais_daily_usage_' . $userId;
    }
}
