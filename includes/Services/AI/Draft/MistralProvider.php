<?php

namespace Softminal\ImageCraft\Services\AI\Draft;

use Softminal\ImageCraft\Services\AI\ChatCompletionsProvider;

if (!defined('ABSPATH')) {
    exit;
}

// DRAFT: Not yet integrated. To activate, move to Services/AI/, update namespace, and uncomment in AIFactory.php.
class MistralProvider extends ChatCompletionsProvider
{
    private const ENDPOINT = 'https://api.mistral.ai/v1/chat/completions';
    private const TEST_MODEL = 'mistral-small-latest';

    public static function getAvailableModels(): array
    {
        return [
            'pixtral-large-latest',
        ];
    }

    public static function getDefaultModel(): string
    {
        return 'pixtral-large-latest';
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

    protected function getTestModel(): string
    {
        return self::TEST_MODEL;
    }
}
