<?php

namespace Softminal\ImageCraft\Http\Controllers;

use Softminal\ImageCraft\Models\ApiSetting;
use Softminal\ImageCraft\Services\Settings;
use Softminal\ImageCraft\Services\AI\AIFactory;
use Softminal\ImageCraft\Services\SeoIntegration;

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

        $apiSetting = null;
        try {
            $apiSetting = ApiSetting::getActive($userId);
        } catch (\Exception $e) {
            // Table may not exist yet during activation
        }
        
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
            'available_models' => AIFactory::getAvailableModels(),
            'available_languages' => \Softminal\ImageCraft\Services\AI\BaseAIProvider::getLanguageMap(),
            'woo_active' => class_exists('WooCommerce'),
            'available_post_types' => self::getPublicPostTypes(),
            'plugin_version' => defined('ICAIS_VERSION') ? ICAIS_VERSION : '0.0.0',
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
                'message' => __('No settings provided', 'imagecraft-ai-alt-text-file-renamer-image-seo')
            ], 400);
        }

        // Validate settings keys
        $validKeys = array_keys(Settings::all());
        $invalidKeys = [];
        foreach (array_keys($settings) as $key) {
            if (!in_array($key, $validKeys)) {
                $invalidKeys[] = sanitize_text_field($key);
            }
        }

        if (!empty($invalidKeys)) {
            return $this->response([
                'success' => false,
                'message' => __('Invalid setting keys:', 'imagecraft-ai-alt-text-file-renamer-image-seo') . ' ' . implode(', ', $invalidKeys)
            ], 400);
        }

        // Sanitize setting values based on type
        $intKeys = ['icais_max_length', 'icais_batch_size', 'icais_request_timeout', 'icais_rename_max_length'];
        $boolKeys = ['icais_auto_generate', 'icais_preview_before_accept', 'icais_skip_existing', 'icais_auto_rename', 'icais_woo_auto_generate', 'icais_woo_include_product_info', 'icais_seo_integration', 'icais_seo_use_keyphrase'];
        $textareaKeys = ['icais_custom_prompt', 'icais_woo_custom_prompt'];
        $jsonArrayKeys = ['icais_excluded_post_types'];

        // Server-side caps for integer settings to prevent DoS
        $intCaps = [
            'icais_batch_size' => 50,
            'icais_request_timeout' => 300,
            'icais_max_length' => 500,
            'icais_rename_max_length' => 500,
        ];

        // Model setting keys that need validation against available models
        $modelKeys = ['icais_openai_model', 'icais_anthropic_model', 'icais_gemini_model'];

        foreach ($settings as $key => $value) {
            if (in_array($key, $intKeys, true)) {
                $settings[$key] = absint($value);
                if (isset($intCaps[$key])) {
                    $settings[$key] = min($settings[$key], $intCaps[$key]);
                }
            } elseif (in_array($key, $boolKeys, true)) {
                $settings[$key] = $value ? '1' : '0';
            } elseif (in_array($key, $jsonArrayKeys, true)) {
                // Accept array or JSON string, sanitize each slug, store as JSON
                $arr = is_array($value) ? $value : json_decode($value, true);
                if (!is_array($arr)) {
                    $arr = [];
                }
                $settings[$key] = wp_json_encode(array_values(array_map('sanitize_key', $arr)));
            } elseif (in_array($key, $textareaKeys, true)) {
                $settings[$key] = sanitize_textarea_field(wp_unslash($value));
            } else {
                $settings[$key] = sanitize_text_field(wp_unslash($value));
            }

            // Validate language against supported list — correct invalid values to default
            if ($key === 'icais_language') {
                if (empty($settings[$key]) || !in_array($settings[$key], \Softminal\ImageCraft\Services\AI\BaseAIProvider::getSupportedLanguages(), true)) {
                    $settings[$key] = 'en';
                }
            }

            // Validate model selections against available models
            if (in_array($key, $modelKeys, true) && !empty($settings[$key])) {
                $providerSlug = str_replace(['icais_', '_model'], '', $key);
                $availableModels = AIFactory::getAvailableModels($providerSlug);
                $modelList = $availableModels[$providerSlug] ?? [];
                if (!in_array($settings[$key], $modelList, true)) {
                    unset($settings[$key]); // Drop invalid model value
                }
            }
        }

        try {
            if (Settings::update($settings)) {
                return $this->response([
                    'success' => true,
                    'message' => __('Settings updated successfully', 'imagecraft-ai-alt-text-file-renamer-image-seo')
                ]);
            }
            
            // If update returns false, it might still be successful if values were unchanged
            // Check if settings were actually updated by comparing
            $allSettings = Settings::all();
            $allMatch = true;
            foreach ($settings as $key => $value) {
                $currentValue = $allSettings[$key] ?? null;
                if (Settings::normalizeValueForComparison($currentValue) !== Settings::normalizeValueForComparison($value)) {
                    $allMatch = false;
                    break;
                }
            }
            
            if ($allMatch) {
                // All values match, so update was successful (just no changes needed)
                return $this->response([
                    'success' => true,
                    'message' => __('Settings updated successfully', 'imagecraft-ai-alt-text-file-renamer-image-seo')
                ]);
            }
            
            return $this->response([
                'success' => false,
                'message' => __('Failed to update some settings', 'imagecraft-ai-alt-text-file-renamer-image-seo')
            ], 400);
        } catch (\Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging only when WP_DEBUG is enabled
                error_log('ImageCraft settings update error: ' . $e->getMessage());
            }
            return $this->response([
                'success' => false,
                'message' => __('An error occurred while updating settings.', 'imagecraft-ai-alt-text-file-renamer-image-seo')
            ], 500);
        }
    }
    
    /**
     * Save API key
     */
    public function saveApiKey($request)
    {
        $params = $request->get_json_params();
        $provider = sanitize_text_field($params['provider'] ?? '');

        // Minimal processing for API key — avoid sanitize_text_field which may be
        // hooked by WAFs/plugins and could log the key value
        $apiKey = isset($params['api_key']) ? wp_unslash($params['api_key']) : '';

        if (empty($provider) || empty($apiKey)) {
            return $this->response([
                'success' => false,
                'message' => __('Provider and API key are required', 'imagecraft-ai-alt-text-file-renamer-image-seo')
            ], 400);
        }

        // Basic validation - API keys should be non-empty strings
        if (!is_string($apiKey) || strlen($apiKey) < 10) {
            return $this->response([
                'success' => false,
                'message' => __('Invalid API key format', 'imagecraft-ai-alt-text-file-renamer-image-seo')
            ], 400);
        }
        
        if (!in_array($provider, ['anthropic', 'openai', 'gemini', 'mistral', 'openrouter'])) {
            return $this->response([
                'success' => false,
                'message' => __('Invalid provider', 'imagecraft-ai-alt-text-file-renamer-image-seo')
            ], 400);
        }
        
        $userId = get_current_user_id();
        
        // Deactivate other providers
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional direct update
        $wpdb->update(
            ApiSetting::table(),
            ['is_active' => 0],
            ['user_id' => $userId],
            ['%d'],
            ['%d']
        );
        // Invalidate model cache after direct DB update (bypasses Model::save())
        ApiSetting::invalidateCache();
        
        // Get or create API setting
        $apiSetting = ApiSetting::where('user_id', $userId)
            ->andWhere('api_provider', $provider)
            ->first();
        
        if (!$apiSetting) {
            // Don't use create() here — it would INSERT immediately without
            // the required NOT NULL fields (api_key_encrypted, encryption_iv),
            // which fails on MySQL strict mode. Instead, build the model in
            // memory and let the single save() below persist all fields at once.
            $apiSetting = new ApiSetting();
            $apiSetting->user_id = $userId;
            $apiSetting->api_provider = $provider;
            $apiSetting->is_active = 1;
        } else {
            // Verify ownership (defense-in-depth; query already scopes by user_id)
            if ((int) $apiSetting->user_id !== $userId) {
                return $this->response([
                    'success' => false,
                    'message' => __('Not authorized', 'imagecraft-ai-alt-text-file-renamer-image-seo')
                ], 403);
            }
            $apiSetting->is_active = 1;
        }
        
        try {
            $apiSetting->setApiKey($apiKey);
        } catch (\Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging only when WP_DEBUG is enabled
                error_log('ImageCraft API key encryption error: ' . $e->getMessage());
            }
            return $this->response([
                'success' => false,
                'message' => __('Failed to encrypt API key. Please try again.', 'imagecraft-ai-alt-text-file-renamer-image-seo')
            ], 400);
        }
        
        // Verify the encrypted key was set before saving
        $encryptedKey = $apiSetting->api_key_encrypted ?? null;
        
        if (empty($encryptedKey)) {
            return $this->response([
                'success' => false,
                'message' => __('Failed to encrypt API key. Encrypted value is empty.', 'imagecraft-ai-alt-text-file-renamer-image-seo')
            ], 400);
        }
        
        $saved = $apiSetting->save();
        
        if (!$saved) {
            return $this->response([
                'success' => false,
                'message' => __('Failed to save API key to database', 'imagecraft-ai-alt-text-file-renamer-image-seo')
            ], 500);
        }
        
        // Verify it was actually saved by checking database directly
        $tableName = ApiSetting::table();
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $tableName)) {
            return $this->response([
                'success' => false,
                'message' => __('Invalid table configuration', 'imagecraft-ai-alt-text-file-renamer-image-seo')
            ], 500);
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Verification query needs real-time data; table name validated above
        $dbValue = $wpdb->get_var( $wpdb->prepare( "SELECT api_key_encrypted FROM `{$tableName}` WHERE id = %d", $apiSetting->id ) );
        
        if (empty($dbValue)) {
            return $this->response([
                'success' => false,
                'message' => __('API key was not saved correctly. Please try again.', 'imagecraft-ai-alt-text-file-renamer-image-seo')
            ], 500);
        }
        
        // Sync the preference setting with the active provider
        Settings::set('icais_api_provider', $provider);

        /**
         * Fires after an API key is saved or rotated.
         *
         * @param int    $userId   The user who changed the key.
         * @param string $provider The AI provider slug (e.g. 'openai', 'anthropic').
         */
        do_action('icais_api_key_saved', $userId, $provider);

        return $this->response([
            'success' => true,
            'message' => __('API key saved successfully', 'imagecraft-ai-alt-text-file-renamer-image-seo')
        ]);
    }
    
    /**
     * Test API connection
     */
    public function testApi($request)
    {
        // Rate limit: max 5 test requests per minute per user
        $userId = get_current_user_id();
        $transientKey = 'icais_test_api_' . $userId;
        $attempts = (int) get_transient($transientKey);
        if ($attempts >= 5) {
            return $this->response([
                'success' => false,
                'message' => __('Too many test requests. Please wait a minute before trying again.', 'imagecraft-ai-alt-text-file-renamer-image-seo')
            ], 429);
        }
        set_transient($transientKey, $attempts + 1, MINUTE_IN_SECONDS);

        $params = $request->get_json_params();
        $provider = sanitize_text_field($params['provider'] ?? '');

        // Minimal processing for API key — avoid sanitize_text_field which may be
        // hooked by WAFs/plugins and could log the key value
        $apiKey = isset($params['api_key']) ? wp_unslash($params['api_key']) : '';
        if (!is_string($apiKey) || strlen($apiKey) < 10) {
            return $this->response([
                'success' => false,
                'message' => __('Invalid API key format', 'imagecraft-ai-alt-text-file-renamer-image-seo')
            ], 400);
        }

        if (empty($provider)) {
            return $this->response([
                'success' => false,
                'message' => __('Provider is required', 'imagecraft-ai-alt-text-file-renamer-image-seo')
            ], 400);
        }

        try {
            $model = sanitize_text_field($params['model'] ?? '');
            $aiProvider = AIFactory::create($provider, $apiKey, 60, $model ?: null);
            $result = $aiProvider->testConnection();

            return $this->response($result);
        } catch (\Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging only when WP_DEBUG is enabled
                error_log('ImageCraft API test error: ' . $e->getMessage());
            }
            return $this->response([
                'success' => false,
                'error' => __('API connection test failed. Please check your API key.', 'imagecraft-ai-alt-text-file-renamer-image-seo')
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
                'message' => __('User not logged in', 'imagecraft-ai-alt-text-file-renamer-image-seo')
            ], 401);
        }
        
        if (Settings::completeWizard($userId)) {
            return $this->response([
                'success' => true,
                'message' => __('Wizard completed', 'imagecraft-ai-alt-text-file-renamer-image-seo')
            ]);
        }
        
        return $this->response([
            'success' => false,
            'message' => __('Failed to complete wizard', 'imagecraft-ai-alt-text-file-renamer-image-seo')
        ], 400);
    }

    /**
     * Get all public post types as slug => label pairs (excluding 'attachment')
     */
    private static function getPublicPostTypes(): array
    {
        $postTypes = get_post_types(['public' => true], 'objects');
        $result = [];

        foreach ($postTypes as $slug => $type) {
            if ($slug === 'attachment') {
                continue;
            }
            $result[$slug] = $type->labels->singular_name ?: $type->label;
        }

        return $result;
    }
}
