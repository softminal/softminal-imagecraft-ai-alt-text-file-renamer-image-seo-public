<?php

namespace SmartAltTextAi\Includes\Support;

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
}
