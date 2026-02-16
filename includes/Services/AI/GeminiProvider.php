<?php

namespace ImageCraft\Includes\Services\AI;

if (!defined('ABSPATH')) {
    exit;
}

class GeminiProvider extends BaseAIProvider
{
    private const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent';
    private const COST_PER_1K_TOKENS = 0.0005;

    public function generateAltText(string $imageUrl, string $tone = 'professional', ?string $context = null): array
    {
        $prompt = $this->getPrompt($tone, $context);

        // Get image data
        $imageData = $this->getImageData($imageUrl);

        $data = [
            'contents' => [
                [
                    'parts' => [
                        [
                            'text' => $prompt
                        ],
                        [
                            'inline_data' => [
                                'mime_type' => $imageData['mime_type'],
                                'data' => $imageData['base64']
                            ]
                        ]
                    ]
                ]
            ],
            'generationConfig' => [
                'maxOutputTokens' => 300
            ]
        ];

        $url = self::ENDPOINT . '?key=' . urlencode($this->apiKey);

        try {
            $response = $this->makeRequest($url, $data);
            $altText = $this->extractAltText($response);
            $tokens = $this->estimateTokens($response);
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
            'contents' => [
                [
                    'parts' => [
                        [
                            'text' => 'Say "OK"'
                        ]
                    ]
                ]
            ]
        ];

        $url = self::ENDPOINT . '?key=' . urlencode($this->apiKey);

        try {
            $response = $this->makeRequest($url, $data);
            return ['success' => true, 'message' => 'Connection successful'];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    protected function extractAltText(array $response): string
    {
        if (isset($response['candidates'][0]['content']['parts'][0]['text'])) {
            return trim($response['candidates'][0]['content']['parts'][0]['text']);
        }

        throw new \Exception('Invalid response format');
    }

    protected function calculateCost(int $tokens): float
    {
        return ($tokens / 1000) * self::COST_PER_1K_TOKENS;
    }


    private function estimateTokens(array $response): int
    {
        // Rough estimation - Gemini doesn't always return token usage
        if (isset($response['usageMetadata'])) {
            return ($response['usageMetadata']['promptTokenCount'] ?? 0) +
                   ($response['usageMetadata']['candidatesTokenCount'] ?? 0);
        }

        // Fallback estimation
        return 100;
    }
}
