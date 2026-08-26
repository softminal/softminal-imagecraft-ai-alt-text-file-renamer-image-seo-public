<?php

namespace Softminal\ImageCraft\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Settings service for plugin options
 */
class Settings
{
    /** @var array|null In-memory cache for the current request */
    private static $allCache = null;

    private static $defaults = [
        'icais_api_provider' => 'anthropic',
        'icais_default_tone' => 'professional',
        'icais_max_length' => 125,
        'icais_auto_generate' => '0',
        'icais_preview_before_accept' => '1',
        'icais_skip_existing' => '0',
        'icais_batch_size' => 3,
        'icais_request_timeout' => 60,
        'icais_language' => 'en',
        'icais_generate_title' => '1',
        'icais_title_default_tone' => 'seo',
        'icais_title_max_length' => 30,
        'icais_title_context_mode' => 'image',
        'icais_title_custom_prompt' => '',
        'icais_title_preview_before_accept' => '1',
        'icais_title_skip_existing' => '0',
        'icais_title_auto_generate' => '0',
        'icais_auto_rename' => '0',
        'icais_rename_mode' => 'title_only',
        'icais_rename_max_length' => 120,
        'icais_woo_auto_generate' => '0',
        'icais_woo_include_product_info' => '1',
        'icais_woo_default_tone' => 'woo_seo',
        'icais_custom_prompt' => '',
        'icais_woo_custom_prompt' => '',
        'icais_seo_integration' => '0',
        'icais_seo_use_keyphrase' => '1',
        'icais_openrouter_model' => 'anthropic/claude-3.5-sonnet',
        'icais_openai_model' => 'gpt-4o-mini',
        'icais_anthropic_model' => 'claude-haiku-4-5',
        'icais_gemini_model' => 'gemini-2.5-flash',
        'icais_excluded_post_types' => '[]',
        'icais_allowed_roles' => '[]',          // roles allowed to generate alt text; empty = no restriction (admins always allowed)
        'icais_integration_kadence' => '1',
        'icais_integration_spectra' => '1',
        'icais_integration_yoast' => '1',
        'icais_integration_rankmath' => '1',
        'icais_integration_aioseo' => '1',
        'icais_integration_seopress' => '1',
        'icais_integration_woocommerce' => '1',
        'icais_integration_wpml' => '0',
        'icais_integration_polylang' => '0',
        'icais_integration_filebird' => '1',
        'icais_use_wp7_ai' => '0',              // WordPress 7.0 native AI Client (opt-in)

        // Image compression (all opt-in; Pro unlocks auto/webp/quality/resize)
        'icais_compression_enabled' => '1',     // master toggle for the Compress feature (on by default)
        'icais_compression_quality' => 85,      // visually-lossless JPEG/WebP quality 1-100 (Pro: adjustable)
        'icais_compression_max_width' => 2048,  // downscale cap on longest edge in px, 0 = no resize (Pro-adjustable; free fixed at 2048)
        'icais_compression_convert_webp' => '0',// generate WebP sidecars (free; manual + bulk)
        'icais_compression_convert_avif' => '0',// generate AVIF sidecars (free; needs server support)
        'icais_nextgen_delivery_enabled' => '0',// serve WebP/AVIF sidecars on the frontend via <picture> (opt-in)
        'icais_nextgen_auto' => '0',            // auto-convert on upload (Pro-effect; inert in free)
        'icais_compression_keep_backup' => '1', // back up original before compressing (default on; user can disable)
        'icais_compression_skip_compressed' => '1', // skip already-compressed images
        'icais_compression_min_size_kb' => 50,  // skip images smaller than this
        'icais_compression_auto' => '0',        // auto-compress on upload (Pro)

        // Combined "Generate All" metadata (alt + title + caption in one AI call).
        // Feature is opt-in (master toggle off) so existing installs are unaffected;
        // when used, all three fields default on. Auto-on-upload is Pro-only.
        'icais_meta_enabled' => '0',            // master toggle for the Generate All feature
        'icais_meta_include_alt' => '1',        // include alt text in combined generation
        'icais_meta_include_title' => '1',      // include title in combined generation
        'icais_meta_include_caption' => '1',    // include caption in combined generation
        'icais_meta_auto_generate' => '0',      // auto-generate ALL fields on upload (priority; Pro-gated; free never acts)
        'icais_caption_auto_generate' => '0',   // auto-generate description/caption on upload (Pro-gated)
        'icais_caption_max_length' => 150,      // caption max characters
        'icais_caption_default_tone' => 'professional', // caption tone
        'icais_caption_skip_existing' => '0',   // skip caption when one already exists

        // Image Fallbacks (all opt-in, default off; first public-frontend surface).
        // Broken <img> onerror swap + missing-featured-image fallback. Non-destructive.
        'icais_fallback_enabled' => '0',              // module master toggle
        'icais_fallback_broken_enabled' => '0',       // JS onerror swap for broken <img>
        'icais_fallback_broken_image_id' => 0,        // attachment shown for broken images
        'icais_fallback_featured_enabled' => '0',     // missing-featured-image fallback
        'icais_fallback_featured_image_id' => 0,      // attachment used as default featured image
        'icais_fallback_featured_post_types' => '[]', // post types allow-list (empty = all public)
        // Pro-effect keys (inert in free — the Pro add-on reads them; mirrors icais_compression_quality)
        'icais_fallback_featured_map' => '{}',        // {post_type: attachment_id} per-type overrides (Pro)
        'icais_fallback_scan_schedule' => '0',        // '0' | 'daily' | 'weekly' scheduled broken scan (Pro)
        'icais_fallback_scan_email' => '1',           // email admin when scheduled scan finds NEW broken images (Pro)
    ];

    /**
     * Get a setting value
     *
     * Note: The stored wp_options key is ICAIS_TABLE_PREFIX + $key (e.g. 'icais_icais_max_length').
     * The defaults array uses icais_ prefixed keys for namespacing. This results in a double prefix
     * in wp_options, which is intentional for backwards compatibility with existing installations.
     */
    public static function get(string $key, $default = null)
    {
        $optionKey = ICAIS_TABLE_PREFIX . $key;
        $value = get_option($optionKey, null);

        if ($value === null) {
            return $default ?? (self::$defaults[$key] ?? null);
        }

        return $value;
    }

    /**
     * Set a setting value
     */
    public static function set(string $key, $value): bool
    {
        $optionKey = ICAIS_TABLE_PREFIX . $key;

        // Bust the request-level cache
        self::$allCache = null;

        // update_option returns true on success, false when the value is unchanged
        // (no-op) OR on a real DB error. Call it directly — the previous pre-check
        // read via self::get() was redundant because update_option() does its own
        // internal read against the WP object cache before writing.
        $result = update_option($optionKey, $value);

        if ($result === false) {
            // Distinguish "already the same value" (success) from a real error
            // with one read-back from the object cache.
            $stored = get_option($optionKey);
            return self::normalizeValueForComparison($stored) === self::normalizeValueForComparison($value);
        }

        return true;
    }

    /**
     * Normalize value for comparison (handles WordPress option storage quirks)
     */
    public static function normalizeValueForComparison($value)
    {
        // Preserve empty strings so clearing a text field (e.g. custom prompt)
        // is not treated the same as setting a boolean to false/0.
        if ($value === '') {
            return '';
        }
        // WordPress stores booleans as strings '1'/'0' or integers 1/0
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if ($value === '1' || $value === 1) {
            return '1';
        }
        if ($value === '0' || $value === 0 || $value === false) {
            return '0';
        }
        return (string) $value;
    }

    /**
     * Get the default settings keys and values.
     */
    public static function getDefaults(): array
    {
        return self::$defaults;
    }

    /**
     * Get a setting value as a boolean.
     * Treats '1', 1, and true as true; everything else as false.
     */
    public static function getBoolean(string $key, bool $default = false): bool
    {
        $value = self::get($key);
        if ($value === null) {
            return $default;
        }
        return ($value === '1' || $value === 1 || $value === true);
    }

    /**
     * Get all settings (cached for the current request)
     */
    public static function all(): array
    {
        if (self::$allCache !== null) {
            return self::$allCache;
        }

        $settings = [];

        foreach (self::$defaults as $key => $default) {
            $settings[$key] = self::get($key);
        }

        self::$allCache = $settings;
        return $settings;
    }

    /**
     * Update multiple settings at once
     */
    public static function update(array $settings): bool
    {
        $allSuccess = true;
        $updatedCount = 0;

        foreach ($settings as $key => $value) {
            // Only update valid settings that exist in defaults
            if (array_key_exists($key, self::$defaults)) {
                if (self::set($key, $value)) {
                    $updatedCount++;
                } else {
                    $allSuccess = false;
                }
            }
        }

        // Return true if at least one setting was updated, or if all were already correct
        // This handles the case where update_option returns false for unchanged values
        return $allSuccess || $updatedCount > 0;
    }

    /**
     * Check if wizard is completed
     *
     * Checks the current user's meta first, then falls back to a site-wide flag
     * so that any admin who logs in after the wizard was completed by another admin
     * is not forced through the wizard again.
     */
    public static function isWizardCompleted(?int $userId = null): bool
    {
        if ($userId === null) {
            $userId = get_current_user_id();
        }

        // Per-user check
        if ((bool) get_user_meta($userId, ICAIS_TABLE_PREFIX.'wizard_completed', true)) {
            // Self-heal: stamp the site-wide flag for existing installs that pre-date it
            if (!get_option(ICAIS_TABLE_PREFIX.'wizard_completed', false)) {
                update_option(ICAIS_TABLE_PREFIX.'wizard_completed', true);
            }
            return true;
        }

        // Site-wide option check (set by completeWizard or self-heal above)
        if (get_option(ICAIS_TABLE_PREFIX.'wizard_completed', false)) {
            return true;
        }

        // Last resort: check if any user on this site has the wizard_completed meta.
        // This handles existing installs that pre-date the site-wide option.
        $users = get_users([
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- One-time backwards-compat lookup for pre-site-option installs; stamps the option afterward so it never runs again, and bounded by number=>1.
            'meta_key'   => ICAIS_TABLE_PREFIX.'wizard_completed',
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- One-time backwards-compat lookup for pre-site-option installs; stamps the option afterward so it never runs again, and bounded by number=>1.
            'meta_value' => '1',
            'number'     => 1,
            'fields'     => 'ids',
        ]);
        if (!empty($users)) {
            // Stamp the site-wide option so this query never runs again
            update_option(ICAIS_TABLE_PREFIX.'wizard_completed', true);
            return true;
        }

        return false;
    }

    /**
     * Mark wizard as completed
     */
    public static function completeWizard(?int $userId = null): bool
    {
        if ($userId === null) {
            $userId = get_current_user_id();
        }

        // Validate user ID
        if (empty($userId) || $userId === 0) {

            return false;
        }

        // Set site-wide flag so other admins don't encounter the wizard
        update_option(ICAIS_TABLE_PREFIX.'wizard_completed', true);

        // update_user_meta returns meta_id if new, true if updated, false on failure
        // If value already exists and is the same, it returns false, so we need to check differently
        return update_user_meta($userId, ICAIS_TABLE_PREFIX.'wizard_completed', true);
    }
}
