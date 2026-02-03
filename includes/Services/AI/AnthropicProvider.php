<?php

namespace SmartAltTextAi\Includes\Services\AI;

if (!defined('ABSPATH')) {
    exit;
}

class AnthropicProvider extends BaseAIProvider
{
    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';
    private const MODEL = 'claude-3-haiku-20240307';
    private const COST_PER_TOKEN = 0.00000025; // $0.00025 per 1K tokens

    public function generateAltText(string $imageUrl, string $tone = 'professional'): array
    {
        $prompt = $this->getPrompt($tone);

        // Get image data
        $imageData = $this->getImageData($imageUrl);

        $data = [
            'model' => self::MODEL,
            'max_tokens' => 100,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'image',
                            'source' => [
                                'type' => 'base64',
                                'media_type' => $imageData['mime_type'],
                                'data' => $imageData['base64']
                            ]
                        ],
                        [
                            'type' => 'text',
                            'text' => $prompt
                        ]
                    ]
                ]
            ]
        ];

        $headers = [
            'x-api-key: ' . $this->apiKey,
            'anthropic-version: 2023-06-01'
        ];

        try {
            $response = $this->makeRequest(self::ENDPOINT, $data, $headers);
            $altText = $this->extractAltText($response);
            $tokens = $response['usage']['input_tokens'] + ($response['usage']['output_tokens'] ?? 0);
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
        // Test with a simple text-only request
        $data = [
            'model' => self::MODEL,
            'max_tokens' => 10,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => 'Say "OK"'
                ]
            ]
        ];

        $headers = [
            'x-api-key: ' . $this->apiKey,
            'anthropic-version: 2023-06-01'
        ];

        try {
            $response = $this->makeRequest(self::ENDPOINT, $data, $headers);
            return ['success' => true, 'message' => 'Connection successful'];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    protected function extractAltText(array $response): string
    {
        if (isset($response['content'][0]['text'])) {
            return trim($response['content'][0]['text']);
        }

        throw new \Exception('Invalid response format');
    }

    protected function calculateCost(int $tokens): float
    {
        return $tokens * self::COST_PER_TOKEN;
    }

}
