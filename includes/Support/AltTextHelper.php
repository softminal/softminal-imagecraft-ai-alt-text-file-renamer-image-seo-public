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
     * Get total image count and missing-alt count in a single helper.
     *
     * @return array{total: int, missing: int}
     */
    public static function getImageCounts(): array
    {
        $totalQuery = new \WP_Query([
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'post_status' => 'inherit',
            'posts_per_page' => 1,
            'fields' => 'ids',
        ]);

        $missingQuery = new \WP_Query([
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'post_status' => 'inherit',
            'posts_per_page' => 1,
            'fields' => 'ids',
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Count-only query for images missing alt text
            'meta_query' => [
                'relation' => 'OR',
                [
                    'key' => '_wp_attachment_image_alt',
                    'compare' => 'NOT EXISTS',
                ],
                [
                    'key' => '_wp_attachment_image_alt',
                    'value' => '',
                    'compare' => '=',
                ],
            ],
        ]);

        return [
            'total' => (int) $totalQuery->found_posts,
            'missing' => (int) $missingQuery->found_posts,
        ];
    }
}
