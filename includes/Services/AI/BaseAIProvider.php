<?php

namespace WpAiAltText\Includes\Services\AI;

if (!defined('ABSPATH')) {
    exit;
}

abstract class BaseAIProvider
{
    protected string $apiKey;
    protected int $timeout;
    
    public function __construct(string $apiKey, int $timeout = 60)
    {
        $this->apiKey = $apiKey;
        $this->timeout = $timeout;
    }
    
    /**
     * Generate alt text for an image
     */
    abstract public function generateAltText(string $imageUrl, string $tone = 'professional'): array;
    
    /**
     * Test API connection
     */
    abstract public function testConnection(): array;
    
    /**
     * Get prompt based on tone
     */
    protected function getPrompt(string $tone): string
    {
        $prompts = [
            'professional' => "Analyze this image and generate a concise, professional alt text (max 125 characters) for accessibility. Describe the main subject, action, and relevant context. Do not start with \"Image of\" or \"Photo of\". Focus on what's important for someone who cannot see the image. Return only the alt text, nothing else.",
            'casual' => "Look at this image and write a friendly, conversational alt text (max 125 characters) that describes what's happening. Keep it natural and easy to understand. Don't start with \"Image of\" or \"Picture of\". Return only the alt text, nothing else.",
            'technical' => "Analyze this image and create a detailed, technical alt text (max 125 characters) describing specific elements, composition, and technical aspects. Be precise and informative. Do not use phrases like \"Image of\". Return only the alt text, nothing else.",
            'seo' => "Generate SEO-friendly alt text (max 125 characters) for this image. Include relevant keywords naturally while accurately describing the image. Make it descriptive and keyword-rich without keyword stuffing. Do not start with \"Image of\". Return only the alt text, nothing else."
        ];
        
        return $prompts[$tone] ?? $prompts['professional'];
    }
    
    /**
     * Make HTTP request
     */
    protected function makeRequest(string $url, array $data, array $headers = []): array
    {
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => array_merge([
                'Content-Type: application/json',
            ], $headers),
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        if ($error) {
            throw new \Exception("cURL Error: {$error}");
        }
        
        if ($httpCode >= 400) {
            $errorData = json_decode($response, true);
            $errorMessage = $errorData['error']['message'] ?? "HTTP Error {$httpCode}";
            throw new \Exception($errorMessage);
        }
        
        return json_decode($response, true) ?? [];
    }
    
    /**
     * Extract alt text from response
     */
    abstract protected function extractAltText(array $response): string;
    
    /**
     * Calculate cost estimate
     */
    abstract protected function calculateCost(int $tokens): float;
}
