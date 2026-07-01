<?php

namespace Softminal\ImageCraft\Services\AI;

if (!defined('ABSPATH')) {
    exit;
}

class GeminiProvider extends BaseAIProvider
{
    private const BASE_ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/';
    private const COST_PER_1K_TOKENS = 0.0005;

    public static function getAvailableModels(): array
    {
        return [
            'gemini-2.5-flash',
            'gemini-2.5-flash-lite',
            'gemini-2.5-pro',
            'gemini-3-flash-preview',
            'gemini-3-pro-preview',
            'gemini-3.1-pro-preview',
        ];
    }

    public static function getDefaultModel(): string
    {
        return 'gemini-2.5-flash';
    }

    private function getEndpoint(): string
    {
        return self::BASE_ENDPOINT . $this->model . ':generateContent';
    }

    public function generateAltText(string $imageUrl, string $tone = 'professional', ?string $context = null): array
    {
        // Capture before getPrompt(), which resets metaMode as a side effect.
        $maxTokens = $this->getMaxOutputTokens();
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
                'maxOutputTokens' => $maxTokens
            ]
        ];

        $headers = $this->getHeaders();

        try {
            $response = $this->makeRequest($this->getEndpoint(), $data, $headers);
            $altText = $this->extractAltText($response);
            $tokens = $this->estimateTokens($response);
            $cost = $this->calculateCost($tokens);

            return [
                'success' => true,
                'alt_text' => $altText,
                'tokens' => $tokens,
                'cost' => $cost,
                'finish_reason' => $response['candidates'][0]['finishReason'] ?? null,
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

        $headers = $this->getHeaders();

        try {
            $response = $this->makeRequest($this->getEndpoint(), $data, $headers);
            return ['success' => true, 'message' => 'Connection successful'];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    protected function extractAltText(array $response): string
    {
        // Iterate parts — skip thought/reasoning parts that Gemini 2.5+ thinking models
        // prepend before the actual text part (identified by "thought": true).
        $parts = $response['candidates'][0]['content']['parts'] ?? [];
        foreach ($parts as $part) {
            if (!empty($part['thought'])) {
                continue;
            }
            if (!empty($part['text'])) {
                return trim($part['text']);
            }
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
            'x-goog-api-key' => $this->apiKey,
        ];
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
