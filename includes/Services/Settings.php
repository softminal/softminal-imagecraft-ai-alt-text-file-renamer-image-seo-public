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
        'alttext_auto_generate' => false,
        'alttext_preview_before_accept' => true,
        'alttext_skip_existing' => true,
        'alttext_batch_size' => 5,
        'alttext_request_timeout' => 60,
        'alttext_language' => 'en',
        'alttext_email_on_complete' => false,
    ];
    
    /**
     * Get a setting value
     */
    public static function get(string $key, $default = null)
    {
        $value = get_option($key, null);
        
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
        return update_option($key, $value);
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
        $success = true;
        
        foreach ($settings as $key => $value) {
            if (array_key_exists($key, self::$defaults)) {
                if (!self::set($key, $value)) {
                    $success = false;
                }
            }
        }
        
        return $success;
    }
    
    /**
     * Check if wizard is completed
     */
    public static function isWizardCompleted(int $userId = null): bool
    {
        if ($userId === null) {
            $userId = get_current_user_id();
        }
        
        return (bool) get_user_meta($userId, 'alttext_wizard_completed', true);
    }
    
    /**
     * Mark wizard as completed
     */
    public static function completeWizard(int $userId = null): bool
    {
        if ($userId === null) {
            $userId = get_current_user_id();
        }
        
        return update_user_meta($userId, 'alttext_wizard_completed', true);
    }
}
