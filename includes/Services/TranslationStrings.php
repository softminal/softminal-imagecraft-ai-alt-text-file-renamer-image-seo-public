<?php

namespace SmartAltTextAi\Includes\Services;

if (!defined('ABSPATH')) {
    exit;
}

class TranslationStrings {

    private const TEXT_DOMAIN = 'smart-alt-text-ai';

    public static function get(): array {
        return array_merge(
            self::navbar(),
            self::dashboard(),
            self::scanner(),
            self::settings(),
            self::setupWizard(),
            self::batch(),
            self::woocommerce(),
            self::seoIntegration()
        );
    }

    private static function navbar(): array {
        return self::translate([
            'Dashboard',
            'Scanner',
            'WooCommerce',
            'Settings',
            'Loading...',
        ]);
    }

    private static function dashboard(): array {
        return self::translate([
            // Stats
            'Total Images',
            'Missing Alt Text',
            'This Month',
            'Success Rate',

            // SEO Audit
            'Image SEO Audit',
            'Scanning...',
            'Refresh',
            'Scanning media library...',
            'Excellent',
            'Good',
            'Needs Work',
            'Poor',
            '%s of %s images are fully optimized',
            'Generic / Placeholder',
            'Too Long',
            'Generic alt text found:',
            'Alt text exceeding %s characters:',
            '%s chars',

            // API Status
            'API Status',
            'Connected to %s',
            'Not connected',
            'Configure API',

            // Quick Actions
            'Quick Actions',
            'Scan Media Library',
            'WooCommerce Products',

            // Recent Activity
            'Recent Activity',
            'No recent activity',
        ]);
    }

    private static function scanner(): array {
        return self::translate([
            // Filters & Actions
            'Media Library Scanner',
            'Has Alt Text',
            'All Images',
            'Generate for %s Selected',
            'Rename %s Selected',
            'Advanced Filters & Bulk Select',
            'Upload Date From',
            'Upload Date To',
            'Min File Size (KB)',
            'e.g. 500',
            'Apply',
            'Clear Filters',
            'Quick Bulk Select (across all pages)',
            'Selecting...',
            'All Missing Alt Text',
            'All Generic / Placeholder',
            'All Too Long',
            'All Larger Than %s KB',
            'Clear Selection (%s)',
            '%s images selected across all pages',
            'Loading images...',
            'Select All',
            'Has alt',
            'Missing',
            'No alt text',
            'Rename',
            'Renaming...',
            'Previous',
            'Page %s of %s',
            'Next',

            // Image Modal
            'Attachment details',
            'Close',
            'File name',
            'Rename to SEO',
            'Alt text',
            'Generating...',
            'Generate with AI',
            'Describe the image for accessibility...',
            'Alternative text helps describe the image for screen readers and SEO.',
            'Open in WordPress Media Library',
            'Cancel',
            'Saving...',
            'Update',

            // Notifications
            'Failed to load images',
            'No images match this filter',
            '%s images selected',
            'Failed to select images',
            'Maximum 500 images per batch job. Please reduce your selection.',
            'Batch job started for %s images',
            'Failed to start batch job',
            'Alt text generated. Review and click Update to save.',
            'Failed to generate alt text',
            'Alt text updated.',
            'Failed to update alt text',
            'Renamed to: %s',
            'Failed to rename file',
            'Selected images must have alt text to rename',
            'Renamed %s files',
            'Renamed %s/%s files',
            'Bulk rename failed',
        ]);
    }

    private static function settings(): array {
        return self::translate([
            // API Configuration
            'API Configuration',
            'Provider',
            'Anthropic Claude',
            'OpenAI GPT-4',
            'Google Gemini',
            'API Key',
            'Enter API key',
            'Save API Key',
            'Status:',
            'Active',
            'Inactive',
            'Provider:',

            // Generation
            'Generation Settings',
            'Default Tone',
            'Professional',
            'Casual',
            'Technical',
            'SEO-Optimized',
            'Custom Prompt',
            "e.g. Generate alt text (max {max_length} characters) describing the subject, colors, and setting. Do not start with 'Image of'. Return only the alt text.",
            'Use {max_length} as a placeholder for your max character setting. Product context is added automatically for WooCommerce images.',
            'Max Character Length',
            'Language',
            'English',
            'Spanish',
            'French',
            'German',
            'Italian',
            'Portuguese',
            'Dutch',
            'Russian',
            'Japanese',
            'Chinese',
            'Korean',
            'Arabic',
            'Hindi',
            'Bengali',
            'Turkish',
            'Polish',
            'Swedish',
            'Danish',
            'Finnish',
            'Norwegian',
            'Thai',
            'Vietnamese',
            'Indonesian',
            'Malay',
            'Ukrainian',
            'Czech',
            'Greek',
            'Hebrew',
            'Romanian',
            'Hungarian',
            'Alt text will be generated in the selected language',
            'Auto-generate on upload',
            'Preview before accepting',
            'Skip images with existing alt text',

            // File Renaming
            'File Renaming',
            'Auto-rename files on upload',
            'Rename uploaded files to SEO-friendly names based on generated alt text',
            'Max Filename Length',
            'Characters in filename (default: 120)',

            // Advanced
            'Advanced Settings',
            'Batch Size',
            'Number of images to process per batch',
            'Request Timeout (seconds)',

            // WooCommerce
            'WooCommerce Settings',
            'Configure alt text generation for WooCommerce products',
            'Auto-generate on product upload',
            'Automatically generate alt text when adding product images',
            'Include product information',
            'Use product name, categories, and SKU in alt text generation',
            'Default Tone for Products',
            'WooCommerce Optimized',
            'Product SEO (Recommended)',
            'Product Descriptive (Accessibility)',
            'Product Lifestyle (In-Use Photos)',
            'General',
            'Custom WooCommerce Prompt',
            "e.g. Generate product alt text (max {max_length} characters) with the product name, visible features, and use case. Do not start with 'Image of'. Return only the alt text.",
            'Use {max_length} as a placeholder. Product name, category, and SKU are automatically included as context.',
            'WooCommerce is not installed. Install WooCommerce to access product image optimization features.',
            'Save Settings',

            // Notifications
            'Failed to load settings',
            'Settings saved successfully',
            'Failed to save settings',
            'Please enter an API key',
            'API key saved successfully',
            'Failed to save API key',
        ]);
    }

    private static function setupWizard(): array {
        return self::translate([
            // Welcome
            'Welcome to AI Alt Text Generator',
            'Generate accessible alt text for your images using AI. 100% free forever - you use your own API key.',
            'Easy Setup',
            'Get started in 3 minutes',
            '100% Free',
            'No paid tiers, ever',
            'Unlimited',
            'Process as many images as you want',
            'Get Started',

            // Provider
            'Choose Your AI Provider',
            'Best quality at lowest cost',
            'Cost: ~$0.0008/image',
            '$5 = 6,000+ images',
            'Great quality, widely used',
            'Cost: ~$0.01/image',
            '$5 = 500 images',
            "Google's AI vision model",
            'Cost: ~$0.0005/image',
            '$5 = 10,000+ images',
            'Back',
            'Continue',

            // API Key
            'Add Your API Key',
            'How to get your API key:',
            'Visit',
            'Create an account and add credits',
            'Generate an API key',
            'Paste it below',
            'Enter your API key',
            'Connection successful!',
            'Connection failed:',
            'Connection failed',
            'Testing...',
            'Test Connection',
            'Save & Continue',
            'Failed to test connection',
            'API key saved successfully!',

            // Complete
            'Setup Complete!',
            "You're all set to start generating alt text for your images.",
            'Go to Dashboard',
            'Failed to complete wizard',
        ]);
    }

    private static function batch(): array {
        return self::translate([
            'Batch Processing',
            'Progress',
            '%s of %s images processed',
            '%s failed',
            'Review & Edit Alt Text',
            'Troubleshooting Failed Images',
            'All %s image(s) failed to process. Common causes:',
            'No API key configured:',
            'Go to Settings and add your API key',
            'Invalid API key:',
            'Verify your API key is correct',
            'API key permissions:',
            'Ensure your API key has access',
            'Image URL not accessible:',
            'Images must be publicly accessible',
            'API rate limit exceeded:',
            'Wait a few minutes and try again',
            'Network/connection error:',
            'Check your internet connection and firewall settings',
            'To find the exact error:',
            'Enable WordPress debug logging in wp-config.php',
            'Check wp-content/debug.log for error entries',
            'The log will show the specific error message for each failed image',
            'Go to Settings',
            'Back to Scanner',
            'No active batch job',
            'No generated images to review.',
            'Enter alt text...',
            'Save All',

            // Notifications
            'Batch processing completed with %s successful and %s failed.',
            'Batch processing completed successfully!',
            'Batch processing failed. All %s images failed to process.',
            'Batch job was cancelled.',
            'Failed to load generated images',
            'Alt texts saved successfully.',
            'Failed to save alt texts',
            'Batch job cancelled',
            'Failed to cancel job',
        ]);
    }

    private static function woocommerce(): array {
        return self::translate([
            'WooCommerce Not Detected',
            'Please install and activate WooCommerce to use this feature.',
            'Total Products',
            'Selected',
            'All Products',
            'Missing Alt',
            'Has Alt',
            'Loading products...',
            'SKU:',
            'View Details',
            'Generate',
            'Product Details',
            'Product',
            'SKU',
            'Featured Image Alt Text',
            'Describe for accessibility...',
            'Gallery Images (%s)',

            // Notifications
            'Failed to load products',
            'Generated alt text for %s/%s products',
            'Batch generation failed',
            'Alt text generated successfully',
            'Alt text generated',
            'Failed to generate',
            'Alt text updated',
            'Failed to save',
        ]);
    }

    private static function seoIntegration(): array {
        return self::translate([
            'SEO Integration',
            'Detected:',
            'Use your focus keyphrase to improve alt text SEO relevance.',
            'Enable SEO integration',
            'When enabled, the plugin will read SEO data from your SEO plugin to enhance alt text generation.',
            'Include focus keyphrase in alt text',
            'The AI will naturally incorporate the focus keyphrase from the parent post/page into the generated alt text. The image must be attached to a post that has a focus keyphrase set.',
            'No supported SEO plugin detected. Install Yoast SEO, Rank Math, All in One SEO, or SEOPress to enable focus keyphrase integration.',
        ]);
    }

    private static function translate(array $strings): array {
        $translated = [];
        foreach ($strings as $string) {
            $translated[$string] = __($string, self::TEXT_DOMAIN);
        }
        return $translated;
    }
}
