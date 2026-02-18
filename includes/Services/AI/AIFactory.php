<?php

namespace Softminal\ImageCraft\Services\AI;

use Softminal\ImageCraft\Models\ApiSetting;
use Softminal\ImageCraft\Services\Encryption;
use Softminal\ImageCraft\Services\Settings;

if (!defined('ABSPATH')) {
    exit;
}

class AIFactory
{
    /**
     * Create AI provider instance
     */
    public static function create(string $provider, string $apiKey, int $timeout = 60): BaseAIProvider
    {
        $timeout = Settings::get('icais_request_timeout', $timeout);
        switch (strtolower($provider)) {
            case 'anthropic':
                return new AnthropicProvider($apiKey, $timeout);

            case 'openai':
                return new OpenAIProvider($apiKey, $timeout);

            case 'gemini':
                return new GeminiProvider($apiKey, $timeout);

            case 'mistral':
                return new MistralProvider($apiKey, $timeout);

            case 'openrouter':
                return new OpenRouterProvider($apiKey, $timeout);

            default:
                throw new \Exception('Unknown provider: ' . esc_html($provider));
        }
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
        return self::create($apiSetting->api_provider, Encryption::decrypt($apiSetting->api_key_encrypted));
    }
}
