<?php

namespace Softminal\ImageCraft\Http\Controllers;

use Softminal\ImageCraft\Models\ApiSetting;
use Softminal\ImageCraft\Services\Settings;
use Softminal\ImageCraft\Services\AI\AIFactory;
use Softminal\ImageCraft\Services\FileBird;
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
        
        $configDefined = AIFactory::isConfigDefined();

        // Resolve WP7 native AI availability defensively — the underlying core
        // AI Client must never be able to bubble a fatal up and 500 this endpoint.
        $wp7Available = false;
        try {
            $wp7Available = AIFactory::isWP7Available();
        } catch (\Throwable $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging only when WP_DEBUG is enabled
                error_log('ImageCraft: WP7 availability check failed — ' . $e->getMessage());
            }
        }

        // Check if the site has any working AI configuration — used to bypass the wizard
        // for new admins on a site that is already configured by another admin.
        $siteHasKey = $configDefined;
        if (!$siteHasKey && Settings::get('icais_use_wp7_ai', false) && $wp7Available) {
            $siteHasKey = true;
        }
        if (!$siteHasKey) {
            try {
                $siteHasKey = ApiSetting::getActive() !== null;
            } catch (\Exception $e) {
                // Table may not exist yet during activation
            }
        }

        // If config key is defined but no DB setting, synthesize a virtual api_setting
        $apiSettingResponse = null;
        if ($apiSetting) {
            $apiSettingResponse = [
                'provider' => $apiSetting->api_provider,
                'is_active' => (bool) $apiSetting->is_active,
                'has_key' => $configDefined || !is_null($apiSetting->api_key_encrypted),
                'source' => $configDefined ? 'config' : 'database',
            ];
        } elseif ($configDefined) {
            $apiSettingResponse = [
                'provider' => Settings::get('icais_api_provider', 'anthropic'),
                'is_active' => true,
                'has_key' => true,
                'source' => 'config',
            ];
        }

        return $this->response([
            'success' => true,
            'settings' => Settings::all(),
            'api_setting' => $apiSettingResponse,
            'config_api_key' => $configDefined,
            'site_has_key' => $siteHasKey,
            'wizard_completed' => Settings::isWizardCompleted($userId) || $configDefined,
            'seo_plugin' => SeoIntegration::isActive() ? [
                'active' => true,
                'plugin' => SeoIntegration::getActivePlugin(),
                'label'  => SeoIntegration::getActivePluginLabel(),
            ] : ['active' => false, 'plugin' => null, 'label' => ''],
            'wp7_available' => $wp7Available,
            'available_models' => AIFactory::getAvailableModels(),
            'available_languages' => \Softminal\ImageCraft\Services\AI\BaseAIProvider::getLanguageMap(),
            'woo_active' => class_exists('WooCommerce'),
            'available_post_types' => self::getPublicPostTypes(),
            'plugin_version' => defined('ICAIS_VERSION') ? ICAIS_VERSION : '0.0.0',
            'integrations' => self::getIntegrations(),
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
        $intKeys = ['icais_max_length', 'icais_title_max_length', 'icais_batch_size', 'icais_request_timeout', 'icais_rename_max_length', 'icais_compression_quality', 'icais_compression_max_width', 'icais_compression_min_size_kb'];
        $boolKeys = ['icais_auto_generate', 'icais_preview_before_accept', 'icais_skip_existing', 'icais_generate_title', 'icais_title_preview_before_accept', 'icais_title_skip_existing', 'icais_title_auto_generate', 'icais_auto_rename', 'icais_woo_auto_generate', 'icais_woo_include_product_info', 'icais_seo_integration', 'icais_seo_use_keyphrase', 'icais_integration_kadence', 'icais_integration_spectra', 'icais_integration_yoast', 'icais_integration_rankmath', 'icais_integration_aioseo', 'icais_integration_seopress', 'icais_integration_woocommerce', 'icais_integration_wpml', 'icais_integration_polylang', 'icais_integration_filebird', 'icais_use_wp7_ai', 'icais_compression_enabled', 'icais_compression_convert_webp', 'icais_compression_keep_backup', 'icais_compression_skip_compressed', 'icais_compression_auto'];
        $textareaKeys = ['icais_custom_prompt', 'icais_title_custom_prompt', 'icais_woo_custom_prompt'];
        $jsonArrayKeys = ['icais_excluded_post_types'];

        // Server-side caps for integer settings to prevent DoS
        $intCaps = [
            'icais_batch_size' => 50,
            'icais_request_timeout' => 300,
            'icais_max_length' => 500,
            'icais_title_max_length' => 200,
            'icais_rename_max_length' => 500,
            'icais_compression_quality' => 100,
            'icais_compression_max_width' => 10000,
            'icais_compression_min_size_kb' => 102400,
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
        
        if (!in_array($provider, ['anthropic', 'openai', 'gemini'], true)) {
            return $this->response([
                'success' => false,
                'message' => __('Invalid provider', 'imagecraft-ai-alt-text-file-renamer-image-seo')
            ], 400);
        }
        
        $userId = get_current_user_id();

        // Deactivate all existing provider keys site-wide (API config is global)
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional direct update
        $wpdb->update(
            ApiSetting::table(),
            ['is_active' => 0],
            ['is_active' => 1],
            ['%d'],
            ['%d']
        );
        // Invalidate model cache after direct DB update (bypasses Model::save())
        ApiSetting::invalidateCache();

        // Get or create the site-wide API setting for this provider
        $apiSetting = ApiSetting::where('api_provider', $provider)->first();

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

        // WP7 native AI Client — no API key needed, test via availability check
        if ($provider === 'wp7') {
            $aiProvider = \Softminal\ImageCraft\Services\AI\AIFactory::create('wp7', '', 60, null);
            return $this->response($aiProvider->testConnection());
        }

        // If no key provided in request, try wp-config.php constant
        if (empty($apiKey) || !is_string($apiKey) || strlen($apiKey) < 10) {
            $configKey = AIFactory::getConfigApiKey();
            if ($configKey !== null) {
                $apiKey = $configKey;
            } else {
                return $this->response([
                    'success' => false,
                    'message' => __('Invalid API key format', 'imagecraft-ai-alt-text-file-renamer-image-seo')
                ], 400);
            }
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
        
        // update_user_meta() returns false when the value is already set (no-op),
        // which is indistinguishable from a real failure. Treat both outcomes as
        // success when the meta key is actually present in the database.
        Settings::completeWizard($userId);

        if (Settings::isWizardCompleted($userId)) {
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
     * Get all supported integrations with their active status.
     */
    private static function getIntegrations(): array
    {
        return [
            ['key' => 'kadence', 'name' => 'Kadence Blocks', 'description' => 'Generate alt text, title, and link title from Kadence image blocks.', 'category' => 'block', 'installed' => defined('KADENCE_BLOCKS_VERSION')],
            ['key' => 'spectra', 'name' => 'Spectra', 'description' => 'Generate alt text and title from Spectra image blocks.', 'category' => 'block', 'installed' => defined('UAGB_VER')],
            ['key' => 'yoast', 'name' => 'Yoast SEO', 'description' => 'Use focus keyphrase to improve alt text relevance.', 'category' => 'seo', 'installed' => defined('WPSEO_VERSION')],
            ['key' => 'rankmath', 'name' => 'Rank Math', 'description' => 'Use focus keyphrase to improve alt text relevance.', 'category' => 'seo', 'installed' => class_exists('RankMath')],
            ['key' => 'aioseo', 'name' => 'All in One SEO', 'description' => 'Use focus keyphrase to improve alt text relevance.', 'category' => 'seo', 'installed' => defined('AIOSEO_VERSION')],
            ['key' => 'seopress', 'name' => 'SEOPress', 'description' => 'Use focus keyphrase to improve alt text relevance.', 'category' => 'seo', 'installed' => defined('SEOPRESS_VERSION')],
            ['key' => 'woocommerce', 'name' => 'WooCommerce', 'description' => 'Product-aware alt text with name, SKU, and category context.', 'category' => 'ecommerce', 'installed' => class_exists('WooCommerce')],
            ['key' => 'filebird', 'name' => 'FileBird', 'description' => 'Use media folder context for scanner filtering and more specific AI prompts.', 'category' => 'media', 'installed' => FileBird::isActive()],
            ['key' => 'wpml', 'name' => 'WPML', 'description' => 'Auto-detect language per image for multilingual sites.', 'category' => 'multilingual', 'installed' => defined('ICL_SITEPRESS_VERSION')],
            ['key' => 'polylang', 'name' => 'Polylang', 'description' => 'Auto-detect language per image for multilingual sites.', 'category' => 'multilingual', 'installed' => function_exists('pll_get_post_language')],
        ];
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
