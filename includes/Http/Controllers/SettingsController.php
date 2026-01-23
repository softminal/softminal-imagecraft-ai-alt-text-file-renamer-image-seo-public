<?php

namespace WpAiAltText\Includes\Http\Controllers;

use WpAiAltText\Includes\Models\ApiSetting;
use WpAiAltText\Includes\Services\Settings;
use WpAiAltText\Includes\Services\AI\AIFactory;

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
            // Table might not exist yet - this is OK during initial setup
            error_log('WP AI Alt Text: Could not fetch API setting - ' . $e->getMessage());
        }
        
        $wpdb->suppress_errors = false;
        
        return $this->response([
            'success' => true,
            'settings' => Settings::all(),
            'api_setting' => $apiSetting ? [
                'provider' => $apiSetting->api_provider,
                'is_active' => (bool) $apiSetting->is_active,
                'has_key' => !empty($apiSetting->api_key_encrypted)
            ] : null,
            'wizard_completed' => Settings::isWizardCompleted($userId)
        ]);
    }
    
    /**
     * Update settings
     */
    public function update($request)
    {
        $params = $request->get_json_params();
        $settings = $params['settings'] ?? [];
        
        if (Settings::update($settings)) {
            return $this->response([
                'success' => true,
                'message' => 'Settings updated successfully'
            ]);
        }
        
        return $this->response([
            'success' => false,
            'message' => 'Failed to update settings'
        ], 400);
    }
    
    /**
     * Save API key
     */
    public function saveApiKey($request)
    {
        $params = $request->get_json_params();
        $provider = sanitize_text_field($params['provider'] ?? '');
        $apiKey = sanitize_text_field($params['api_key'] ?? '');
        
        if (empty($provider) || empty($apiKey)) {
            return $this->response([
                'success' => false,
                'message' => 'Provider and API key are required'
            ], 400);
        }
        
        if (!in_array($provider, ['anthropic', 'openai', 'gemini'])) {
            return $this->response([
                'success' => false,
                'message' => 'Invalid provider'
            ], 400);
        }
        
        $userId = get_current_user_id();
        
        // Deactivate other providers
        global $wpdb;
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
        
        $apiSetting->setApiKey($apiKey);
        $apiSetting->save();
        
        return $this->response([
            'success' => true,
            'message' => 'API key saved successfully'
        ]);
    }
    
    /**
     * Test API connection
     */
    public function testApi($request)
    {
        $params = $request->get_json_params();
        $provider = sanitize_text_field($params['provider'] ?? '');
        $apiKey = sanitize_text_field($params['api_key'] ?? '');
        
        if (empty($provider) || empty($apiKey)) {
            return $this->response([
                'success' => false,
                'message' => 'Provider and API key are required'
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
        
        if (Settings::completeWizard($userId)) {
            return $this->response([
                'success' => true,
                'message' => 'Wizard completed'
            ]);
        }
        
        return $this->response([
            'success' => false,
            'message' => 'Failed to complete wizard'
        ], 400);
    }
}
