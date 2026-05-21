<?php

namespace Softminal\ImageCraft\Services\AI;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WordPress 7.0 native AI Client provider.
 *
 * Delegates generation to wp_ai_client_prompt() instead of managing
 * API keys directly. Users configure their AI provider once in
 * WordPress Settings > Connectors, and ImageCraft uses it automatically.
 *
 * This provider is opt-in only. Existing BYOK installations are unaffected.
 *
 * Requires: WordPress 7.0+ with at least one AI provider plugin active.
 *
 * Uses WordPress\AiClient\Files\DTO\File — constructor auto-detects
 * data URI, URL, local path, or plain base64 input.
 */
class WP7Provider extends BaseAIProvider
{
    /**
     * WP7 manages API keys itself — we pass an empty string to satisfy
     * the BaseAIProvider constructor signature.
     */
    public function __construct(string $apiKey = '', int $timeout = 60, ?string $model = null)
    {
        parent::__construct('', $timeout, $model ?? static::getDefaultModel());
    }

    public static function getAvailableModels(): array
    {
        // WP7 uses whatever the admin configured in Settings > Connectors.
        // Model preferences are passed as hints via using_model_preference().
        return ['wp7-default'];
    }

    public static function getDefaultModel(): string
    {
        return 'wp7-default';
    }

    /**
     * Check if the WordPress 7.0 AI Client is available and has a
     * configured provider that supports text generation (vision).
     */
    public static function isAvailable(): bool
    {
        if (!function_exists('wp_ai_client_prompt')) {
            return false;
        }

        return (bool) wp_ai_client_prompt('test')->is_supported_for_text_generation();
    }

    /**
     * Generate alt text using the WordPress 7.0 native AI Client.
     *
     * Reuses BaseAIProvider::getPrompt() and getImageData() so all prompt
     * logic (tone, context, language, SEO keyphrase) works identically to
     * the direct provider implementations.
     */
    public function generateAltText(string $imageUrl, string $tone = 'professional', ?string $context = null): array
    {
        if (!function_exists('wp_ai_client_prompt')) {
            return [
                'success' => false,
                'error'   => __('WordPress 7.0 AI Client is not available', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            ];
        }

        try {
            $prompt    = $this->getPrompt($tone, $context);
            $imageData = $this->getImageData($imageUrl);

            // Build a data URI and pass it to the File constructor.
            // The constructor auto-detects data URI format — no factory method needed.
            // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Already base64-encoded by getImageData(); reconstructing data URI for WP AI Client
            $dataUri = 'data:' . $imageData['mime_type'] . ';base64,' . $imageData['base64'];
            $file    = new \WordPress\AiClient\Files\DTO\File($dataUri);

            $result = wp_ai_client_prompt()
                ->with_file($file)
                ->with_text($prompt)
                ->using_system_instruction('Return only plain text. No markdown, no bullet points, no special characters, no formatting of any kind.')
                ->using_max_tokens(300)
                ->generate_text_result();

            if (is_wp_error($result)) {
                return [
                    'success' => false,
                    'error'   => $result->get_error_message(),
                ];
            }

            // toText() extracts text from the first candidate's content channel parts.
            // Strip any leading markdown/formatting artifacts the model may have added
            // despite the system instruction (e.g. ":*", "- ", "• ", "** ").
            $altText = trim($result->toText());
            $altText = trim(preg_replace('/^[\s\*\:\-\•\>\#\_]+/', '', $altText));

            if (empty($altText)) {
                return [
                    'success' => false,
                    'error'   => __('WordPress AI Client returned an empty response', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
                ];
            }

            // getTotalTokens() = promptTokens + completionTokens
            $tokens = $result->getTokenUsage()->getTotalTokens();

            return [
                'success'  => true,
                'alt_text' => $altText,
                'tokens'   => $tokens,
                'cost'     => 0.0, // WP7 abstracts billing — cost not available per-request
            ];
        } catch (\Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log('ImageCraft WP7Provider: ' . $e->getMessage());
            }

            return [
                'success' => false,
                'error'   => $e->getMessage(),
            ];
        }
    }

    /**
     * Test the connection by checking WP7 AI Client availability.
     * Does not make an API call — uses the deterministic is_supported_* check.
     */
    public function testConnection(): array
    {
        if (!function_exists('wp_ai_client_prompt')) {
            return [
                'success' => false,
                'error'   => __('WordPress 7.0 is required for this feature', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            ];
        }

        $builder = wp_ai_client_prompt('test');

        if (!$builder->is_supported_for_text_generation()) {
            return [
                'success' => false,
                'error'   => __('No AI provider configured. Go to WordPress Settings → Connectors to set one up.', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            ];
        }

        return [
            'success' => true,
            'message' => __('WordPress AI Client is ready', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
        ];
    }

    /**
     * Not used — WP7 returns structured result objects, not raw arrays.
     */
    protected function extractAltText(array $response): string
    {
        return $response['text'] ?? '';
    }

    /**
     * Cost is not available per-request through the WP7 AI Client abstraction.
     */
    protected function calculateCost(int $tokens): float
    {
        return 0.0;
    }
}
