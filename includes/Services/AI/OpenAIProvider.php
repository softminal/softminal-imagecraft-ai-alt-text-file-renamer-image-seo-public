<?php

namespace SmartAltTextAi\Includes\Services\AI;

if (!defined('ABSPATH')) {
    exit;
}

class OpenAIProvider extends BaseAIProvider
{
    private const ENDPOINT = 'https://api.openai.com/v1/chat/completions';
    private const MODEL = 'gpt-4o';
    private const COST_PER_1K_TOKENS = 0.01; // Approximate cost

    public function generateAltText(string $imageUrl, string $tone = 'professional', ?string $context = null): array
    {
        $prompt = $this->getPrompt($tone, $context);

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
                                'url' => $imageUrl
                            ]
                        ]
                    ]
                ]
            ],
            'max_tokens' => 100
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
            'model' => self::MODEL,
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
