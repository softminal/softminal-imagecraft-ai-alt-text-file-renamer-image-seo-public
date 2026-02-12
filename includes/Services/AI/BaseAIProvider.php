<?php

namespace SmartAltTextAi\Includes\Services\AI;

use SmartAltTextAi\Includes\Services\Settings;

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
        $maxLength = (int) Settings::get('satai_max_length', 150);

        $rules = "Rules: Stay under {$maxLength} characters. Never start with \"Image of\", \"Photo of\", \"Picture of\", or \"A photo\". Do not wrap in quotation marks. Return only the alt text, nothing else.";

        $basePrompts = [
            'professional' => "Write concise, professional alt text for this image. Describe the main subject, any key action, and relevant context. Focus on what matters most for someone who cannot see the image. {$rules}",
            'casual' => "Write a friendly, natural-sounding alt text for this image. Describe what is happening in plain, everyday language. {$rules}",
            'technical' => "Write precise, technical alt text for this image. Describe specific elements, spatial layout, and notable visual details. Be informative and exact. {$rules}",
            'seo' => "Write SEO-friendly alt text for this image. Weave relevant keywords naturally into an accurate description. Be descriptive and keyword-aware without stuffing. {$rules}",
            'woo_seo' => "Write SEO-optimized product alt text. You MUST include the exact product name. Describe visible features (color, material, shape) and camera angle if detectable (front, back, side, close-up). Use category keywords naturally. {$rules}",
            'woo_descriptive' => "Write descriptive product alt text for accessibility. You MUST include the exact product name. Describe visible physical features: color, material, texture, shape, size, and camera angle if detectable. Focus on what a visually impaired shopper needs to know. {$rules}",
            'woo_lifestyle' => "Write lifestyle product alt text. You MUST include the exact product name. Describe how the product is being used, the setting, and any people or activities shown. Mention the viewing angle if detectable. {$rules}",
        ];

        // Resolve prompt text
        if ($tone === 'custom' || $tone === 'woo_custom') {
            $settingKey = $tone === 'custom' ? 'satai_custom_prompt' : 'satai_woo_custom_prompt';
            $customPrompt = Settings::get($settingKey);

            if (!empty($customPrompt)) {
                $prompt = str_replace('{max_length}', (string) $maxLength, $customPrompt);
            } else {
                $tone = $tone === 'custom' ? 'professional' : 'woo_seo';
                $prompt = $basePrompts[$tone] ?? $basePrompts['professional'];
            }
        } else {
            $prompt = $basePrompts[$tone] ?? $basePrompts['professional'];
        }

        // Prepend context with explicit product name extraction
        if (!empty($context)) {
            // Extract product name from context string (format: "Product: Name | ...")
            $productName = null;
            if (preg_match('/^Product:\s*(.+?)(?:\s*\||$)/', trim($context), $matches)) {
                $productName = trim($matches[1]);
            }

            if ($productName) {
                $prompt = "This is a product called \"{$productName}\". Additional product info: " . trim($context) . "\n\nIMPORTANT: The alt text MUST contain the product name \"{$productName}\".\n\n" . $prompt;
            } else {
                $prompt = "[CONTEXT: " . trim($context) . "]\n\n" . $prompt;
            }
        }

        // Append SEO keyphrase instruction if present in context
        if (!empty($context) && preg_match('/SEO Focus Keyphrase:\s*(.+?)(?:\s*\||$)/', $context, $seoMatches)) {
            $keyphrase = trim($seoMatches[1]);
            $prompt .= "\n\nSEO INSTRUCTION: The target keyword for this page is \"{$keyphrase}\". You MUST include this exact keyword or a very close variation in the alt text. Weave it naturally into the description. The alt text must read as a complete, natural sentence — never cut off mid-thought.";
        }

        // Append language instruction for non-English
        $language = Settings::get('satai_language', 'en');
        if ($language !== 'en') {
            $languageName = $this->getLanguageName($language);
            $prompt .= " You MUST write the alt text in {$languageName}.";
        }

        return $prompt;
    }

    /**
     * Get human-readable language name from ISO code
     */
    protected function getLanguageName(string $code): string
    {
        $languages = [
            'en' => 'English',
            'es' => 'Spanish',
            'fr' => 'French',
            'de' => 'German',
            'it' => 'Italian',
            'pt' => 'Portuguese',
            'nl' => 'Dutch',
            'ru' => 'Russian',
            'ja' => 'Japanese',
            'zh' => 'Chinese',
            'ko' => 'Korean',
            'ar' => 'Arabic',
            'hi' => 'Hindi',
            'bn' => 'Bengali',
            'tr' => 'Turkish',
            'pl' => 'Polish',
            'sv' => 'Swedish',
            'da' => 'Danish',
            'fi' => 'Finnish',
            'no' => 'Norwegian',
            'th' => 'Thai',
            'vi' => 'Vietnamese',
            'id' => 'Indonesian',
            'ms' => 'Malay',
            'uk' => 'Ukrainian',
            'cs' => 'Czech',
            'el' => 'Greek',
            'he' => 'Hebrew',
            'ro' => 'Romanian',
            'hu' => 'Hungarian',
        ];

        return $languages[$code] ?? $code;
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
