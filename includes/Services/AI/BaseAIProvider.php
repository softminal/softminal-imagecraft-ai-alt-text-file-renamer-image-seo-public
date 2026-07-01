<?php

namespace Softminal\ImageCraft\Services\AI;

use Softminal\ImageCraft\Services\Settings;

if (!defined('ABSPATH')) {
    exit;
}

abstract class BaseAIProvider
{
    protected string $apiKey;
    protected int $timeout;
    protected string $model;
    protected bool $titleOnlyMode = false;
    protected bool $linkTitleMode = false;
    protected bool $filenameMode = false;
    protected bool $metaMode = false;
    /** @var array|null Explicit field set for the next meta call (overrides settings). */
    protected ?array $metaFields = null;

    /** @var bool|null Cached result of isLocalDevelopment() — value never changes within a request. */
    private static ?bool $isLocalDevCache = null;

    public function __construct(string $apiKey, int $timeout = 60, ?string $model = null)
    {
        $this->apiKey = $apiKey;
        $this->timeout = $timeout;
        $this->model = $model ?? static::getDefaultModel();
    }

    /**
     * Get available models for this provider
     *
     * @return array Array of model identifiers
     */
    abstract public static function getAvailableModels(): array;

    /**
     * Get default model for this provider
     *
     * @return string Default model identifier
     */
    abstract public static function getDefaultModel(): string;

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
    /**
     * Enable title-only mode for the next generateAltText() call.
     * The AI will generate only a title, not alt text.
     */
    public function setTitleOnlyMode(bool $enabled = true): self
    {
        $this->titleOnlyMode = $enabled;
        return $this;
    }

    /**
     * Enable link title mode for the next generateAltText() call.
     * The AI will generate a title for the image's anchor link.
     */
    public function setLinkTitleMode(bool $enabled = true): self
    {
        $this->linkTitleMode = $enabled;
        return $this;
    }

    /**
     * Enable filename mode for the next generateAltText() call.
     * The AI will generate only a short SEO-friendly filename slug.
     */
    public function setFilenameMode(bool $enabled = true): self
    {
        $this->filenameMode = $enabled;
        return $this;
    }

    /**
     * Enable combined meta mode for the next generateAltText() call.
     * The AI will return a strict JSON object containing alt_text, title and
     * caption together (one request). The caller is responsible for decoding
     * the JSON (see GeneratorController::generateMetaForImage()).
     */
    public function setMetaMode(bool $enabled = true): self
    {
        $this->metaMode = $enabled;
        return $this;
    }

    /**
     * Set an explicit field list for the next meta call, overriding the
     * icais_meta_include_* settings (e.g. a per-batch picker selection).
     * Pass null to fall back to the settings defaults.
     *
     * @param array|null $fields Subset of ['alt', 'title', 'caption']
     */
    public function setMetaFields(?array $fields): self
    {
        $this->metaFields = $fields;
        return $this;
    }

    /**
     * Output token budget for the next generateAltText() call. Combined meta
     * mode asks for a JSON object containing alt_text + title + caption in one
     * response, which needs more headroom than a single alt text — otherwise
     * the response can be truncated mid-JSON and fail to parse.
     *
     * Must be called BEFORE getPrompt(), since getPrompt() resets $metaMode.
     */
    protected function getMaxOutputTokens(): int
    {
        return $this->metaMode ? 1024 : 300;
    }

    protected function getPrompt(string $tone, ?string $context = null): string
    {
        // Combined meta mode: generate alt text + title + caption as one JSON object
        if ($this->metaMode) {
            $this->metaMode = false;
            return $this->getMetaPrompt($tone, $context);
        }

        // Filename mode: generate a short SEO slug
        if ($this->filenameMode) {
            $this->filenameMode = false;
            return $this->getFilenamePrompt($context);
        }

        // Link title mode: generate a title for the image's anchor link
        if ($this->linkTitleMode) {
            $this->linkTitleMode = false;
            return $this->getLinkTitlePrompt($context);
        }

        // Title-only mode: use a dedicated title prompt
        if ($this->titleOnlyMode) {
            $this->titleOnlyMode = false;
            return $this->getTitleOnlyPrompt($context);
        }

        $maxLength = (int) Settings::get('icais_max_length', 125);

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
            $settingKey = $tone === 'custom' ? 'icais_custom_prompt' : 'icais_woo_custom_prompt';
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
            $productName = $this->extractProductName($context);

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
        $language = Settings::get('icais_language', 'en');
        if ($language !== 'en') {
            $languageName = $this->getLanguageName($language);
            $prompt .= " You MUST write in {$languageName}.";
        }

        return $prompt;
    }

    /**
     * Extract the product name from a context string.
     * Context format: "Product: Name | other details"
     *
     * @param string $context
     * @return string|null Product name, or null if not found
     */
    private function extractProductName(string $context): ?string
    {
        if (preg_match('/^Product:\s*(.+?)(?:\s*\||$)/', trim($context), $matches)) {
            return trim($matches[1]);
        }
        return null;
    }

    /**
     * Build a title-only prompt with tone support (mirrors getPrompt() for alt text).
     */
    protected function getTitleOnlyPrompt(?string $context = null): string
    {
        $maxLength = (int) Settings::get('icais_title_max_length', 30);
        $tone = Settings::get('icais_title_default_tone', 'seo');

        $rules = "Rules: Stay under {$maxLength} characters. Be concise and keyword-focused. No filler words. Do not wrap in quotation marks. Return only the title, nothing else.";

        $basePrompts = [
            'professional' => "Write a concise, professional title for this image. Focus on the main subject and key details. {$rules}",
            'casual'       => "Write a friendly, natural title for this image. Describe the main subject in plain language. {$rules}",
            'technical'    => "Write a precise, technical title for this image. Include specific elements and details. {$rules}",
            'seo'          => "Write an SEO-optimized title for this image. Use relevant keywords naturally. Be descriptive and keyword-focused. {$rules}",
            'woo_seo'      => "Write an SEO-optimized product title. You MUST include the product name. Add key features like color, material, or type. {$rules}",
            'woo_descriptive' => "Write a descriptive product title. You MUST include the product name and visible features. {$rules}",
            'woo_lifestyle' => "Write a lifestyle product title. You MUST include the product name and describe the scene or usage. {$rules}",
        ];

        // Custom prompt support
        if ($tone === 'custom') {
            $customPrompt = Settings::get('icais_title_custom_prompt');
            if (!empty($customPrompt)) {
                $prompt = str_replace('{max_length}', (string) $maxLength, $customPrompt);
            } else {
                $prompt = $basePrompts['seo'];
            }
        } else {
            $prompt = $basePrompts[$tone] ?? $basePrompts['seo'];
        }

        // Prepend context
        if (!empty($context)) {
            $productName = $this->extractProductName($context);

            if ($productName) {
                $prompt = "This is a product called \"{$productName}\". Additional product info: " . trim($context) . "\n\nIMPORTANT: The title MUST contain the product name \"{$productName}\".\n\n" . $prompt;
            } else {
                $prompt = "[CONTEXT: " . trim($context) . "]\n\n" . $prompt;
            }
        }

        // SEO keyphrase
        if (!empty($context) && preg_match('/SEO Focus Keyphrase:\s*(.+?)(?:\s*\||$)/', $context, $seoMatches)) {
            $keyphrase = trim($seoMatches[1]);
            $prompt .= "\n\nSEO INSTRUCTION: Include the keyword \"{$keyphrase}\" or a close variation in the title.";
        }

        // Language (shared setting)
        $language = Settings::get('icais_language', 'en');
        if ($language !== 'en') {
            $languageName = $this->getLanguageName($language);
            $prompt .= " You MUST write in {$languageName}.";
        }

        return $prompt;
    }

    /**
     * Build a combined "meta" prompt that asks the model to return alt text,
     * title and caption together as a single strict JSON object.
     *
     * Only the fields enabled via the icais_meta_include_* settings are
     * requested, but the model is always told to return all three keys
     * (disabled ones as empty strings) so the caller can parse uniformly.
     */
    protected function getMetaPrompt(string $tone, ?string $context = null): string
    {
        $altMax     = (int) Settings::get('icais_max_length', 125);
        $titleMax   = (int) Settings::get('icais_title_max_length', 30);
        $captionMax = (int) Settings::get('icais_caption_max_length', 150);

        // Explicit per-call field selection (e.g. bulk picker) overrides settings.
        if (is_array($this->metaFields)) {
            $includeAlt     = in_array('alt', $this->metaFields, true);
            $includeTitle   = in_array('title', $this->metaFields, true);
            $includeCaption = in_array('caption', $this->metaFields, true);
            $this->metaFields = null;
        } else {
            $includeAlt     = Settings::getBoolean('icais_meta_include_alt', true);
            $includeTitle   = Settings::getBoolean('icais_meta_include_title', true);
            $includeCaption = Settings::getBoolean('icais_meta_include_caption', true);
        }

        // Per-field instructions (only for enabled fields).
        $fieldLines = [];
        if ($includeAlt) {
            $fieldLines[] = "- \"alt_text\": Accessible alt text under {$altMax} characters. Describe the main subject, key action and relevant context. Never start with \"Image of\", \"Photo of\" or \"Picture of\".";
        }
        if ($includeTitle) {
            $fieldLines[] = "- \"title\": A concise, keyword-focused title under {$titleMax} characters. No filler words.";
        }
        if ($includeCaption) {
            $fieldLines[] = "- \"caption\": A natural, descriptive caption under {$captionMax} characters that could appear beneath the image on a page.";
        }

        // Defensive: if every field is somehow disabled, still ask for alt text
        // so the request is never empty.
        if (empty($fieldLines)) {
            $fieldLines[] = "- \"alt_text\": Accessible alt text under {$altMax} characters.";
        }

        $prompt  = "Analyze this image and generate SEO metadata for it.\n";
        $prompt .= implode("\n", $fieldLines) . "\n\n";
        $prompt .= "Return ONLY a single strict, minified JSON object — no markdown, no code fences, no commentary — with EXACTLY these keys: \"alt_text\", \"title\", \"caption\". ";
        $prompt .= "Set any field you were not asked to write to an empty string. Do not wrap field values in quotation marks beyond the JSON syntax.";

        // SEO tone hint reuse: bias all fields toward keywords when an SEO-ish tone is selected.
        if (in_array($tone, ['seo', 'woo_seo'], true)) {
            $prompt .= " Weave relevant keywords naturally into each field without keyword stuffing.";
        }

        // Prepend context with explicit product name extraction (mirrors getPrompt()).
        if (!empty($context)) {
            $productName = $this->extractProductName($context);

            if ($productName) {
                $prompt = "This is a product called \"{$productName}\". Additional product info: " . trim($context) . "\n\nIMPORTANT: Each generated field MUST contain the product name \"{$productName}\".\n\n" . $prompt;
            } else {
                $prompt = "[CONTEXT: " . trim($context) . "]\n\n" . $prompt;
            }
        }

        // Append SEO keyphrase instruction if present in context.
        if (!empty($context) && preg_match('/SEO Focus Keyphrase:\s*(.+?)(?:\s*\||$)/', $context, $seoMatches)) {
            $keyphrase = trim($seoMatches[1]);
            $prompt .= "\n\nSEO INSTRUCTION: The target keyword for this page is \"{$keyphrase}\". Include this exact keyword or a very close variation naturally in the alt_text and caption.";
        }

        // Append language instruction for non-English.
        $language = Settings::get('icais_language', 'en');
        if ($language !== 'en') {
            $languageName = $this->getLanguageName($language);
            $prompt .= " You MUST write every field in {$languageName}.";
        }

        return $prompt;
    }

    /**
     * Build a link title prompt.
     *
     * Link titles appear as tooltips on anchor-wrapped images. They should
     * describe what the user will find when clicking, not the image itself.
     */
    protected function getLinkTitlePrompt(?string $context = null): string
    {
        $maxLength = (int) Settings::get('icais_title_max_length', 30);
        $tone = Settings::get('icais_title_default_tone', 'seo');

        $rules = "Rules: Stay under {$maxLength} characters. Be action-oriented and concise. Do not wrap in quotation marks. Return only the link title, nothing else.";

        $basePrompts = [
            'professional' => "Write a concise, professional link title for this image link. Describe what the user will find or learn when they click. Focus on the destination's value. {$rules}",
            'casual'       => "Write a friendly link title for this image link. Tell the user what they will see or get when they click. Keep it inviting. {$rules}",
            'technical'    => "Write a precise link title for this image link. Indicate the destination content or action that clicking triggers. {$rules}",
            'seo'          => "Write an SEO-optimized link title for this image link. Use relevant keywords naturally while describing the click destination. {$rules}",
            'woo_seo'      => "Write an SEO-optimized product link title. You MUST include the product name. Describe what clicking reveals (e.g. product details, purchase page). {$rules}",
            'woo_descriptive' => "Write a descriptive product link title. You MUST include the product name. Describe what the user will find when clicking. {$rules}",
            'woo_lifestyle' => "Write a lifestyle product link title. You MUST include the product name. Describe the experience or content behind the link. {$rules}",
        ];

        // Custom prompt support
        if ($tone === 'custom') {
            $customPrompt = Settings::get('icais_title_custom_prompt');
            if (!empty($customPrompt)) {
                $prompt = str_replace('{max_length}', (string) $maxLength, $customPrompt);
            } else {
                $prompt = $basePrompts['seo'];
            }
        } else {
            $prompt = $basePrompts[$tone] ?? $basePrompts['seo'];
        }

        // Prepend context
        if (!empty($context)) {
            $productName = $this->extractProductName($context);

            if ($productName) {
                $prompt = "This is a product called \"{$productName}\". Additional product info: " . trim($context) . "\n\nIMPORTANT: The link title MUST contain the product name \"{$productName}\".\n\n" . $prompt;
            } else {
                $prompt = "[CONTEXT: " . trim($context) . "]\n\n" . $prompt;
            }
        }

        // Language
        $language = Settings::get('icais_language', 'en');
        if ($language !== 'en') {
            $languageName = $this->getLanguageName($language);
            $prompt .= " You MUST write in {$languageName}.";
        }

        return $prompt;
    }

    /**
     * Build a filename prompt. AI generates a short SEO-friendly slug.
     */
    protected function getFilenamePrompt(?string $context = null): string
    {
        $maxLength = (int) Settings::get('icais_rename_max_length', 120);

        $prompt = "Generate a short, SEO-friendly filename for this image. Rules: Maximum {$maxLength} characters. Use only lowercase letters, numbers, and dashes. No spaces, underscores, or special characters. Be keyword-focused and descriptive. No filler words like 'a', 'the', 'in', 'of'. Do not include file extension. Return only the filename slug, nothing else.";

        if (!empty($context)) {
            $productName = $this->extractProductName($context);

            if ($productName) {
                $prompt = "This is a product called \"{$productName}\". " . trim($context) . "\n\nThe filename MUST contain the product name.\n\n" . $prompt;
            } else {
                $prompt = "[CONTEXT: " . trim($context) . "]\n\n" . $prompt;
            }
        }

        $language = Settings::get('icais_language', 'en');
        if ($language !== 'en') {
            $languageName = $this->getLanguageName($language);
            $prompt .= " Use {$languageName} keywords transliterated to ASCII.";
        }

        return $prompt;
    }

    /**
     * Single source of truth for all supported languages.
     *
     * Every other consumer (getSupportedLanguages, getLanguageName,
     * SettingsController validation, frontend dropdown) derives from this map.
     *
     * @return array<string, string> ISO code => English name
     */
    public static function getLanguageMap(): array
    {
        return [
            'af' => 'Afrikaans',
            'am' => 'Amharic',
            'ar' => 'Arabic',
            'az' => 'Azerbaijani',
            'bg' => 'Bulgarian',
            'bn' => 'Bengali',
            'bs' => 'Bosnian',
            'ca' => 'Catalan',
            'cs' => 'Czech',
            'cy' => 'Welsh',
            'da' => 'Danish',
            'de' => 'German',
            'el' => 'Greek',
            'en' => 'English',
            'es' => 'Spanish',
            'et' => 'Estonian',
            'fa' => 'Persian',
            'fi' => 'Finnish',
            'fil' => 'Filipino',
            'fr' => 'French',
            'ga' => 'Irish',
            'gl' => 'Galician',
            'gu' => 'Gujarati',
            'he' => 'Hebrew',
            'hi' => 'Hindi',
            'hr' => 'Croatian',
            'hu' => 'Hungarian',
            'hy' => 'Armenian',
            'id' => 'Indonesian',
            'is' => 'Icelandic',
            'it' => 'Italian',
            'ja' => 'Japanese',
            'ka' => 'Georgian',
            'kk' => 'Kazakh',
            'km' => 'Khmer',
            'kn' => 'Kannada',
            'ko' => 'Korean',
            'lt' => 'Lithuanian',
            'lv' => 'Latvian',
            'mk' => 'Macedonian',
            'mn' => 'Mongolian',
            'mr' => 'Marathi',
            'ms' => 'Malay',
            'ne' => 'Nepali',
            'nl' => 'Dutch',
            'no' => 'Norwegian',
            'pa' => 'Punjabi',
            'pl' => 'Polish',
            'ps' => 'Pashto',
            'pt' => 'Portuguese',
            'ro' => 'Romanian',
            'ru' => 'Russian',
            'si' => 'Sinhala',
            'sk' => 'Slovak',
            'sl' => 'Slovenian',
            'sq' => 'Albanian',
            'sr' => 'Serbian',
            'sv' => 'Swedish',
            'sw' => 'Swahili',
            'ta' => 'Tamil',
            'te' => 'Telugu',
            'th' => 'Thai',
            'tr' => 'Turkish',
            'uk' => 'Ukrainian',
            'ur' => 'Urdu',
            'uz' => 'Uzbek',
            'vi' => 'Vietnamese',
            'zh' => 'Chinese',
            'zu' => 'Zulu',
        ];
    }

    /**
     * Get all supported language codes.
     *
     * @return string[]
     */
    public static function getSupportedLanguages(): array
    {
        return array_keys(self::getLanguageMap());
    }

    /**
     * Get human-readable language name from ISO code
     */
    protected function getLanguageName(string $code): string
    {
        $languages = self::getLanguageMap();

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
     * Calculate approximate cost estimate.
     *
     * Uses a flat rate per token regardless of which model is selected.
     * This is intentionally approximate for dashboard display purposes.
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
        // Initialize WP_Filesystem early for all filesystem operations
        global $wp_filesystem;
        if (empty($wp_filesystem)) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }

        // Try to get local file path if URL is from this WordPress site.
        // First uses getLocalFilePath(), then falls back to direct ABSPATH+urlpath
        // computation which works even when site_url / upload_url don't match the
        // image URL (http/https mismatch, CDN, domain migration, etc.).
        $localPath = $this->getLocalFilePath($imageUrl);

        if (!$localPath) {
            $urlPath = wp_parse_url($imageUrl, PHP_URL_PATH);
            if ($urlPath && strpos($urlPath, '/wp-content/uploads/') !== false) {
                $candidate = rtrim(ABSPATH, '/') . $urlPath;
                // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_exists -- native check needed; WP_Filesystem may use FTP transport
                if (file_exists($candidate)) {
                    $localPath = $candidate;
                }
            }
        }

        if ($localPath) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local image; WP_Filesystem transport may be FTP which cannot read local paths
            $imageContent = file_get_contents($localPath);
            if ($imageContent === false) {
                throw new \Exception('Failed to read local image file');
            }
        }

        if (!$localPath) {
            // Validate URL before fetching to prevent SSRF
            $this->validateImageUrl($imageUrl);

            // Minimise the DNS-rebinding TOCTOU window by re-validating the resolved
            // IP immediately before the HTTP library makes its own DNS lookup.
            // Priority PHP_INT_MAX ensures this runs as late as possible in the filter chain.
            $ssrfSiteHost = wp_parse_url(site_url(), PHP_URL_HOST);
            $ssrfCheckFilter = static function ($preempt, $args, $requestUrl) use ($ssrfSiteHost) {
                if ($preempt !== false) {
                    return $preempt;
                }
                $host = wp_parse_url($requestUrl, PHP_URL_HOST);
                if (!$host || $host === $ssrfSiteHost) {
                    return $preempt;
                }
                $ip = gethostbyname($host);
                if ($ip === $host) {
                    return new \WP_Error('ssrf_blocked', 'Could not resolve image URL hostname');
                }
                if (false === filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return new \WP_Error('ssrf_blocked', 'Image URL resolves to a disallowed IP address');
                }
                return $preempt;
            };
            add_filter('pre_http_request', $ssrfCheckFilter, PHP_INT_MAX, 3);

            // Use WordPress HTTP API for remote URLs
            $response = wp_remote_get($imageUrl, [
                'timeout' => $this->timeout,
                'sslverify' => true,
            ]);

            remove_filter('pre_http_request', $ssrfCheckFilter, PHP_INT_MAX);

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

        // Reject images larger than 20 MB to prevent memory exhaustion
        $maxBytes = 20 * 1024 * 1024;
        if (strlen($imageContent) > $maxBytes) {
            throw new \Exception('Image file is too large (max 20 MB)');
        }

        // Validate actual image content via magic bytes (defense-in-depth)
        $detectedMime = $this->detectMimeFromContent($imageContent);
        if ($detectedMime === null) {
            throw new \Exception('File does not appear to be a supported image format');
        }

        // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Required for encoding image data for AI vision API requests
        $base64 = base64_encode($imageContent);
        $mimeType = $detectedMime;

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
        global $wp_filesystem;
        if (empty($wp_filesystem)) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }

        $uploadDir = wp_upload_dir();
        $uploadUrl = $uploadDir['baseurl'];
        $uploadPath = $uploadDir['basedir'];

        // Normalize schemes to handle http vs https mismatches (common on migrated/staging sites)
        $normalizedUrl       = preg_replace('#^https?://#', '//', $url);
        $normalizedUploadUrl = preg_replace('#^https?://#', '//', $uploadUrl);

        // Check if URL is from this WordPress site's upload directory
        if (strpos($normalizedUrl, $normalizedUploadUrl) === 0) {
            // Extract relative path from upload URL
            $relativePath = substr($normalizedUrl, strlen($normalizedUploadUrl));
            $relativePath = ltrim($relativePath, '/');
            $localPath = trailingslashit($uploadPath) . $relativePath;

            if ($wp_filesystem->exists($localPath)) {
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
                if ($wp_filesystem->exists($localPath)) {
                    return $localPath;
                }
            }
        }

        // Final fallback: resolve using URL path only, ignoring domain entirely.
        // Handles CDN URLs, http/https mismatches, and domain changes after migration.
        $urlPath = wp_parse_url($url, PHP_URL_PATH);
        if ($urlPath && strpos($urlPath, '/wp-content/uploads/') !== false) {
            $localPath = rtrim(ABSPATH, '/') . $urlPath;
            if ($wp_filesystem->exists($localPath)) {
                return $localPath;
            }
        }

        return null;
    }

    /**
     * Check if running in local development environment.
     * Result is cached in a static property — site URL never changes within a request.
     *
     * @return bool
     */
    protected function isLocalDevelopment(): bool
    {
        if (self::$isLocalDevCache !== null) {
            return self::$isLocalDevCache;
        }

        $siteUrl = site_url();
        $homeUrl = home_url();

        self::$isLocalDevCache = (
            strpos($siteUrl, 'localhost') !== false ||
            strpos($siteUrl, '127.0.0.1') !== false ||
            strpos($siteUrl, '.local') !== false ||
            strpos($siteUrl, '.test') !== false ||
            strpos($homeUrl, 'localhost') !== false ||
            strpos($homeUrl, '127.0.0.1') !== false ||
            strpos($homeUrl, '.local') !== false ||
            strpos($homeUrl, '.test') !== false
        );

        return self::$isLocalDevCache;
    }

    /**
     * Validate a remote image URL to prevent SSRF attacks.
     *
     * Blocks requests to private/reserved IP ranges (RFC 1918, link-local, loopback)
     * and non-HTTP(S) schemes.
     *
     * @param string $url The URL to validate.
     * @throws \Exception If the URL is unsafe.
     */
    protected function validateImageUrl(string $url): void
    {
        $scheme = wp_parse_url($url, PHP_URL_SCHEME);
        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new \Exception('Invalid image URL scheme');
        }

        $host = wp_parse_url($url, PHP_URL_HOST);
        if (empty($host)) {
            throw new \Exception('Invalid image URL: missing host');
        }

        // Allow same-site URLs (they should have been handled as local files above,
        // but fall through here if the local path didn't resolve)
        $siteHost = wp_parse_url(site_url(), PHP_URL_HOST);
        if ($host === $siteHost) {
            return;
        }

        // Resolve the hostname to an IP and reject private/reserved ranges
        $ip = gethostbyname($host);
        if ($ip === $host) {
            throw new \Exception('Could not resolve image URL hostname');
        }

        // FILTER_FLAG_NO_PRIV_RANGE  → blocks 10.x, 172.16-31.x, 192.168.x
        // FILTER_FLAG_NO_RES_RANGE   → blocks 0.0.0.0, 127.x, 169.254.x, 240.x+
        if (false === filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            throw new \Exception('Image URL resolves to a disallowed IP address');
        }
    }

    /**
     * Detect MIME type from file content using magic bytes.
     *
     * @param string $content Raw file content
     * @return string|null Detected MIME type, or null if not a supported image
     */
    protected function detectMimeFromContent(string $content): ?string
    {
        if (strlen($content) < 4) {
            return null;
        }

        // JPEG: starts with FF D8 FF
        if (strncmp($content, "\xFF\xD8\xFF", 3) === 0) {
            return 'image/jpeg';
        }

        // PNG: starts with 89 50 4E 47 0D 0A 1A 0A
        if (strncmp($content, "\x89PNG\r\n\x1A\n", 8) === 0) {
            return 'image/png';
        }

        // GIF: starts with GIF87a or GIF89a
        if (strncmp($content, 'GIF87a', 6) === 0 || strncmp($content, 'GIF89a', 6) === 0) {
            return 'image/gif';
        }

        // WebP: RIFF....WEBP
        if (strlen($content) >= 12 && strncmp($content, 'RIFF', 4) === 0 && substr($content, 8, 4) === 'WEBP') {
            return 'image/webp';
        }

        return null;
    }

    /**
     * Get MIME type from URL extension (fallback only).
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
        ];

        return $mimeTypes[$extension] ?? 'image/jpeg';
    }
}
