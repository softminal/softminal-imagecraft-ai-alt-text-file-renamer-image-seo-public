<?php

namespace Softminal\ImageCraft\Services\AI;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shared base for providers using the OpenAI-compatible Chat Completions API.
 *
 * Subclasses only need to supply endpoint, headers, cost rate, and (optionally)
 * a different model for connection tests.
 */
abstract class ChatCompletionsProvider extends BaseAIProvider
{
    /**
     * API endpoint URL.
     */
    abstract protected function getEndpoint(): string;

    /**
     * Provider-specific request headers (e.g. Authorization).
     *
     * @return array
     */
    abstract protected function getHeaders(): array;

    /**
     * Cost per 1 000 tokens (used for rough estimates).
     */
    abstract protected function getCostPer1kTokens(): float;

    /**
     * Model used for the lightweight connection test.
     * Override in subclass if the vision model is not suitable for a simple text test.
     */
    protected function getTestModel(): string
    {
        return $this->model;
    }

    public function generateAltText(string $imageUrl, string $tone = 'professional', ?string $context = null): array
    {
        $prompt = $this->getPrompt($tone, $context);
        $imageData = $this->getImageData($imageUrl);

        $data = [
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => $prompt,
                        ],
                        [
                            'type' => 'image_url',
                            'image_url' => [
                                'url' => 'data:' . $imageData['mime_type'] . ';base64,' . $imageData['base64'],
                            ],
                        ],
                    ],
                ],
            ],
            'max_tokens' => 300,
        ];

        try {
            $response = $this->makeRequest($this->getEndpoint(), $data, $this->getHeaders());
            $altText = $this->extractAltText($response);
            $tokens = ($response['usage']['prompt_tokens'] ?? 0) + ($response['usage']['completion_tokens'] ?? 0);
            $cost = $this->calculateCost($tokens);

            return [
                'success' => true,
                'alt_text' => $altText,
                'tokens' => $tokens,
                'cost' => $cost,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function testConnection(): array
    {
        $data = [
            'model' => $this->getTestModel(),
            'messages' => [
                [
                    'role' => 'user',
                    'content' => 'Say "OK"',
                ],
            ],
            'max_tokens' => 10,
        ];

        try {
            $this->makeRequest($this->getEndpoint(), $data, $this->getHeaders());
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
        return ($tokens / 1000) * $this->getCostPer1kTokens();
    }
}
