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
    private static $defaults = [
        'icais_api_provider' => 'anthropic',
        'icais_default_tone' => 'professional',
        'icais_max_length' => 150,
        'icais_auto_generate' => '0',
        'icais_preview_before_accept' => '1',
        'icais_skip_existing' => '0',
        'icais_batch_size' => 5,
        'icais_request_timeout' => 60,
        'icais_language' => 'en',
        'icais_email_on_complete' => '0',
        'icais_auto_rename' => '0',
        'icais_rename_max_length' => 120,
        'icais_woo_auto_generate' => '0',
        'icais_woo_include_product_info' => '1',
        'icais_woo_default_tone' => 'woo_seo',
        'icais_custom_prompt' => '',
        'icais_woo_custom_prompt' => '',
        'icais_seo_integration' => '0',
        'icais_seo_use_keyphrase' => '1',
        'icais_openrouter_model' => 'anthropic/claude-3.5-sonnet',
    ];

    /**
     * Get a setting value
     */
    public static function get(string $key, $default = null)
    {
        // Add prefix for WordPress option name
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
        // Get current value (without adding prefix again since get() handles it)
        $currentValue = self::get($key);

        // Normalize values for comparison (WordPress stores booleans as '1'/'0' or 1/0)
        $normalizedCurrent = self::normalizeValueForComparison($currentValue);
        $normalizedNew = self::normalizeValueForComparison($value);

        // If values are the same, no need to update - consider it successful
        if ($normalizedCurrent === $normalizedNew) {
            return true;
        }

        // Add prefix for WordPress option name
        $optionKey = ICAIS_TABLE_PREFIX . $key;

        // Update the option
        $result = update_option($optionKey, $value);

        // update_option returns false if value unchanged OR on error
        // Since we already checked for unchanged values, false here likely means an error
        // But verify by checking if the value was actually set
        if ($result === false) {
            $verifyValue = get_option($optionKey);
            $normalizedVerify = self::normalizeValueForComparison($verifyValue);
            // If value matches what we tried to set, it's actually successful
            return $normalizedVerify === $normalizedNew;
        }

        return true;
    }

    /**
     * Normalize value for comparison (handles WordPress option storage quirks)
     */
    private static function normalizeValueForComparison($value)
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
     * Get all settings
     */
    public static function all(): array
    {
        $settings = [];

        foreach (self::$defaults as $key => $default) {
            $settings[$key] = self::get($key);
        }

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
     */
    public static function isWizardCompleted(?int $userId = null): bool
    {
        if ($userId === null) {
            $userId = get_current_user_id();
        }

        return (bool) get_user_meta($userId, ICAIS_TABLE_PREFIX.'wizard_completed', true);
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

        // update_user_meta returns meta_id if new, true if updated, false on failure
        // If value already exists and is the same, it returns false, so we need to check differently
        return update_user_meta($userId, ICAIS_TABLE_PREFIX.'wizard_completed', true);
    }
}
