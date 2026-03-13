<?php

namespace Softminal\ImageCraft\Services\AI;

if (!defined('ABSPATH')) {
    exit;
}

class OpenAIProvider extends ChatCompletionsProvider
{
    private const ENDPOINT = 'https://api.openai.com/v1/chat/completions';

    public static function getAvailableModels(): array
    {
        return [
            'gpt-4.1-nano',
            'gpt-4.1-mini',
            'gpt-4.1',
            'gpt-4o-mini',
            'gpt-4o',
            'gpt-5-mini',
            'gpt-5',
            'gpt-5.2',
        ];
    }

    public static function getDefaultModel(): string
    {
        return 'gpt-4o-mini';
    }

    protected function getEndpoint(): string
    {
        return self::ENDPOINT;
    }

    protected function getHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->apiKey,
        ];
    }

    protected function getCostPer1kTokens(): float
    {
        return 0.002;
    }
}
