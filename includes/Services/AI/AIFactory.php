<?php

namespace Softminal\ImageCraft\Services\AI;

use Softminal\ImageCraft\Models\ApiSetting;
use Softminal\ImageCraft\Services\Settings;

if (!defined('ABSPATH')) {
    exit;
}

class AIFactory
{
    private static $providerClasses = [
        'anthropic' => AnthropicProvider::class,
        'openai'    => OpenAIProvider::class,
        'gemini'    => GeminiProvider::class,
        'wp7'       => WP7Provider::class,           // WordPress 7.0 native AI Client (opt-in)
        // 'mistral' => MistralProvider::class,       // Planned for next release
        // 'openrouter' => OpenRouterProvider::class,  // Planned for next release
    ];

    /** wp-config.php constant name for the API key */
    private const CONFIG_CONSTANT = 'ICAIS_API_KEY';

    /**
     * Get the API key from wp-config.php constant, or null if not defined.
     */
    public static function getConfigApiKey(): ?string
    {
        if (defined(self::CONFIG_CONSTANT) && !empty(constant(self::CONFIG_CONSTANT))) {
            return constant(self::CONFIG_CONSTANT);
        }
        return null;
    }

    /**
     * Check if a wp-config.php API key is defined.
     */
    public static function isConfigDefined(): bool
    {
        return self::getConfigApiKey() !== null;
    }

    /**
     * Create AI provider instance
     */
    public static function create(string $provider, string $apiKey, int $timeout = 60, ?string $model = null): BaseAIProvider
    {
        // Single source of truth: always read timeout from Settings, fallback to parameter
        $timeout = (int) Settings::get('icais_request_timeout', $timeout);
        $providerKey = strtolower($provider);

        if (!isset(self::$providerClasses[$providerKey])) {
            throw new \Exception('Unknown provider: ' . esc_html($provider));
        }

        $class = self::$providerClasses[$providerKey];

        // Validate model against the provider's allowed list
        // WP7 provider uses WordPress-managed models — skip validation
        if ($providerKey !== 'wp7' && $model !== null && !in_array($model, $class::getAvailableModels(), true)) {
            $model = null; // Fall back to provider default
        }

        return new $class($apiKey, $timeout, $model);
    }

    /**
     * Get provider for current user.
     *
     * Returns null when the provider cannot be resolved. When WP_DEBUG is
     * enabled the specific failure reason is written to the error log so
     * site admins can diagnose "API key not configured" issues.
     */
    public static function getProviderForUser(?int $userId = null): ?BaseAIProvider
    {
        if ($userId === null) {
            $userId = get_current_user_id();
        }

        // 1. Check wp-config.php constant first (takes priority)
        $configKey = self::getConfigApiKey();
        if ($configKey !== null) {
            $provider = Settings::get('icais_api_provider', 'anthropic');
            $model = Settings::get('icais_' . $provider . '_model');
            return self::create($provider, $configKey, 60, $model);
        }

        // 2. Use WordPress 7.0 native AI Client if opted in and available
        if (Settings::get('icais_use_wp7_ai', false) && WP7Provider::isAvailable()) {
            return self::create('wp7', '', 60, null);
        }

        // 3. Fall back to database-stored encrypted key (site-wide)
        $apiSetting = ApiSetting::getActive($userId);

        if (!$apiSetting) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging only when WP_DEBUG is enabled
                error_log('ImageCraft: No active API setting found for user ' . $userId);
            }
            return null;
        }

        if (is_null($apiSetting->api_key_encrypted) || $apiSetting->api_key_encrypted === '') {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging only when WP_DEBUG is enabled
                error_log('ImageCraft: API setting found (id=' . ($apiSetting->id ?? '?') . ') but api_key_encrypted is empty for user ' . $userId);
            }
            return null;
        }

        $provider = $apiSetting->api_provider;
        $model = Settings::get('icais_' . $provider . '_model');

        $apiKey = $apiSetting->getApiKey();
        if ($apiKey === null) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging only when WP_DEBUG is enabled
                error_log('ImageCraft: Decryption failed for API setting id=' . ($apiSetting->id ?? '?') . ', provider=' . $provider . ', user=' . $userId);
            }
            return null;
        }

        return self::create($provider, $apiKey, 60, $model);
    }

    /**
     * Check if the WordPress 7.0 native AI Client is available on this site.
     * Proxies to WP7Provider::isAvailable() for use by controllers/settings.
     */
    public static function isWP7Available(): bool
    {
        return WP7Provider::isAvailable();
    }

    /**
     * Get available models for all or a specific provider
     *
     * @param string|null $provider Provider name, or null for all providers
     * @return array Keyed by provider name
     */
    public static function getAvailableModels(?string $provider = null): array
    {
        if ($provider !== null) {
            $providerKey = strtolower($provider);
            if (!isset(self::$providerClasses[$providerKey])) {
                return [];
            }
            $class = self::$providerClasses[$providerKey];
            return [$providerKey => $class::getAvailableModels()];
        }

        $models = [];
        foreach (self::$providerClasses as $key => $class) {
            $models[$key] = $class::getAvailableModels();
        }

        return $models;
    }
}
