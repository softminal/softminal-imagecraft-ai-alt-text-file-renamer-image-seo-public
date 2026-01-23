<?php

namespace WpAiAltText\Includes\Services\AI;

if (!defined('ABSPATH')) {
    exit;
}

class GeminiProvider extends BaseAIProvider
{
    private const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-pro-vision:generateContent';
    // private const ENDPOINT = 'https://generativelanguage.googleapis.com/v1/models';
    private const COST_PER_1K_TOKENS = 0.0005; // Approximate cost
    
    public function generateAltText(string $imageUrl, string $tone = 'professional'): array
    {
        $prompt = $this->getPrompt($tone);
        
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
                'maxOutputTokens' => 100
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
    
    private function getImageData(string $imageUrl): array
    {
        $imageContent = file_get_contents($imageUrl);
        if ($imageContent === false) {
            throw new \Exception('Failed to fetch image');
        }
        
        $base64 = base64_encode($imageContent);
        $mimeType = $this->getMimeType($imageUrl);
        
        return [
            'base64' => $base64,
            'mime_type' => $mimeType
        ];
    }
    
    private function getMimeType(string $url): string
    {
        $extension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        $mimeTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp'
        ];
        
        return $mimeTypes[$extension] ?? 'image/jpeg';
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
