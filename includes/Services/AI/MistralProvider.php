<?php

namespace Softminal\ImageCraft\Services\AI;

if (!defined('ABSPATH')) {
    exit;
}

class MistralProvider extends BaseAIProvider
{
    private const ENDPOINT = 'https://api.mistral.ai/v1/chat/completions';
    private const MODEL = 'pixtral-large-latest';
    private const TEST_MODEL = 'mistral-small-latest';
    private const COST_PER_1K_TOKENS = 0.002;

    public function generateAltText(string $imageUrl, string $tone = 'professional', ?string $context = null): array
    {
        $prompt = $this->getPrompt($tone, $context);

        $imageData = $this->getImageData($imageUrl);

        $data = [
            'model' => self::MODEL,
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

        $headers = [
            'Authorization: Bearer ' . $this->apiKey
        ];

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
        $data = [
            'model' => self::TEST_MODEL,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => 'Say "OK"'
                ]
            ],
            'max_tokens' => 10
        ];

        $headers = [
            'Authorization: Bearer ' . $this->apiKey
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
        if (isset($response['choices'][0]['message']['content'])) {
            return trim($response['choices'][0]['message']['content']);
        }

        throw new \Exception('Invalid response format');
    }

    protected function calculateCost(int $tokens): float
    {
        return ($tokens / 1000) * self::COST_PER_1K_TOKENS;
    }
}
