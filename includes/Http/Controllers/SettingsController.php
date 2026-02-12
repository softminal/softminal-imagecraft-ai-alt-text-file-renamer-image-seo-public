<?php

namespace SmartAltTextAi\Includes\Http\Controllers;

use SmartAltTextAi\Includes\Models\ApiSetting;
use SmartAltTextAi\Includes\Services\Settings;
use SmartAltTextAi\Includes\Services\AI\AIFactory;
use SmartAltTextAi\Includes\Services\SeoIntegration;

if (!defined('ABSPATH')) {
    exit;
}

class SettingsController extends Controller
{
    /**
     * Get all settings
     */
    public function index($request)
    {
        $userId = get_current_user_id();
        
        // Suppress database errors and handle gracefully
        global $wpdb;
        $wpdb->suppress_errors = true;
        
        $apiSetting = null;
        try {
            $apiSetting = ApiSetting::getActive($userId);
        } catch (\Exception $e) {
        }
        
        $wpdb->suppress_errors = false;
        
        return $this->response([
            'success' => true,
            'settings' => Settings::all(),
            'api_setting' => $apiSetting ? [
                'provider' => $apiSetting->api_provider,
                'is_active' => (bool) $apiSetting->is_active,
                'has_key' => !is_null($apiSetting->api_key_encrypted)
            ] : null,
            'wizard_completed' => Settings::isWizardCompleted($userId),
            'seo_plugin' => SeoIntegration::isActive() ? [
                'active' => true,
                'plugin' => SeoIntegration::getActivePlugin(),
                'label'  => SeoIntegration::getActivePluginLabel(),
            ] : ['active' => false, 'plugin' => null, 'label' => ''],
        ]);
    }
    
    /**
     * Update settings
     */
    public function update($request)
    {
        $params = $request->get_json_params();
        $settings = $params['settings'] ?? [];
        
        if (empty($settings)) {
            return $this->response([
                'success' => false,
                'message' => __('No settings provided', 'smart-alt-text-ai')
            ], 400);
        }

        // Validate settings keys
        $validKeys = array_keys(Settings::all());
        $invalidKeys = [];
        foreach (array_keys($settings) as $key) {
            if (!in_array($key, $validKeys)) {
                $invalidKeys[] = $key;
            }
        }

        if (!empty($invalidKeys)) {
            return $this->response([
                'success' => false,
                'message' => __('Invalid setting keys:', 'smart-alt-text-ai') . ' ' . implode(', ', $invalidKeys)
            ], 400);
        }

        try {
            if (Settings::update($settings)) {
                return $this->response([
                    'success' => true,
                    'message' => __('Settings updated successfully', 'smart-alt-text-ai')
                ]);
            }
            
            // If update returns false, it might still be successful if values were unchanged
            // Check if settings were actually updated by comparing
            $allSettings = Settings::all();
            $allMatch = true;
            foreach ($settings as $key => $value) {
                $currentValue = $allSettings[$key] ?? null;
                if (self::normalizeForComparison($currentValue) !== self::normalizeForComparison($value)) {
                    $allMatch = false;
                    break;
                }
            }
            
            if ($allMatch) {
                // All values match, so update was successful (just no changes needed)
                return $this->response([
                    'success' => true,
                    'message' => __('Settings updated successfully', 'smart-alt-text-ai')
                ]);
            }
            
            return $this->response([
                'success' => false,
                'message' => __('Failed to update some settings', 'smart-alt-text-ai')
            ], 400);
        } catch (\Exception $e) {
            return $this->response([
                'success' => false,
                'message' => __('Error updating settings:', 'smart-alt-text-ai') . ' ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Normalize value for comparison
     */
    private static function normalizeForComparison($value)
    {
        if ($value === '') {
            return '';
        }
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
     * Save API key
     */
    public function saveApiKey($request)
    {
        $params = $request->get_json_params();
        $provider = sanitize_text_field($params['provider'] ?? '');
        // Don't sanitize API key - it may contain special characters that sanitize_text_field would remove
        $apiKey = trim($params['api_key'] ?? '');
        
        if (empty($provider) || empty($apiKey)) {
            return $this->response([
                'success' => false,
                'message' => __('Provider and API key are required', 'smart-alt-text-ai')
            ], 400);
        }
        
        // Basic validation - API keys should be non-empty strings
        if (!is_string($apiKey) || strlen($apiKey) < 10) {
            return $this->response([
                'success' => false,
                'message' => __('Invalid API key format', 'smart-alt-text-ai')
            ], 400);
        }
        
        if (!in_array($provider, ['anthropic', 'openai', 'gemini'])) {
            return $this->response([
                'success' => false,
                'message' => __('Invalid provider', 'smart-alt-text-ai')
            ], 400);
        }
        
        $userId = get_current_user_id();
        
        // Deactivate other providers
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional direct update
        $wpdb->update(
            ApiSetting::table(),
            ['is_active' => 0],
            ['user_id' => $userId]
        );
        
        // Get or create API setting
        $apiSetting = ApiSetting::where('user_id', $userId)
            ->andWhere('api_provider', $provider)
            ->first();
        
        if (!$apiSetting) {
            $apiSetting = ApiSetting::create([
                'user_id' => $userId,
                'api_provider' => $provider,
                'is_active' => 1
            ]);
        } else {
            $apiSetting->is_active = 1;
        }
        
        try {
            $apiSetting->setApiKey($apiKey);
        } catch (\Exception $e) {
            return $this->response([
                'success' => false,
                'message' => __('Failed to encrypt API key:', 'smart-alt-text-ai') . ' ' . $e->getMessage()
            ], 400);
        }
        
        // Verify the encrypted key was set before saving
        $encryptedKey = $apiSetting->api_key_encrypted ?? null;
        
        if (empty($encryptedKey)) {
            return $this->response([
                'success' => false,
                'message' => __('Failed to encrypt API key. Encrypted value is empty.', 'smart-alt-text-ai')
            ], 400);
        }
        
        $saved = $apiSetting->save();
        
        if (!$saved) {
            return $this->response([
                'success' => false,
                'message' => __('Failed to save API key to database', 'smart-alt-text-ai')
            ], 500);
        }
        
        // Verify it was actually saved by checking database directly
        $tableName = ApiSetting::table();
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $tableName)) {
            return $this->response([
                'success' => false,
                'message' => __('Invalid table configuration', 'smart-alt-text-ai')
            ], 500);
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Verification query needs real-time data; table name validated above
        $dbValue = $wpdb->get_var( $wpdb->prepare( "SELECT api_key_encrypted FROM `{$tableName}` WHERE id = %d", $apiSetting->id ) );
        
        if (empty($dbValue)) {
            return $this->response([
                'success' => false,
                'message' => __('API key was not saved correctly. Please try again.', 'smart-alt-text-ai')
            ], 500);
        }
        
        // Sync the preference setting with the active provider
        Settings::set('satai_api_provider', $provider);
        
        return $this->response([
            'success' => true,
            'message' => __('API key saved successfully', 'smart-alt-text-ai')
        ]);
    }
    
    /**
     * Test API connection
     */
    public function testApi($request)
    {
        $params = $request->get_json_params();
        $provider = sanitize_text_field($params['provider'] ?? '');
        // Don't sanitize API key - it may contain special characters that sanitize_text_field would remove
        $apiKey = trim($params['api_key'] ?? '');
        
        if (empty($provider) || empty($apiKey)) {
            return $this->response([
                'success' => false,
                'message' => __('Provider and API key are required', 'smart-alt-text-ai')
            ], 400);
        }
        
        try {
            $aiProvider = AIFactory::create($provider, $apiKey);
            $result = $aiProvider->testConnection();
            
            return $this->response($result);
        } catch (\Exception $e) {
            return $this->response([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }
    
    /**
     * Complete wizard
     */
    public function completeWizard($request)
    {
        $userId = get_current_user_id();
        
        if (empty($userId) || $userId === 0) {
            return $this->response([
                'success' => false,
                'message' => __('User not logged in', 'smart-alt-text-ai')
            ], 401);
        }
        
        if (Settings::completeWizard($userId)) {
            return $this->response([
                'success' => true,
                'message' => __('Wizard completed', 'smart-alt-text-ai')
            ]);
        }
        
        return $this->response([
            'success' => false,
            'message' => __('Failed to complete wizard', 'smart-alt-text-ai')
        ], 400);
    }
}
