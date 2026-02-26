<?php

namespace Softminal\ImageCraft\Support;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shared helpers for evaluating alt text quality.
 *
 * Used by ScannerController and StatsController to avoid duplication.
 */
class AltTextHelper
{
    /**
     * Placeholder words that indicate low-quality alt text
     */
    private static $placeholders = [
        'image', 'photo', 'picture', 'img', 'untitled',
        'default', 'placeholder', 'no alt', 'alt text',
        'thumbnail', 'banner', 'hero', 'logo',
    ];

    /**
     * Check if alt text looks like a generic/placeholder value
     */
    public static function isGeneric(string $alt): bool
    {
        $alt = trim($alt);

        if (mb_strlen($alt) < 4) {
            return true;
        }

        $lower = strtolower($alt);

        if (in_array($lower, self::$placeholders, true)) {
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

    /**
     * Get total image count and missing-alt count in a single query.
     *
     * Uses a single SQL round-trip with LEFT JOIN + SUM(CASE) instead of
     * two separate WP_Query calls.
     *
     * @return array{total: int, missing: int}
     */
    public static function getImageCounts(): array
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Lightweight aggregation; not worth caching for a single page load
        $row = $wpdb->get_row(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN pm.meta_value IS NULL OR pm.meta_value = '' THEN 1 ELSE 0 END) AS missing
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} pm
                    ON pm.post_id = p.ID AND pm.meta_key = '_wp_attachment_image_alt'
             WHERE p.post_type = 'attachment'
               AND p.post_status = 'inherit'
               AND p.post_mime_type LIKE 'image/%'"
        );

        return [
            'total' => (int) ($row->total ?? 0),
            'missing' => (int) ($row->missing ?? 0),
        ];
    }
}
