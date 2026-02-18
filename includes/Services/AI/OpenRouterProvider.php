<?php

namespace Softminal\ImageCraft\Services\AI;

use Softminal\ImageCraft\Services\Settings;

if (!defined('ABSPATH')) {
    exit;
}

class OpenRouterProvider extends BaseAIProvider
{
    private const ENDPOINT = 'https://openrouter.ai/api/v1/chat/completions';
    private const DEFAULT_MODEL = 'anthropic/claude-3.5-sonnet';
    private const COST_PER_1K_TOKENS = 0.005; // Approximate average, varies by model

    public function generateAltText(string $imageUrl, string $tone = 'professional', ?string $context = null): array
    {
        $prompt = $this->getPrompt($tone, $context);
        $model = Settings::get('icais_openrouter_model', self::DEFAULT_MODEL);

        $imageData = $this->getImageData($imageUrl);

        $data = [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => $prompt
                        ],
                        [
                            'type' => 'image_url',
                            'image_url' => [
                                'url' => 'data:' . $imageData['mime_type'] . ';base64,' . $imageData['base64']
                            ]
                        ]
                    ]
                ]
            ],
            'max_tokens' => 300
        ];

        $headers = $this->getHeaders();

        try {
            $response = $this->makeRequest(self::ENDPOINT, $data, $headers);
            $altText = $this->extractAltText($response);
            $tokens = ($response['usage']['prompt_tokens'] ?? 0) + ($response['usage']['completion_tokens'] ?? 0);
            $cost = $this->calculateCost($tokens);

            return [
                'success' => true,
                'alt_text' => $altText,
                'tokens' => $tokens,
                'cost' => $cost
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function testConnection(): array
    {
        $model = Settings::get('icais_openrouter_model', self::DEFAULT_MODEL);

        $data = [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => 'Say "OK"'
                ]
            ],
            'max_tokens' => 10
        ];

        $headers = $this->getHeaders();

        try {
            $response = $this->makeRequest(self::ENDPOINT, $data, $headers);
            return ['success' => true, 'message' => 'Connection successful'];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    protected function extractAltText(array $response): string
    {
        if (isset($response['choices'][0]['message']['content'])) {
            return trim($response['choices'][0]['message']['content']);
        }

        throw new \Exception('Invalid response format');
    }

    protected function calculateCost(int $tokens): float
    {
        return ($tokens / 1000) * self::COST_PER_1K_TOKENS;
    }

    private function getHeaders(): array
    {
        return [
            'Authorization: Bearer ' . $this->apiKey,
            'HTTP-Referer: ' . site_url(),
            'X-Title: ImageCraft',
        ];
    }
}
