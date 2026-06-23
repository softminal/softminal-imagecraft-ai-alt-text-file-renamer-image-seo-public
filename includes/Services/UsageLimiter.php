<?php

namespace Softminal\ImageCraft\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Enforces a single daily free-tier quota shared across all actions.
 *
 * One per-user daily pool (default 50). AI generation counts once per image
 * (alt text / title / rename are bundled, and re-generating the same image is
 * free). Compression counts every time it runs — re-compressing an image you
 * already compressed today consumes another slot. So compressing + generating
 * one image uses 2; compressing it twice uses 2. Window resets 24 hours after
 * the first action. Pro (ICAIS_PRO) is unlimited.
 *
 * Storage (wp_option `icais_daily_usage_<userId>`):
 *   ['started_at' => int, 'ids' => int[], 'compress_count' => int]
 * 'ids' is the legacy generation set (deduped); 'compress_count' is a running
 * tally of compressions. Both may be absent on older data.
 */
class UsageLimiter
{
    /** @var int Free tier daily limit, shared by generation + compression */
    private const DAILY_LIMIT = 50;

    /** @var int Window duration in seconds (24 hours) */
    private const WINDOW_SECONDS = 86400;

    /**
     * Check if Pro add-on is active (unlimited usage).
     */
    public static function isPro(): bool
    {
        return defined('ICAIS_PRO') && \ICAIS_PRO;
    }

    // ---------------------------------------------------------------------
    // Generation (alt text / title / rename) — one count per image
    // ---------------------------------------------------------------------

    /**
     * Check if the user can generate for this attachment.
     * Re-generating an already-counted image is free.
     */
    public static function canGenerate(int $userId, int $attachmentId): bool
    {
        if (self::isPro()) {
            return true;
        }

        $data = self::getWindowData($userId);

        if (in_array($attachmentId, $data['ids'], true)) {
            return true;
        }

        return self::totalCount($data) < self::DAILY_LIMIT;
    }

    /**
     * Record a generation against this attachment. Call after success.
     */
    public static function recordUsage(int $userId, int $attachmentId): void
    {
        $data = self::getWindowData($userId);

        if (in_array($attachmentId, $data['ids'], true)) {
            return; // Already counted for generation
        }

        if (self::totalCount($data) === 0) {
            $data['started_at'] = time();
        }

        $data['ids'][] = $attachmentId;
        self::saveWindowData($userId, $data);
    }

    // ---------------------------------------------------------------------
    // Compression — a separate count per image, sharing the same daily pool
    // ---------------------------------------------------------------------

    /**
     * Check if the user can compress this attachment.
     *
     * Unlike generation, every compression counts — including re-compressing an
     * image already compressed today — so this only checks the shared pool.
     * $attachmentId is accepted for signature parity with canGenerate().
     */
    public static function canCompress(int $userId, int $attachmentId): bool
    {
        if (self::isPro()) {
            return true;
        }

        return self::totalCount(self::getWindowData($userId)) < self::DAILY_LIMIT;
    }

    /**
     * Record a compression. Call after a successful (non-skipped) compression.
     * Always increments — re-compressing the same image counts again.
     * $attachmentId is accepted for signature parity with recordUsage().
     */
    public static function recordCompressUsage(int $userId, int $attachmentId): void
    {
        $data = self::getWindowData($userId);

        if (self::totalCount($data) === 0) {
            $data['started_at'] = time();
        }

        $data['compress_count']++;
        self::saveWindowData($userId, $data);
    }

    // ---------------------------------------------------------------------
    // Shared usage / limit accessors (combined generation + compression)
    // ---------------------------------------------------------------------

    /**
     * Total actions used in the current window (generation + compression).
     */
    public static function getDailyUsage(int $userId): int
    {
        return self::totalCount(self::getWindowData($userId));
    }

    /**
     * Daily limit. Returns -1 when Pro is active (unlimited).
     */
    public static function getLimit(): int
    {
        return self::isPro() ? -1 : self::DAILY_LIMIT;
    }

    /**
     * Remaining actions in the current window. -1 when Pro (unlimited).
     */
    public static function getRemaining(int $userId): int
    {
        if (self::isPro()) {
            return -1;
        }

        return max(0, self::DAILY_LIMIT - self::getDailyUsage($userId));
    }

    /**
     * Timestamp when the current window resets, or null if none active.
     */
    public static function getResetTime(int $userId): ?int
    {
        $data = self::getWindowData($userId);

        if (self::totalCount($data) === 0 || empty($data['started_at'])) {
            return null;
        }

        return (int) $data['started_at'] + self::WINDOW_SECONDS;
    }

    /**
     * Human-readable time until reset, or null if no active window.
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
                /* translators: %d: daily free-tier limit */
                __('Daily free limit of %d reached. Upgrade to Pro for unlimited usage.', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
                self::DAILY_LIMIT
            ),
        ];
    }

    // ---------------------------------------------------------------------
    // Compression aliases — same combined pool, kept so the compression call
    // sites and the compression stats endpoint need no change.
    // ---------------------------------------------------------------------

    public static function getCompressDailyUsage(int $userId): int
    {
        return self::getDailyUsage($userId);
    }

    public static function getCompressLimit(): int
    {
        return self::getLimit();
    }

    public static function getCompressRemaining(int $userId): int
    {
        return self::getRemaining($userId);
    }

    public static function getCompressResetTime(int $userId): ?int
    {
        return self::getResetTime($userId);
    }

    public static function compressLimitReachedError(): array
    {
        return self::limitReachedError();
    }

    // ---------------------------------------------------------------------
    // Internals
    // ---------------------------------------------------------------------

    /**
     * Combined daily usage: distinct generated images + total compressions.
     */
    private static function totalCount(array $data): int
    {
        return count($data['ids']) + (int) $data['compress_count'];
    }

    /**
     * Current window data, auto-reset when the 24h window expires.
     *
     * @return array{started_at: int|null, ids: int[], compress_count: int}
     */
    private static function getWindowData(int $userId): array
    {
        $data = get_option(self::optionKey($userId), null);

        if (!is_array($data)) {
            return ['started_at' => null, 'ids' => [], 'compress_count' => 0];
        }

        $startedAt = $data['started_at'] ?? 0;
        if ($startedAt > 0 && (time() - $startedAt) >= self::WINDOW_SECONDS) {
            return ['started_at' => null, 'ids' => [], 'compress_count' => 0];
        }

        // Normalize legacy/partial shapes: 'ids' is the generation set.
        // 'compress_count' is the running compression tally; fall back to the
        // count of an older 'compress' ID array if present.
        $compressCount = isset($data['compress_count'])
            ? (int) $data['compress_count']
            : (isset($data['compress']) && is_array($data['compress']) ? count($data['compress']) : 0);

        return [
            'started_at'     => $data['started_at'] ?? null,
            'ids'            => isset($data['ids']) && is_array($data['ids']) ? $data['ids'] : [],
            'compress_count' => $compressCount,
        ];
    }

    /**
     * Save the window data for a user.
     */
    private static function saveWindowData(int $userId, array $data): void
    {
        update_option(self::optionKey($userId), $data, false);
    }

    /**
     * wp_options key for a user's shared daily usage (legacy key retained).
     */
    private static function optionKey(int $userId): string
    {
        return 'icais_daily_usage_' . $userId;
    }
}
