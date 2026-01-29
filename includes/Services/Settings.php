<?php

namespace WpAiAltText\Includes\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Settings service for plugin options
 */
class Settings
{
    private static $defaults = [
        'alttext_api_provider' => 'anthropic',
        'alttext_default_tone' => 'professional',
        'alttext_max_length' => 125,
        'alttext_auto_generate' => '0',
        'alttext_preview_before_accept' => '1',
        'alttext_skip_existing' => '0',
        'alttext_batch_size' => 5,
        'alttext_request_timeout' => 60,
        'alttext_language' => 'en',
        'alttext_email_on_complete' => '0',
    ];
    
    /**
     * Get a setting value
     */
    public static function get(string $key, $default = null)
    {
        // Add prefix for WordPress option name
        $optionKey = WP_AI_ALT_TEXT_TABLE_PREFIX . $key;
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
        $optionKey = WP_AI_ALT_TEXT_TABLE_PREFIX . $key;
        
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
        // WordPress stores booleans as strings '1'/'0' or integers 1/0
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        // Convert to string for consistent comparison
        if ($value === '1' || $value === 1 || $value === true) {
            return '1';
        }
        if ($value === '0' || $value === 0 || $value === false || $value === '') {
            return '0';
        }
        // For other types, convert to string
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
        
        return (bool) get_user_meta($userId, WP_AI_ALT_TEXT_TABLE_PREFIX.'wizard_completed', true);
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
        return update_user_meta($userId, WP_AI_ALT_TEXT_TABLE_PREFIX.'wizard_completed', true);
    }
}
