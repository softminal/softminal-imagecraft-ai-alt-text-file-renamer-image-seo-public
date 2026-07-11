<?php

namespace Softminal\ImageCraft;

use Softminal\ImageCraft\Services\Settings;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Image Fallbacks — the plugin's public-frontend surface.
 *
 * Two non-destructive, opt-in (default off) sub-features:
 *  1. Broken-image fallback: a tiny vanilla JS swaps any <img> that fails to
 *     load to a user-chosen fallback attachment (SEO-aware: carries alt text).
 *  2. Missing-featured-image fallback: filters `_thumbnail_id` meta reads so
 *     `has_post_thumbnail()` returns true and themes render the fallback —
 *     nothing is ever written to the database.
 *
 * Pro unlock point (mirrors icais_compression_quality): the per-post-type
 * featured image is resolved through apply_filters('icais_fallback_featured_image_id')
 * which the Pro add-on overrides from the icais_fallback_featured_map setting.
 */
class Fallback
{
    /**
     * Recursion guard for the `_thumbnail_id` meta filter. Static so that ALL
     * instances of this class share it — with an instance property, a second
     * registered instance would answer the inner "re-read the real meta" call
     * with the fallback ID and make the outer instance think a real thumbnail
     * exists.
     *
     * @var bool
     */
    private static $resolving = false;

    /** @var array<int,bool> Per-request cache: attachment ID => is a real image. */
    private $validImageCache = [];

    public function init()
    {
        // Frontend-only surface. The filters bail on is_admin() themselves, but
        // skipping registration entirely in admin keeps the hot path clean.
        if (is_admin()) {
            return;
        }

        add_action('wp_enqueue_scripts', [$this, 'enqueueBrokenFallbackScript']);
        add_filter('get_post_metadata', [$this, 'filterThumbnailId'], 10, 4);
        add_filter('post_thumbnail_html', [$this, 'filterThumbnailHtml'], 10, 5);
    }

    /**
     * Enqueue the broken-image onerror swap script (raw, non-Vite asset).
     */
    public function enqueueBrokenFallbackScript()
    {
        try {
            if (!$this->moduleEnabled() || Settings::get('icais_fallback_broken_enabled', '0') !== '1') {
                return;
            }

            $imageId = (int) Settings::get('icais_fallback_broken_image_id', 0);
            if ($imageId <= 0 || !$this->isValidImage($imageId)) {
                return;
            }

            $url = wp_get_attachment_image_url($imageId, 'full');
            if (!$url) {
                return;
            }

            wp_enqueue_script(
                'icais-fallback',
                ICAIS_URL . 'resources/public/js/fallback.js',
                [],
                ICAIS_VERSION,
                true
            );
            wp_localize_script('icais-fallback', 'icaisFallback', [
                'imageUrl' => $url,
                'altText'  => (string) get_post_meta($imageId, '_wp_attachment_image_alt', true),
            ]);
        } catch (\Throwable $e) {
            // Frontend hook — must never take a site down.
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging only when WP_DEBUG is enabled
                error_log('ImageCraft fallback enqueue error: ' . $e->getMessage());
            }
        }
    }

    /**
     * PRIMARY featured fallback: make `_thumbnail_id` reads return the fallback
     * attachment when a post has no real thumbnail, so has_post_thumbnail() is
     * true and classic themes, block themes, REST and og:image all render it.
     *
     * HOT PATH — runs on every post meta read. Cheap bails come first.
     *
     * @param mixed  $value    Short-circuit value (null = proceed to DB).
     * @param int    $objectId Post ID.
     * @param string $metaKey  Meta key being read.
     * @param bool   $single   Whether a single value was requested.
     * @return mixed
     */
    public function filterThumbnailId($value, $objectId, $metaKey, $single)
    {
        // Cheapest bails first — this filter fires for EVERY post meta read.
        if ($metaKey !== '_thumbnail_id' || $value !== null || self::$resolving) {
            return $value;
        }

        // Never filter REST reads: the block editor loads posts via REST
        // (is_admin() is false there) — a filtered featured_media would show a
        // featured image the user never set AND be written back to the DB on
        // save, breaking the non-destructive guarantee.
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return $value;
        }

        try {
            if (!$this->featuredEnabled()) {
                return $value;
            }

            $post = get_post($objectId);
            if (!$post || !$this->postTypeAllowed($post->post_type)) {
                return $value;
            }

            // Re-read the real meta with the recursion guard up.
            self::$resolving = true;
            $real = get_post_meta((int) $objectId, '_thumbnail_id', true);
            self::$resolving = false;

            if (!empty($real)) {
                return $value; // Post has a real featured image — never interfere.
            }

            $fallbackId = $this->featuredFallbackId((int) $objectId, $post->post_type);
            if ($fallbackId <= 0) {
                return $value;
            }

            return $single ? $fallbackId : [$fallbackId];
        } catch (\Throwable $e) {
            self::$resolving = false;
            return $value; // Frontend hot path — fail open to default behavior.
        }
    }

    /**
     * SECONDARY featured fallback: belt-and-braces for edge cases where a theme
     * bypasses the meta path and the thumbnail HTML is still empty.
     *
     * @param string       $html        Rendered thumbnail HTML.
     * @param int          $postId      Post ID.
     * @param int          $thumbnailId Attachment ID (0 when none).
     * @param string|array $size        Requested size.
     * @param string|array $attr        Attributes.
     * @return string
     */
    public function filterThumbnailHtml($html, $postId, $thumbnailId, $size, $attr)
    {
        if ($html !== '' || !empty($thumbnailId)) {
            return $html;
        }

        try {
            if (!$this->featuredEnabled()) {
                return $html;
            }

            $post = get_post($postId);
            if (!$post || !$this->postTypeAllowed($post->post_type)) {
                return $html;
            }

            $fallbackId = $this->featuredFallbackId((int) $postId, $post->post_type);
            if ($fallbackId <= 0) {
                return $html;
            }

            $image = wp_get_attachment_image(
                $fallbackId,
                $size ? $size : 'post-thumbnail',
                false,
                is_array($attr) ? $attr : []
            );

            return $image ? $image : $html;
        } catch (\Throwable $e) {
            return $html;
        }
    }

    /**
     * Resolve the featured fallback attachment for a post.
     *
     * Pro unlock point: the Pro add-on overrides the global ID per post type
     * (icais_fallback_featured_map) via this filter — mirrors the
     * icais_compression_quality pattern.
     */
    private function featuredFallbackId(int $postId, string $postType): int
    {
        $globalId = (int) Settings::get('icais_fallback_featured_image_id', 0);

        /**
         * Filter the featured-image fallback attachment ID.
         *
         * @param int    $globalId The globally configured fallback attachment ID.
         * @param int    $postId   The post being rendered.
         * @param string $postType The post's type.
         */
        $id = (int) apply_filters('icais_fallback_featured_image_id', $globalId, $postId, $postType);

        return ($id > 0 && $this->isValidImage($id)) ? $id : 0;
    }

    /**
     * Whether the module master toggle is on.
     */
    private function moduleEnabled(): bool
    {
        static $enabled = null;
        if ($enabled === null) {
            $enabled = Settings::get('icais_fallback_enabled', '0') === '1';
        }
        return $enabled;
    }

    /**
     * Whether the featured-image fallback is fully enabled.
     */
    private function featuredEnabled(): bool
    {
        static $enabled = null;
        if ($enabled === null) {
            $enabled = $this->moduleEnabled()
                && Settings::get('icais_fallback_featured_enabled', '0') === '1';
        }
        return $enabled;
    }

    /**
     * Whether a post type is in the allow-list (empty list = all public types).
     */
    private function postTypeAllowed(string $postType): bool
    {
        static $allowed = null;
        if ($allowed === null) {
            $raw = Settings::get('icais_fallback_featured_post_types', '[]');
            $list = json_decode(is_string($raw) ? $raw : '[]', true);
            $allowed = (is_array($list) && !empty($list))
                ? array_values(array_map('sanitize_key', $list))
                : array_values(get_post_types(['public' => true]));
            // Never fall back on attachments themselves.
            $allowed = array_diff($allowed, ['attachment']);
        }
        return in_array($postType, $allowed, true);
    }

    /**
     * Whether an attachment exists and is a real image (per-request cached).
     */
    private function isValidImage(int $attachmentId): bool
    {
        if (!isset($this->validImageCache[$attachmentId])) {
            $this->validImageCache[$attachmentId] = wp_attachment_is_image($attachmentId);
        }
        return $this->validImageCache[$attachmentId];
    }
}
