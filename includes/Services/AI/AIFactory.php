<?php

namespace WpAiAltText\Includes\Services\AI;
use WpAiAltText\Includes\Services\Settings;

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
        $timeout = Settings::get('alttext_request_timeout', $timeout);
        
        switch (strtolower($provider)) {
            case 'anthropic':
                return new AnthropicProvider($apiKey, $timeout);
            
            case 'openai':
                return new OpenAIProvider($apiKey, $timeout);
            
            case 'gemini':
                return new GeminiProvider($apiKey, $timeout);
            
            default:
                throw new \Exception("Unknown provider: {$provider}");
        }
    }
    
    /**
     * Get provider for current user
     */
    public static function getProviderForUser(int $userId = null): ?BaseAIProvider
    {
        if ($userId === null) {
            $userId = get_current_user_id();
        }
        
        $apiSetting = \WpAiAltText\Includes\Models\ApiSetting::getActive($userId);
        
        if (!$apiSetting) {
            return null;
        }
        
        $apiKey = $apiSetting->getApiKey();
        if (!$apiKey) {
            return null;
        }
        
        return self::create($apiSetting->api_provider, $apiKey);
    }
}
