<?php

namespace SmartAltTextAi\Includes\Services\AI;

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
     *
     * @param string $imageUrl Image URL
     * @param string $tone Tone of the alt text
     * @param string|null $context Optional context for WooCommerce products
     * @return array
     */
    abstract public function generateAltText(string $imageUrl, string $tone = 'professional', ?string $context = null): array;

    /**
     * Test API connection
     */
    abstract public function testConnection(): array;

    /**
     * Get prompt based on tone with optional context
     *
     * @param string $tone Tone of the alt text
     * @param string|null $context Optional context information
     * @return string
     */
    protected function getPrompt(string $tone, ?string $context = null): string
    {
        $basePrompts = [
            'professional' => "Analyze this image and generate a concise, professional alt text (max 125 characters) for accessibility. Describe the main subject, action, and relevant context. Do not start with \"Image of\" or \"Photo of\". Focus on what's important for someone who cannot see the image. Return only the alt text, nothing else.",
            'casual' => "Look at this image and write a friendly, conversational alt text (max 125 characters) that describes what's happening. Keep it natural and easy to understand. Don't start with \"Image of\" or \"Picture of\". Return only the alt text, nothing else.",
            'technical' => "Analyze this image and create a detailed, technical alt text (max 125 characters) describing specific elements, composition, and technical aspects. Be precise and informative. Do not use phrases like \"Image of\". Return only the alt text, nothing else.",
            'seo' => "Generate SEO-friendly alt text (max 125 characters) for this image. Include relevant keywords naturally while accurately describing the image. Make it descriptive and keyword-rich without keyword stuffing. Do not start with \"Image of\". Return only the alt text, nothing else."
        ];

        $prompt = $basePrompts[$tone] ?? $basePrompts['professional'];

        if (!empty($context)) {
            $prompt = "[CONTEXT: " . trim($context) . "]\n\n" . $prompt;
        }

        return $prompt;
    }

    /**
     * Make HTTP request
     */
    protected function makeRequest(string $url, array $data, array $headers = []): array
    {
        // Normalize headers to associative array for wp_remote_post()
        $normalizedHeaders = ['Content-Type' => 'application/json'];

        foreach ($headers as $key => $value) {
            if (is_int($key) && is_string($value)) {
                $parts = explode(':', $value, 2);
                $normalizedHeaders[trim($parts[0])] = isset($parts[1]) ? trim($parts[1]) : '';
                continue;
            }
            if (is_string($key)) {
                $normalizedHeaders[$key] = $value;
            }
        }

        $response = wp_remote_post($url, [
            'headers' => $normalizedHeaders,
            'body' => wp_json_encode($data),
            'timeout' => $this->timeout,
            'sslverify' => true,
        ]);

        if (is_wp_error($response)) {
            throw new \Exception('Request failed: ' . esc_html($response->get_error_message()));
        }

        $httpCode = (int) wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        // Decode once so we can show better error messages
        $decoded = json_decode($body, true);

        if ($httpCode >= 400) {
            $errorMessage =
                $decoded['error']['message']
                ?? $decoded['message']
                ?? "HTTP Error {$httpCode}";

            throw new \Exception(esc_html($errorMessage));
        }

        return is_array($decoded) ? $decoded : [];
    }


    /**
     * Extract alt text from response
     */
    abstract protected function extractAltText(array $response): string;

    /**
     * Calculate cost estimate
     */
    abstract protected function calculateCost(int $tokens): float;

    /**
     * Get image data from URL (handles local files and remote URLs)
     *
     * @param string $imageUrl Image URL
     * @return array Array with 'base64' and 'mime_type' keys
     * @throws \Exception If image cannot be fetched
     */
    protected function getImageData(string $imageUrl): array
    {
        // Try to get local file path if URL is from this WordPress site
        $localPath = $this->getLocalFilePath($imageUrl);

        if ($localPath && file_exists($localPath)) {
            // Read from local file system (no SSL issues)
            $imageContent = file_get_contents($localPath);
            if ($imageContent === false) {
                throw new \Exception('Failed to read local image file');
            }
        } else {
            // Use WordPress HTTP API for remote URLs (handles SSL better)
            $response = wp_remote_get($imageUrl, [
                'timeout' => $this->timeout,
                'sslverify' => !$this->isLocalDevelopment(),
            ]);

            if (is_wp_error($response)) {
                throw new \Exception('Failed to fetch image: ' . esc_html($response->get_error_message()));
            }

            $httpCode = wp_remote_retrieve_response_code($response);
            if ($httpCode !== 200) {
                throw new \Exception('Failed to fetch image: HTTP ' . esc_html((string) $httpCode));
            }

            $imageContent = wp_remote_retrieve_body($response);
            if (empty($imageContent)) {
                throw new \Exception('Failed to fetch image: Empty response');
            }
        }

        $base64 = base64_encode($imageContent);
        $mimeType = $this->getMimeType($imageUrl);

        return [
            'base64' => $base64,
            'mime_type' => $mimeType
        ];
    }

    /**
     * Get local file path from WordPress upload URL
     *
     * @param string $url Image URL
     * @return string|null Local file path or null if not a local URL
     */
    protected function getLocalFilePath(string $url): ?string
    {
        $uploadDir = wp_upload_dir();
        $uploadUrl = $uploadDir['baseurl'];
        $uploadPath = $uploadDir['basedir'];

        // Check if URL is from this WordPress site's upload directory
        if (strpos($url, $uploadUrl) === 0) {
            // Extract relative path from upload URL
            $relativePath = substr($url, strlen($uploadUrl));
            $relativePath = ltrim($relativePath, '/');
            $localPath = trailingslashit($uploadPath) . $relativePath;

            if (file_exists($localPath)) {
                return $localPath;
            }
        }

        // Check if URL is from this WordPress site (by hostname)
        $urlHost = wp_parse_url($url, PHP_URL_HOST);
        $siteHost = wp_parse_url(site_url(), PHP_URL_HOST);

        if ($urlHost && ($urlHost === $siteHost ||
            strpos($urlHost, '.local') !== false ||
            strpos($urlHost, '.test') !== false ||
            $urlHost === 'localhost' ||
            $urlHost === '127.0.0.1')) {

            // Try to extract path relative to WordPress root
            $urlPath = wp_parse_url($url, PHP_URL_PATH);
            if ($urlPath && strpos($urlPath, '/wp-content/') === 0) {
                $localPath = ABSPATH . ltrim($urlPath, '/');
                if (file_exists($localPath)) {
                    return $localPath;
                }
            }
        }

        return null;
    }

    /**
     * Check if running in local development environment
     *
     * @return bool
     */
    protected function isLocalDevelopment(): bool
    {
        // Check common local development indicators
        $siteUrl = site_url();
        $homeUrl = home_url();

        return (
            strpos($siteUrl, 'localhost') !== false ||
            strpos($siteUrl, '127.0.0.1') !== false ||
            strpos($siteUrl, '.local') !== false ||
            strpos($siteUrl, '.test') !== false ||
            strpos($homeUrl, 'localhost') !== false ||
            strpos($homeUrl, '127.0.0.1') !== false ||
            strpos($homeUrl, '.local') !== false ||
            strpos($homeUrl, '.test') !== false
        );
    }

    /**
     * Get MIME type from URL
     *
     * @param string $url Image URL
     * @return string MIME type
     */
    protected function getMimeType(string $url): string
    {
        $extension = strtolower(pathinfo(wp_parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        $mimeTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml'
        ];

        return $mimeTypes[$extension] ?? 'image/jpeg';
    }
}
