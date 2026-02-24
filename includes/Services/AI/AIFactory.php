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
        'openai' => OpenAIProvider::class,
        'gemini' => GeminiProvider::class,
        // 'mistral' => MistralProvider::class,       // Planned for next release
        // 'openrouter' => OpenRouterProvider::class,  // Planned for next release
    ];

    /**
     * Create AI provider instance
     */
    public static function create(string $provider, string $apiKey, int $timeout = 60, ?string $model = null): BaseAIProvider
    {
        $timeout = (int) Settings::get('icais_request_timeout', $timeout);
        $providerKey = strtolower($provider);

        if (!isset(self::$providerClasses[$providerKey])) {
            throw new \Exception('Unknown provider: ' . esc_html($provider));
        }

        $class = self::$providerClasses[$providerKey];

        // Validate model against the provider's allowed list
        if ($model !== null && !in_array($model, $class::getAvailableModels(), true)) {
            $model = null; // Fall back to provider default
        }

        return new $class($apiKey, $timeout, $model);
    }

    /**
     * Get provider for current user
     */
    public static function getProviderForUser(?int $userId = null): ?BaseAIProvider
    {
        if ($userId === null) {
            $userId = get_current_user_id();
        }

        $apiSetting = ApiSetting::getActive($userId);

        if (!$apiSetting) {
            return null;
        }

        if (is_null($apiSetting->api_key_encrypted)) {
            return null;
        }

        $provider = $apiSetting->api_provider;
        $model = Settings::get('icais_' . $provider . '_model');

        $apiKey = $apiSetting->getApiKey();
        if ($apiKey === null) {
            return null;
        }

        return self::create($provider, $apiKey, 60, $model);
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
