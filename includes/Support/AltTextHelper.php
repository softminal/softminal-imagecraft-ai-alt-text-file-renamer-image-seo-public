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
     * Normalize a filename or title for comparison: strip extension, replace
     * separators with spaces, lowercase, collapse whitespace.
     */
    private static function normalizeForCompare(string $value): string
    {
        $value = (string) preg_replace('/\.[a-z0-9]+$/i', '', $value);
        $value = str_replace(['-', '_'], ' ', $value);
        $value = trim((string) preg_replace('/\s+/', ' ', $value));
        return strtolower($value);
    }

    /**
     * A post_title only counts as a "real" title when ImageCraft generated
     * one, or when it clearly differs from the upload filename (a human
     * customized it). WordPress auto-fills post_title from the filename on
     * upload, so a raw, untouched post_title is not a meaningful title.
     */
    public static function hasRealTitle(string $postTitle, string $generatedTitle, string $attachedFile): bool
    {
        if ($generatedTitle !== '') {
            return true;
        }
        if ($postTitle === '') {
            return false;
        }
        $filename = basename($attachedFile);
        if ($filename === '') {
            return true; // No filename to compare against — assume it's real.
        }
        return self::normalizeForCompare($postTitle) !== self::normalizeForCompare($filename);
    }

    /**
     * Truncate text to a max length, trimming to the last complete word.
     *
     * @param string $text      The text to truncate.
     * @param int    $maxLength Maximum character length.
     * @return string
     */
    public static function truncateTitle(string $text, int $maxLength): string
    {
        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }
        $truncated = mb_substr($text, 0, $maxLength);
        return (string) preg_replace('/\s+\S*$/', '', $truncated) ?: $truncated;
    }

    /**
     * Get total image count, missing-alt count, and "real title"/caption counts.
     *
     * Row-level fetch (not a single aggregate query) because "has a real
     * title" requires comparing post_title against the upload filename in
     * PHP — a comparison that isn't reliably portable in SQL on the MySQL 5.7
     * floor. Still a single round-trip; the per-row work is cheap string
     * comparison, not additional queries.
     *
     * @return array{total: int, missing: int}
     */
    public static function getImageCounts(): array
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Lightweight; not worth caching for a single page load
        $rows = $wpdb->get_results(
            "SELECT p.ID, p.post_title,
                    pm.meta_value AS alt_text,
                    tm.meta_value AS generated_title,
                    cm.meta_value AS generated_caption,
                    af.meta_value AS attached_file
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} pm
                    ON pm.post_id = p.ID AND pm.meta_key = '_wp_attachment_image_alt'
             LEFT JOIN {$wpdb->postmeta} tm
                    ON tm.post_id = p.ID AND tm.meta_key = '_icais_generated_title'
             LEFT JOIN {$wpdb->postmeta} cm
                    ON cm.post_id = p.ID AND cm.meta_key = '_icais_generated_caption'
             LEFT JOIN {$wpdb->postmeta} af
                    ON af.post_id = p.ID AND af.meta_key = '_wp_attached_file'
             WHERE p.post_type = 'attachment'
               AND p.post_status = 'inherit'
               AND p.post_mime_type LIKE 'image/%'"
        );

        $total = count($rows);
        $missing = 0;
        $hasTitle = 0;
        $hasCaption = 0;

        foreach ($rows as $row) {
            if ((string) $row->alt_text === '') {
                $missing++;
            }
            if (self::hasRealTitle((string) $row->post_title, (string) $row->generated_title, (string) $row->attached_file)) {
                $hasTitle++;
            }
            if ((string) $row->generated_caption !== '') {
                $hasCaption++;
            }
        }

        return [
            'total' => $total,
            'missing' => $missing,
            'has_title' => $hasTitle,
            'missing_title' => $total - $hasTitle,
            'has_caption' => $hasCaption,
            'missing_caption' => $total - $hasCaption,
        ];
    }
}
