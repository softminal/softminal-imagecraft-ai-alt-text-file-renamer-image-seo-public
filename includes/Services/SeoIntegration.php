<?php

namespace Softminal\ImageCraft\Services;

if (!defined('ABSPATH')) {
    exit;
}

class SeoIntegration
{
    /**
     * Check if any supported SEO plugin is active
     */
    public static function isActive(): bool
    {
        return self::getActivePlugin() !== null;
    }

    /**
     * Get the slug of the first detected SEO plugin
     *
     * @return string|null 'yoast', 'rank_math', 'aioseo', 'seopress', or null
     */
    public static function getActivePlugin(): ?string
    {
        if (defined('WPSEO_VERSION')) {
            return 'yoast';
        }

        if (class_exists('RankMath')) {
            return 'rank_math';
        }

        if (defined('AIOSEO_VERSION')) {
            return 'aioseo';
        }

        if (defined('SEOPRESS_VERSION')) {
            return 'seopress';
        }

        return null;
    }

    /**
     * Get human-readable name of the active SEO plugin
     */
    public static function getActivePluginLabel(): string
    {
        $labels = [
            'yoast'     => 'Yoast SEO',
            'rank_math' => 'Rank Math',
            'aioseo'    => 'All in One SEO',
            'seopress'  => 'SEOPress',
        ];

        $plugin = self::getActivePlugin();

        return $plugin ? ($labels[$plugin] ?? '') : '';
    }

    /**
     * Get the focus keyphrase from the detected SEO plugin's post meta
     *
     * @param int $postId The post/page ID
     * @return string The focus keyphrase or empty string
     */
    public static function getFocusKeyphrase(int $postId): string
    {
        if ($postId <= 0) {
            return '';
        }

        $plugin = self::getActivePlugin();
        if (!$plugin) {
            return '';
        }

        $keyphrase = '';

        switch ($plugin) {
            case 'yoast':
                $keyphrase = get_post_meta($postId, '_yoast_wpseo_focuskw', true);
                break;

            case 'rank_math':
                $raw = get_post_meta($postId, 'rank_math_focus_keyword', true);
                if (!empty($raw)) {
                    // Rank Math stores comma-separated keywords; take the first
                    $parts = explode(',', $raw);
                    $keyphrase = trim($parts[0]);
                }
                break;

            case 'aioseo':
                $keyphrase = self::getAioseoKeyphrase($postId);
                break;

            case 'seopress':
                $raw = get_post_meta($postId, '_seopress_analysis_target_kw', true);
                if (!empty($raw)) {
                    // SEOPress may store comma-separated keywords; take the first
                    $parts = explode(',', $raw);
                    $keyphrase = trim($parts[0]);
                }
                break;
        }

        return is_string($keyphrase) ? trim($keyphrase) : '';
    }

    /**
     * Get AIOSEO focus keyphrase (handles JSON format and legacy meta)
     */
    private static function getAioseoKeyphrase(int $postId): string
    {
        // AIOSEO v4+ stores keywords as JSON in _aioseo_keywords
        $raw = get_post_meta($postId, '_aioseo_keywords', true);

        if (!empty($raw)) {
            // Try JSON decode (AIOSEO v4 format)
            if (is_string($raw) && $raw[0] === '[') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded) && !empty($decoded)) {
                    // Each entry may be a string or an object with 'keyphrase' key
                    $first = $decoded[0];
                    if (is_string($first)) {
                        return trim($first);
                    }
                    if (is_array($first) && isset($first['keyphrase'])) {
                        return trim($first['keyphrase']);
                    }
                }
            }

            // Plain string fallback
            if (is_string($raw)) {
                return trim($raw);
            }
        }

        // Fallback: try AIOSEO v4 model table via its own API if available
        if (function_exists('aioseo') && method_exists(aioseo()->models, 'Post')) {
            try {
                $aioseoPost = aioseo()->models->Post->getPost($postId);
                if ($aioseoPost && !empty($aioseoPost->keyphrases)) {
                    $keyphrases = json_decode($aioseoPost->keyphrases, true);
                    if (is_array($keyphrases) && isset($keyphrases['focus']) && isset($keyphrases['focus']['keyphrase'])) {
                        return trim($keyphrases['focus']['keyphrase']);
                    }
                }
            } catch (\Exception $e) {
                // Silently fail — fall through to empty
            }
        }

        return '';
    }

    /**
     * Get the parent post ID for an attachment
     *
     * @param int $attachmentId
     * @return int Parent post ID or 0
     */
    public static function getAttachmentParentPostId(int $attachmentId): int
    {
        $attachment = get_post($attachmentId);
        if (!$attachment || empty($attachment->post_parent)) {
            return 0;
        }

        return (int) $attachment->post_parent;
    }

    /**
     * Build SEO context string for an attachment
     *
     * Main entry point: checks settings, finds parent post, reads keyphrase,
     * and returns a context string or null.
     *
     * @param int $attachmentId
     * @param int $postId Optional explicit post ID (e.g. from block editor) to use instead of post_parent
     * @return string|null Context string like "SEO Focus Keyphrase: organic coffee | Page: About Us" or null
     */
    public static function buildSeoContext(int $attachmentId, int $postId = 0): ?string
    {
        // Check if SEO integration is enabled
        if (Settings::get('icais_seo_integration', '0') !== '1') {
            return null;
        }

        // Check if focus keyphrase usage is enabled
        if (Settings::get('icais_seo_use_keyphrase', '1') !== '1') {
            return null;
        }

        // Check if any SEO plugin is active
        if (!self::isActive()) {
            return null;
        }

        // Use explicit post ID if provided, otherwise fall back to post_parent
        $targetPostId = $postId > 0 ? $postId : self::getAttachmentParentPostId($attachmentId);
        if ($targetPostId <= 0) {
            return null;
        }

        // Get focus keyphrase
        $keyphrase = self::getFocusKeyphrase($targetPostId);
        if (empty($keyphrase)) {
            return null;
        }

        // Build context string
        $targetPost = get_post($targetPostId);
        $pageTitle = $targetPost ? $targetPost->post_title : '';

        $context = 'SEO Focus Keyphrase: ' . $keyphrase;
        if (!empty($pageTitle)) {
            $context .= ' | Page: ' . $pageTitle;
        }

        return $context;
    }
}
