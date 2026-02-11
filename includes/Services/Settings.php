<?php

namespace SmartAltTextAi\Includes\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Settings service for plugin options
 */
class Settings
{
    private static $defaults = [
        'satai_api_provider' => 'anthropic',
        'satai_default_tone' => 'professional',
        'satai_max_length' => 150,
        'satai_auto_generate' => '0',
        'satai_preview_before_accept' => '1',
        'satai_skip_existing' => '0',
        'satai_batch_size' => 5,
        'satai_request_timeout' => 60,
        'satai_language' => 'en',
        'satai_email_on_complete' => '0',
        'satai_auto_rename' => '0',
        'satai_rename_max_length' => 120,
        'satai_woo_auto_generate' => '0',
        'satai_woo_include_product_info' => '1',
        'satai_woo_default_tone' => 'woo_seo',
        'satai_custom_prompt' => '',
        'satai_woo_custom_prompt' => '',
    ];

    /**
     * Get a setting value
     */
    public static function get(string $key, $default = null)
    {
        // Add prefix for WordPress option name
        $optionKey = SATAI_TABLE_PREFIX . $key;
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
        $optionKey = SATAI_TABLE_PREFIX . $key;

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

        return (bool) get_user_meta($userId, SATAI_TABLE_PREFIX.'wizard_completed', true);
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
        return update_user_meta($userId, SATAI_TABLE_PREFIX.'wizard_completed', true);
    }
}
