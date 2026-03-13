<?php

namespace Softminal\ImageCraft\Services\AI;

if (!defined('ABSPATH')) {
    exit;
}

class OpenRouterProvider extends ChatCompletionsProvider
{
    private const ENDPOINT = 'https://openrouter.ai/api/v1/chat/completions';
    private const DEFAULT_MODEL = 'anthropic/claude-3.5-sonnet';

    public static function getAvailableModels(): array
    {
        return [self::DEFAULT_MODEL];
    }

    public static function getDefaultModel(): string
    {
        return self::DEFAULT_MODEL;
    }

    protected function getEndpoint(): string
    {
        return self::ENDPOINT;
    }

    protected function getHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'HTTP-Referer' => 'https://wordpress.org/plugins/imagecraft-ai-alt-text-file-renamer-image-seo/',
            'X-Title' => 'ImageCraft',
        ];
    }

    protected function getCostPer1kTokens(): float
    {
        return 0.005;
    }
}
