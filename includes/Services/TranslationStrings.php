<?php

namespace Softminal\ImageCraft\Services;

if (!defined('ABSPATH')) {
    exit;
}

class TranslationStrings {

    public static function get(): array {
        return array_merge(
            self::navbar(),
            self::dashboard(),
            self::scanner(),
            self::settings(),
            self::setupWizard(),
            self::batch(),
            self::woocommerce(),
            self::seoIntegration(),
            self::help()
        );
    }

    private static function navbar(): array {
        return [
            'Dashboard' => __('Dashboard', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Scanner' => __('Scanner', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'WooCommerce' => __('WooCommerce', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Settings' => __('Settings', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Loading...' => __('Loading...', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
        ];
    }

    private static function dashboard(): array {
        return [
            // Stats
            'Total Images' => __('Total Images', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Missing Alt Text' => __('Missing Alt Text', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'This Month' => __('This Month', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Success Rate' => __('Success Rate', 'imagecraft-ai-alt-text-file-renamer-image-seo'),

            // SEO Audit
            'Image SEO Audit' => __('Image SEO Audit', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Scanning...' => __('Scanning...', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Refresh' => __('Refresh', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Scanning media library...' => __('Scanning media library...', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Excellent' => __('Excellent', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Good' => __('Good', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Needs Work' => __('Needs Work', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Poor' => __('Poor', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            /* translators: %1$s: number of optimized images, %2$s: total number of images */
            '%1$s of %2$s images are fully optimized' => __('%1$s of %2$s images are fully optimized', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Generic / Placeholder' => __('Generic / Placeholder', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Too Long' => __('Too Long', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Generic alt text found:' => __('Generic alt text found:', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            /* translators: %s: maximum character count */
            'Alt text exceeding %s characters:' => __('Alt text exceeding %s characters:', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            /* translators: %s: character count */
            '%s chars' => __('%s chars', 'imagecraft-ai-alt-text-file-renamer-image-seo'),

            // API Status
            'API Status' => __('API Status', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            /* translators: %s: AI provider name */
            'Connected to %s' => __('Connected to %s', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Not connected' => __('Not connected', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Configure API' => __('Configure API', 'imagecraft-ai-alt-text-file-renamer-image-seo'),

            // Quick Actions
            'Quick Actions' => __('Quick Actions', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Scan Media Library' => __('Scan Media Library', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'WooCommerce Products' => __('WooCommerce Products', 'imagecraft-ai-alt-text-file-renamer-image-seo'),

            // Recent Activity
            'Recent Activity' => __('Recent Activity', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'No recent activity' => __('No recent activity', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
        ];
    }

    private static function scanner(): array {
        return [
            // Filters & Actions
            'Media Library Scanner' => __('Media Library Scanner', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Has Alt Text' => __('Has Alt Text', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'All Images' => __('All Images', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            /* translators: %s: number of selected images */
            'Generate for %s Selected' => __('Generate for %s Selected', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            /* translators: %s: number of selected images */
            'Rename %s Selected' => __('Rename %s Selected', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Advanced Filters & Bulk Select' => __('Advanced Filters & Bulk Select', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Upload Date From' => __('Upload Date From', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Upload Date To' => __('Upload Date To', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Min File Size (KB)' => __('Min File Size (KB)', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'e.g. 500' => __('e.g. 500', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Apply' => __('Apply', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Clear Filters' => __('Clear Filters', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Quick Bulk Select (across all pages)' => __('Quick Bulk Select (across all pages)', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Selecting...' => __('Selecting...', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'All Missing Alt Text' => __('All Missing Alt Text', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'All Generic / Placeholder' => __('All Generic / Placeholder', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'All Too Long' => __('All Too Long', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            /* translators: %s: minimum file size in kilobytes */
            'All Larger Than %s KB' => __('All Larger Than %s KB', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            /* translators: %s: number of selected images */
            'Clear Selection (%s)' => __('Clear Selection (%s)', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            /* translators: %s: number of selected images */
            '%s images selected across all pages' => __('%s images selected across all pages', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Loading images...' => __('Loading images...', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Select All' => __('Select All', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Has alt' => __('Has alt', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Missing' => __('Missing', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'No alt text' => __('No alt text', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Rename' => __('Rename', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Renaming...' => __('Renaming...', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Previous' => __('Previous', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            /* translators: %1$s: current page number, %2$s: total number of pages */
            'Page %1$s of %2$s' => __('Page %1$s of %2$s', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Next' => __('Next', 'imagecraft-ai-alt-text-file-renamer-image-seo'),

            // Image Modal
            'Attachment details' => __('Attachment details', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Close' => __('Close', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'File name' => __('File name', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Rename to SEO' => __('Rename to SEO', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Alt text' => __('Alt text', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Generating...' => __('Generating...', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Generate with AI' => __('Generate with AI', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Describe the image for accessibility...' => __('Describe the image for accessibility...', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Alternative text helps describe the image for screen readers and SEO.' => __('Alternative text helps describe the image for screen readers and SEO.', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Open in WordPress Media Library' => __('Open in WordPress Media Library', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Cancel' => __('Cancel', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Saving...' => __('Saving...', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Update' => __('Update', 'imagecraft-ai-alt-text-file-renamer-image-seo'),

            // Notifications
            'Failed to load images' => __('Failed to load images', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'No images match this filter' => __('No images match this filter', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            /* translators: %s: number of selected images */
            '%s images selected' => __('%s images selected', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Failed to select images' => __('Failed to select images', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Maximum 500 images per batch job. Please reduce your selection.' => __('Maximum 500 images per batch job. Please reduce your selection.', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            /* translators: %s: number of images in batch */
            'Batch job started for %s images' => __('Batch job started for %s images', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Failed to start batch job' => __('Failed to start batch job', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Alt text generated. Review and click Update to save.' => __('Alt text generated. Review and click Update to save.', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Failed to generate alt text' => __('Failed to generate alt text', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Alt text updated.' => __('Alt text updated.', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Failed to update alt text' => __('Failed to update alt text', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            /* translators: %s: new filename */
            'Renamed to: %s' => __('Renamed to: %s', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Failed to rename file' => __('Failed to rename file', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Selected images must have alt text to rename' => __('Selected images must have alt text to rename', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            /* translators: %s: number of renamed files */
            'Renamed %s files' => __('Renamed %s files', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            /* translators: %1$s: number of successfully renamed files, %2$s: total number of files */
            'Renamed %1$s/%2$s files' => __('Renamed %1$s/%2$s files', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Bulk rename failed' => __('Bulk rename failed', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
        ];
    }

    private static function settings(): array {
        return [
            // API Configuration
            'API Configuration' => __('API Configuration', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Provider' => __('Provider', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Anthropic Claude' => __('Anthropic Claude', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'OpenAI GPT-4' => __('OpenAI GPT-4', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Google Gemini' => __('Google Gemini', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Mistral AI (Pixtral)' => __('Mistral AI (Pixtral)', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'OpenRouter' => __('OpenRouter', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'OpenRouter Model' => __('OpenRouter Model', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'API Key' => __('API Key', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Enter API key' => __('Enter API key', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Save API Key' => __('Save API Key', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Status:' => __('Status:', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Active' => __('Active', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Inactive' => __('Inactive', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Provider:' => __('Provider:', 'imagecraft-ai-alt-text-file-renamer-image-seo'),

            // Generation
            'Generation Settings' => __('Generation Settings', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Default Tone' => __('Default Tone', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Professional' => __('Professional', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Casual' => __('Casual', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Technical' => __('Technical', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'SEO-Optimized' => __('SEO-Optimized', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Custom Prompt' => __('Custom Prompt', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            "e.g. Generate alt text (max {max_length} characters) describing the subject, colors, and setting. Do not start with 'Image of'. Return only the alt text." => __("e.g. Generate alt text (max {max_length} characters) describing the subject, colors, and setting. Do not start with 'Image of'. Return only the alt text.", 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Use {max_length} as a placeholder for your max character setting. Product context is added automatically for WooCommerce images.' => __('Use {max_length} as a placeholder for your max character setting. Product context is added automatically for WooCommerce images.', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Max Character Length' => __('Max Character Length', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Language' => __('Language', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Afrikaans' => __('Afrikaans', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Albanian' => __('Albanian', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Amharic' => __('Amharic', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Arabic' => __('Arabic', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Armenian' => __('Armenian', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Azerbaijani' => __('Azerbaijani', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Bengali' => __('Bengali', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Bosnian' => __('Bosnian', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Bulgarian' => __('Bulgarian', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Catalan' => __('Catalan', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Chinese' => __('Chinese', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Croatian' => __('Croatian', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Czech' => __('Czech', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Danish' => __('Danish', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Dutch' => __('Dutch', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'English' => __('English', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Estonian' => __('Estonian', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Filipino' => __('Filipino', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Finnish' => __('Finnish', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'French' => __('French', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Galician' => __('Galician', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Georgian' => __('Georgian', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'German' => __('German', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Greek' => __('Greek', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Gujarati' => __('Gujarati', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Hebrew' => __('Hebrew', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Hindi' => __('Hindi', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Hungarian' => __('Hungarian', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Icelandic' => __('Icelandic', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Indonesian' => __('Indonesian', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Irish' => __('Irish', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Italian' => __('Italian', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Japanese' => __('Japanese', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Kannada' => __('Kannada', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Kazakh' => __('Kazakh', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Khmer' => __('Khmer', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Korean' => __('Korean', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Latvian' => __('Latvian', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Lithuanian' => __('Lithuanian', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Macedonian' => __('Macedonian', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Malay' => __('Malay', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Marathi' => __('Marathi', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Mongolian' => __('Mongolian', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Nepali' => __('Nepali', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Norwegian' => __('Norwegian', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Pashto' => __('Pashto', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Persian' => __('Persian', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Polish' => __('Polish', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Portuguese' => __('Portuguese', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Punjabi' => __('Punjabi', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Romanian' => __('Romanian', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Russian' => __('Russian', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Serbian' => __('Serbian', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Sinhala' => __('Sinhala', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Slovak' => __('Slovak', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Slovenian' => __('Slovenian', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Spanish' => __('Spanish', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Swahili' => __('Swahili', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Swedish' => __('Swedish', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Tamil' => __('Tamil', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Telugu' => __('Telugu', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Thai' => __('Thai', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Turkish' => __('Turkish', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Ukrainian' => __('Ukrainian', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Urdu' => __('Urdu', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Uzbek' => __('Uzbek', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Vietnamese' => __('Vietnamese', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Welsh' => __('Welsh', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Zulu' => __('Zulu', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Alt text will be generated in the selected language' => __('Alt text will be generated in the selected language', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Auto-generate on upload' => __('Auto-generate on upload', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Preview before accepting' => __('Preview before accepting', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Skip images with existing alt text' => __('Skip images with existing alt text', 'imagecraft-ai-alt-text-file-renamer-image-seo'),

            // File Renaming
            'File Renaming' => __('File Renaming', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Auto-rename files on upload' => __('Auto-rename files on upload', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Rename uploaded files to SEO-friendly names based on generated alt text' => __('Rename uploaded files to SEO-friendly names based on generated alt text', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Max Filename Length' => __('Max Filename Length', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Characters in filename (default: 120)' => __('Characters in filename (default: 120)', 'imagecraft-ai-alt-text-file-renamer-image-seo'),

            // Advanced
            'Advanced Settings' => __('Advanced Settings', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Batch Size' => __('Batch Size', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Number of images to process per batch' => __('Number of images to process per batch', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Request Timeout (seconds)' => __('Request Timeout (seconds)', 'imagecraft-ai-alt-text-file-renamer-image-seo'),

            // WooCommerce
            'WooCommerce Settings' => __('WooCommerce Settings', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Configure alt text generation for WooCommerce products' => __('Configure alt text generation for WooCommerce products', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Auto-generate on product upload' => __('Auto-generate on product upload', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Automatically generate alt text when adding product images' => __('Automatically generate alt text when adding product images', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Include product information' => __('Include product information', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Use product name, categories, and SKU in alt text generation' => __('Use product name, categories, and SKU in alt text generation', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Default Tone for Products' => __('Default Tone for Products', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'WooCommerce Optimized' => __('WooCommerce Optimized', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Product SEO (Recommended)' => __('Product SEO (Recommended)', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Product Descriptive (Accessibility)' => __('Product Descriptive (Accessibility)', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Product Lifestyle (In-Use Photos)' => __('Product Lifestyle (In-Use Photos)', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'General' => __('General', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Custom WooCommerce Prompt' => __('Custom WooCommerce Prompt', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            "e.g. Generate product alt text (max {max_length} characters) with the product name, visible features, and use case. Do not start with 'Image of'. Return only the alt text." => __("e.g. Generate product alt text (max {max_length} characters) with the product name, visible features, and use case. Do not start with 'Image of'. Return only the alt text.", 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Use {max_length} as a placeholder. Product name, category, and SKU are automatically included as context.' => __('Use {max_length} as a placeholder. Product name, category, and SKU are automatically included as context.', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'WooCommerce is not installed. Install WooCommerce to access product image optimization features.' => __('WooCommerce is not installed. Install WooCommerce to access product image optimization features.', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Save Settings' => __('Save Settings', 'imagecraft-ai-alt-text-file-renamer-image-seo'),

            // Notifications
            'Failed to load settings' => __('Failed to load settings', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Settings saved successfully' => __('Settings saved successfully', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Failed to save settings' => __('Failed to save settings', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Please enter an API key' => __('Please enter an API key', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'API key saved successfully' => __('API key saved successfully', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Failed to save API key' => __('Failed to save API key', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
        ];
    }

    private static function setupWizard(): array {
        return [
            // Welcome
            'Watch How It Works' => __('Watch How It Works', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Welcome to AI Alt Text Generator' => __('Welcome to AI Alt Text Generator', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Generate accessible alt text for your images using AI. You provide your own API key with no middleman fees.' => __('Generate accessible alt text for your images using AI. You provide your own API key with no middleman fees.', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Easy Setup' => __('Easy Setup', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Get started in 3 minutes' => __('Get started in 3 minutes', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'BYOK Model' => __('BYOK Model', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Use your own API key' => __('Use your own API key', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Unlimited' => __('Unlimited', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Process as many images as you want' => __('Process as many images as you want', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Get Started' => __('Get Started', 'imagecraft-ai-alt-text-file-renamer-image-seo'),

            // Provider
            'Choose Your AI Provider' => __('Choose Your AI Provider', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Low cost with quality results' => __('Low cost with quality results', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Best balance of quality and cost' => __('Best balance of quality and cost', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Great quality, widely used' => __('Great quality, widely used', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Excellent results, higher cost' => __('Excellent results, higher cost', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            "Google's AI vision model" => __("Google's AI vision model", 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Most affordable option' => __('Most affordable option', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Back' => __('Back', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Continue' => __('Continue', 'imagecraft-ai-alt-text-file-renamer-image-seo'),

            // API Key
            'Add Your API Key' => __('Add Your API Key', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'How to get your API key:' => __('How to get your API key:', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Visit' => __('Visit', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Create an account and add credits' => __('Create an account and add credits', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Generate an API key' => __('Generate an API key', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Paste it below' => __('Paste it below', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Enter your API key' => __('Enter your API key', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Connection successful!' => __('Connection successful!', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Connection failed:' => __('Connection failed:', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Connection failed' => __('Connection failed', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Testing...' => __('Testing...', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Test Connection' => __('Test Connection', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Save & Continue' => __('Save & Continue', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Failed to test connection' => __('Failed to test connection', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'API key saved successfully!' => __('API key saved successfully!', 'imagecraft-ai-alt-text-file-renamer-image-seo'),

            // Complete
            'Setup Complete!' => __('Setup Complete!', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            "You're all set to start generating alt text for your images." => __("You're all set to start generating alt text for your images.", 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Go to Dashboard' => __('Go to Dashboard', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Failed to complete wizard' => __('Failed to complete wizard', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
        ];
    }

    private static function batch(): array {
        return [
            'Batch Processing' => __('Batch Processing', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Progress' => __('Progress', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            /* translators: %1$s: number of processed images, %2$s: total number of images */
            '%1$s of %2$s images processed' => __('%1$s of %2$s images processed', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            /* translators: %s: number of failed images */
            '%s failed' => __('%s failed', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Review & Edit Alt Text' => __('Review & Edit Alt Text', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Troubleshooting Failed Images' => __('Troubleshooting Failed Images', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            /* translators: %s: number of failed images */
            'All %s image(s) failed to process. Common causes:' => __('All %s image(s) failed to process. Common causes:', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'No API key configured:' => __('No API key configured:', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Go to Settings and add your API key' => __('Go to Settings and add your API key', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Invalid API key:' => __('Invalid API key:', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Verify your API key is correct' => __('Verify your API key is correct', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'API key permissions:' => __('API key permissions:', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Ensure your API key has access' => __('Ensure your API key has access', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Image URL not accessible:' => __('Image URL not accessible:', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Images must be publicly accessible' => __('Images must be publicly accessible', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'API rate limit exceeded:' => __('API rate limit exceeded:', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Wait a few minutes and try again' => __('Wait a few minutes and try again', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Network/connection error:' => __('Network/connection error:', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Check your internet connection and firewall settings' => __('Check your internet connection and firewall settings', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'To find the exact error:' => __('To find the exact error:', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Enable WordPress debug logging in wp-config.php' => __('Enable WordPress debug logging in wp-config.php', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Check wp-content/debug.log for error entries' => __('Check wp-content/debug.log for error entries', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'The log will show the specific error message for each failed image' => __('The log will show the specific error message for each failed image', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Go to Settings' => __('Go to Settings', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Back to Scanner' => __('Back to Scanner', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'No active batch job' => __('No active batch job', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'No generated images to review.' => __('No generated images to review.', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Enter alt text...' => __('Enter alt text...', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Save All' => __('Save All', 'imagecraft-ai-alt-text-file-renamer-image-seo'),

            // Notifications
            /* translators: %1$s: number of successful images, %2$s: number of failed images */
            'Batch processing completed with %1$s successful and %2$s failed.' => __('Batch processing completed with %1$s successful and %2$s failed.', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Batch processing completed successfully!' => __('Batch processing completed successfully!', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            /* translators: %s: number of failed images */
            'Batch processing failed. All %s images failed to process.' => __('Batch processing failed. All %s images failed to process.', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Batch job was cancelled.' => __('Batch job was cancelled.', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Failed to load generated images' => __('Failed to load generated images', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Alt texts saved successfully.' => __('Alt texts saved successfully.', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Failed to save alt texts' => __('Failed to save alt texts', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Batch job cancelled' => __('Batch job cancelled', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Failed to cancel job' => __('Failed to cancel job', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
        ];
    }

    private static function woocommerce(): array {
        return [
            'WooCommerce Not Detected' => __('WooCommerce Not Detected', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Please install and activate WooCommerce to use this feature.' => __('Please install and activate WooCommerce to use this feature.', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Total Products' => __('Total Products', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Selected' => __('Selected', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'All Products' => __('All Products', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Missing Alt' => __('Missing Alt', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Has Alt' => __('Has Alt', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Loading products...' => __('Loading products...', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'SKU:' => __('SKU:', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'View Details' => __('View Details', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Generate' => __('Generate', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Product Details' => __('Product Details', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Product' => __('Product', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'SKU' => __('SKU', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Featured Image Alt Text' => __('Featured Image Alt Text', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Describe for accessibility...' => __('Describe for accessibility...', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            /* translators: %s: number of gallery images */
            'Gallery Images (%s)' => __('Gallery Images (%s)', 'imagecraft-ai-alt-text-file-renamer-image-seo'),

            // Notifications
            'Failed to load products' => __('Failed to load products', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            /* translators: %1$s: number of successful products, %2$s: total number of products */
            'Generated alt text for %1$s/%2$s products' => __('Generated alt text for %1$s/%2$s products', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Batch generation failed' => __('Batch generation failed', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Alt text generated successfully' => __('Alt text generated successfully', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Alt text generated' => __('Alt text generated', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Failed to generate' => __('Failed to generate', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Alt text updated' => __('Alt text updated', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Failed to save' => __('Failed to save', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
        ];
    }

    private static function seoIntegration(): array {
        return [
            'SEO Integration' => __('SEO Integration', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Detected:' => __('Detected:', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Use your focus keyphrase to improve alt text SEO relevance.' => __('Use your focus keyphrase to improve alt text SEO relevance.', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Enable SEO integration' => __('Enable SEO integration', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'When enabled, the plugin will read SEO data from your SEO plugin to enhance alt text generation.' => __('When enabled, the plugin will read SEO data from your SEO plugin to enhance alt text generation.', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Include focus keyphrase in alt text' => __('Include focus keyphrase in alt text', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'The AI will naturally incorporate the focus keyphrase from the parent post/page into the generated alt text. The image must be attached to a post that has a focus keyphrase set.' => __('The AI will naturally incorporate the focus keyphrase from the parent post/page into the generated alt text. The image must be attached to a post that has a focus keyphrase set.', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'No supported SEO plugin detected. Install Yoast SEO, Rank Math, All in One SEO, or SEOPress to enable focus keyphrase integration.' => __('No supported SEO plugin detected. Install Yoast SEO, Rank Math, All in One SEO, or SEOPress to enable focus keyphrase integration.', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
        ];
    }

    private static function help(): array {
        return [
            'Help' => __('Help', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Help & Support' => __('Help & Support', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            '24/7 Dedicated Support' => __('24/7 Dedicated Support', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Our support team is here to help you with any questions, issues, or feature requests.' => __('Our support team is here to help you with any questions, issues, or feature requests.', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Whether you need help setting up your API key, configuring WooCommerce, or troubleshooting batch processing — we\'ve got you covered.' => __('Whether you need help setting up your API key, configuring WooCommerce, or troubleshooting batch processing — we\'ve got you covered.', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Contact Support' => __('Contact Support', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Give Your Love' => __('Give Your Love', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Your love is our motivation! A quick review helps us grow and keep improving ImageCraft for everyone.' => __('Your love is our motivation! A quick review helps us grow and keep improving ImageCraft for everyone.', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'If ImageCraft has helped your workflow, please take a moment to share your experience on WordPress.org.' => __('If ImageCraft has helped your workflow, please take a moment to share your experience on WordPress.org.', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Leave a Review' => __('Leave a Review', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Quick Links' => __('Quick Links', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'WordPress.org Plugin Page' => __('WordPress.org Plugin Page', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'View plugin details and changelog' => __('View plugin details and changelog', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Knowledge Base' => __('Knowledge Base', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Guides, tutorials, and FAQs' => __('Guides, tutorials, and FAQs', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Community Forum' => __('Community Forum', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Ask questions and get help from the community' => __('Ask questions and get help from the community', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Plugin Information' => __('Plugin Information', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Version' => __('Version', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Developer' => __('Developer', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'License' => __('License', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Requires WordPress' => __('Requires WordPress', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Video Tutorial' => __('Video Tutorial', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            'Watch how to set up and use ImageCraft in just a few minutes.' => __('Watch how to set up and use ImageCraft in just a few minutes.', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
        ];
    }
}
