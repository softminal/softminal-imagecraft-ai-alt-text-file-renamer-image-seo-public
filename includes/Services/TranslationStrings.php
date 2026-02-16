<?php

namespace ImageCraft\Includes\Services;

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
            self::seoIntegration()
        );
    }

    private static function navbar(): array {
        return [
            'Dashboard' => __('Dashboard', 'imagecraft'),
            'Scanner' => __('Scanner', 'imagecraft'),
            'WooCommerce' => __('WooCommerce', 'imagecraft'),
            'Settings' => __('Settings', 'imagecraft'),
            'Loading...' => __('Loading...', 'imagecraft'),
        ];
    }

    private static function dashboard(): array {
        return [
            // Stats
            'Total Images' => __('Total Images', 'imagecraft'),
            'Missing Alt Text' => __('Missing Alt Text', 'imagecraft'),
            'This Month' => __('This Month', 'imagecraft'),
            'Success Rate' => __('Success Rate', 'imagecraft'),

            // SEO Audit
            'Image SEO Audit' => __('Image SEO Audit', 'imagecraft'),
            'Scanning...' => __('Scanning...', 'imagecraft'),
            'Refresh' => __('Refresh', 'imagecraft'),
            'Scanning media library...' => __('Scanning media library...', 'imagecraft'),
            'Excellent' => __('Excellent', 'imagecraft'),
            'Good' => __('Good', 'imagecraft'),
            'Needs Work' => __('Needs Work', 'imagecraft'),
            'Poor' => __('Poor', 'imagecraft'),
            /* translators: %1$s: number of optimized images, %2$s: total number of images */
            '%1$s of %2$s images are fully optimized' => __('%1$s of %2$s images are fully optimized', 'imagecraft'),
            'Generic / Placeholder' => __('Generic / Placeholder', 'imagecraft'),
            'Too Long' => __('Too Long', 'imagecraft'),
            'Generic alt text found:' => __('Generic alt text found:', 'imagecraft'),
            /* translators: %s: maximum character count */
            'Alt text exceeding %s characters:' => __('Alt text exceeding %s characters:', 'imagecraft'),
            /* translators: %s: character count */
            '%s chars' => __('%s chars', 'imagecraft'),

            // API Status
            'API Status' => __('API Status', 'imagecraft'),
            /* translators: %s: AI provider name */
            'Connected to %s' => __('Connected to %s', 'imagecraft'),
            'Not connected' => __('Not connected', 'imagecraft'),
            'Configure API' => __('Configure API', 'imagecraft'),

            // Quick Actions
            'Quick Actions' => __('Quick Actions', 'imagecraft'),
            'Scan Media Library' => __('Scan Media Library', 'imagecraft'),
            'WooCommerce Products' => __('WooCommerce Products', 'imagecraft'),

            // Recent Activity
            'Recent Activity' => __('Recent Activity', 'imagecraft'),
            'No recent activity' => __('No recent activity', 'imagecraft'),
        ];
    }

    private static function scanner(): array {
        return [
            // Filters & Actions
            'Media Library Scanner' => __('Media Library Scanner', 'imagecraft'),
            'Has Alt Text' => __('Has Alt Text', 'imagecraft'),
            'All Images' => __('All Images', 'imagecraft'),
            /* translators: %s: number of selected images */
            'Generate for %s Selected' => __('Generate for %s Selected', 'imagecraft'),
            /* translators: %s: number of selected images */
            'Rename %s Selected' => __('Rename %s Selected', 'imagecraft'),
            'Advanced Filters & Bulk Select' => __('Advanced Filters & Bulk Select', 'imagecraft'),
            'Upload Date From' => __('Upload Date From', 'imagecraft'),
            'Upload Date To' => __('Upload Date To', 'imagecraft'),
            'Min File Size (KB)' => __('Min File Size (KB)', 'imagecraft'),
            'e.g. 500' => __('e.g. 500', 'imagecraft'),
            'Apply' => __('Apply', 'imagecraft'),
            'Clear Filters' => __('Clear Filters', 'imagecraft'),
            'Quick Bulk Select (across all pages)' => __('Quick Bulk Select (across all pages)', 'imagecraft'),
            'Selecting...' => __('Selecting...', 'imagecraft'),
            'All Missing Alt Text' => __('All Missing Alt Text', 'imagecraft'),
            'All Generic / Placeholder' => __('All Generic / Placeholder', 'imagecraft'),
            'All Too Long' => __('All Too Long', 'imagecraft'),
            /* translators: %s: minimum file size in kilobytes */
            'All Larger Than %s KB' => __('All Larger Than %s KB', 'imagecraft'),
            /* translators: %s: number of selected images */
            'Clear Selection (%s)' => __('Clear Selection (%s)', 'imagecraft'),
            /* translators: %s: number of selected images */
            '%s images selected across all pages' => __('%s images selected across all pages', 'imagecraft'),
            'Loading images...' => __('Loading images...', 'imagecraft'),
            'Select All' => __('Select All', 'imagecraft'),
            'Has alt' => __('Has alt', 'imagecraft'),
            'Missing' => __('Missing', 'imagecraft'),
            'No alt text' => __('No alt text', 'imagecraft'),
            'Rename' => __('Rename', 'imagecraft'),
            'Renaming...' => __('Renaming...', 'imagecraft'),
            'Previous' => __('Previous', 'imagecraft'),
            /* translators: %1$s: current page number, %2$s: total number of pages */
            'Page %1$s of %2$s' => __('Page %1$s of %2$s', 'imagecraft'),
            'Next' => __('Next', 'imagecraft'),

            // Image Modal
            'Attachment details' => __('Attachment details', 'imagecraft'),
            'Close' => __('Close', 'imagecraft'),
            'File name' => __('File name', 'imagecraft'),
            'Rename to SEO' => __('Rename to SEO', 'imagecraft'),
            'Alt text' => __('Alt text', 'imagecraft'),
            'Generating...' => __('Generating...', 'imagecraft'),
            'Generate with AI' => __('Generate with AI', 'imagecraft'),
            'Describe the image for accessibility...' => __('Describe the image for accessibility...', 'imagecraft'),
            'Alternative text helps describe the image for screen readers and SEO.' => __('Alternative text helps describe the image for screen readers and SEO.', 'imagecraft'),
            'Open in WordPress Media Library' => __('Open in WordPress Media Library', 'imagecraft'),
            'Cancel' => __('Cancel', 'imagecraft'),
            'Saving...' => __('Saving...', 'imagecraft'),
            'Update' => __('Update', 'imagecraft'),

            // Notifications
            'Failed to load images' => __('Failed to load images', 'imagecraft'),
            'No images match this filter' => __('No images match this filter', 'imagecraft'),
            /* translators: %s: number of selected images */
            '%s images selected' => __('%s images selected', 'imagecraft'),
            'Failed to select images' => __('Failed to select images', 'imagecraft'),
            'Maximum 500 images per batch job. Please reduce your selection.' => __('Maximum 500 images per batch job. Please reduce your selection.', 'imagecraft'),
            /* translators: %s: number of images in batch */
            'Batch job started for %s images' => __('Batch job started for %s images', 'imagecraft'),
            'Failed to start batch job' => __('Failed to start batch job', 'imagecraft'),
            'Alt text generated. Review and click Update to save.' => __('Alt text generated. Review and click Update to save.', 'imagecraft'),
            'Failed to generate alt text' => __('Failed to generate alt text', 'imagecraft'),
            'Alt text updated.' => __('Alt text updated.', 'imagecraft'),
            'Failed to update alt text' => __('Failed to update alt text', 'imagecraft'),
            /* translators: %s: new filename */
            'Renamed to: %s' => __('Renamed to: %s', 'imagecraft'),
            'Failed to rename file' => __('Failed to rename file', 'imagecraft'),
            'Selected images must have alt text to rename' => __('Selected images must have alt text to rename', 'imagecraft'),
            /* translators: %s: number of renamed files */
            'Renamed %s files' => __('Renamed %s files', 'imagecraft'),
            /* translators: %1$s: number of successfully renamed files, %2$s: total number of files */
            'Renamed %1$s/%2$s files' => __('Renamed %1$s/%2$s files', 'imagecraft'),
            'Bulk rename failed' => __('Bulk rename failed', 'imagecraft'),
        ];
    }

    private static function settings(): array {
        return [
            // API Configuration
            'API Configuration' => __('API Configuration', 'imagecraft'),
            'Provider' => __('Provider', 'imagecraft'),
            'Anthropic Claude' => __('Anthropic Claude', 'imagecraft'),
            'OpenAI GPT-4' => __('OpenAI GPT-4', 'imagecraft'),
            'Google Gemini' => __('Google Gemini', 'imagecraft'),
            'API Key' => __('API Key', 'imagecraft'),
            'Enter API key' => __('Enter API key', 'imagecraft'),
            'Save API Key' => __('Save API Key', 'imagecraft'),
            'Status:' => __('Status:', 'imagecraft'),
            'Active' => __('Active', 'imagecraft'),
            'Inactive' => __('Inactive', 'imagecraft'),
            'Provider:' => __('Provider:', 'imagecraft'),

            // Generation
            'Generation Settings' => __('Generation Settings', 'imagecraft'),
            'Default Tone' => __('Default Tone', 'imagecraft'),
            'Professional' => __('Professional', 'imagecraft'),
            'Casual' => __('Casual', 'imagecraft'),
            'Technical' => __('Technical', 'imagecraft'),
            'SEO-Optimized' => __('SEO-Optimized', 'imagecraft'),
            'Custom Prompt' => __('Custom Prompt', 'imagecraft'),
            "e.g. Generate alt text (max {max_length} characters) describing the subject, colors, and setting. Do not start with 'Image of'. Return only the alt text." => __("e.g. Generate alt text (max {max_length} characters) describing the subject, colors, and setting. Do not start with 'Image of'. Return only the alt text.", 'imagecraft'),
            'Use {max_length} as a placeholder for your max character setting. Product context is added automatically for WooCommerce images.' => __('Use {max_length} as a placeholder for your max character setting. Product context is added automatically for WooCommerce images.', 'imagecraft'),
            'Max Character Length' => __('Max Character Length', 'imagecraft'),
            'Language' => __('Language', 'imagecraft'),
            'English' => __('English', 'imagecraft'),
            'Spanish' => __('Spanish', 'imagecraft'),
            'French' => __('French', 'imagecraft'),
            'German' => __('German', 'imagecraft'),
            'Italian' => __('Italian', 'imagecraft'),
            'Portuguese' => __('Portuguese', 'imagecraft'),
            'Dutch' => __('Dutch', 'imagecraft'),
            'Russian' => __('Russian', 'imagecraft'),
            'Japanese' => __('Japanese', 'imagecraft'),
            'Chinese' => __('Chinese', 'imagecraft'),
            'Korean' => __('Korean', 'imagecraft'),
            'Arabic' => __('Arabic', 'imagecraft'),
            'Hindi' => __('Hindi', 'imagecraft'),
            'Bengali' => __('Bengali', 'imagecraft'),
            'Turkish' => __('Turkish', 'imagecraft'),
            'Polish' => __('Polish', 'imagecraft'),
            'Swedish' => __('Swedish', 'imagecraft'),
            'Danish' => __('Danish', 'imagecraft'),
            'Finnish' => __('Finnish', 'imagecraft'),
            'Norwegian' => __('Norwegian', 'imagecraft'),
            'Thai' => __('Thai', 'imagecraft'),
            'Vietnamese' => __('Vietnamese', 'imagecraft'),
            'Indonesian' => __('Indonesian', 'imagecraft'),
            'Malay' => __('Malay', 'imagecraft'),
            'Ukrainian' => __('Ukrainian', 'imagecraft'),
            'Czech' => __('Czech', 'imagecraft'),
            'Greek' => __('Greek', 'imagecraft'),
            'Hebrew' => __('Hebrew', 'imagecraft'),
            'Romanian' => __('Romanian', 'imagecraft'),
            'Hungarian' => __('Hungarian', 'imagecraft'),
            'Alt text will be generated in the selected language' => __('Alt text will be generated in the selected language', 'imagecraft'),
            'Auto-generate on upload' => __('Auto-generate on upload', 'imagecraft'),
            'Preview before accepting' => __('Preview before accepting', 'imagecraft'),
            'Skip images with existing alt text' => __('Skip images with existing alt text', 'imagecraft'),

            // File Renaming
            'File Renaming' => __('File Renaming', 'imagecraft'),
            'Auto-rename files on upload' => __('Auto-rename files on upload', 'imagecraft'),
            'Rename uploaded files to SEO-friendly names based on generated alt text' => __('Rename uploaded files to SEO-friendly names based on generated alt text', 'imagecraft'),
            'Max Filename Length' => __('Max Filename Length', 'imagecraft'),
            'Characters in filename (default: 120)' => __('Characters in filename (default: 120)', 'imagecraft'),

            // Advanced
            'Advanced Settings' => __('Advanced Settings', 'imagecraft'),
            'Batch Size' => __('Batch Size', 'imagecraft'),
            'Number of images to process per batch' => __('Number of images to process per batch', 'imagecraft'),
            'Request Timeout (seconds)' => __('Request Timeout (seconds)', 'imagecraft'),

            // WooCommerce
            'WooCommerce Settings' => __('WooCommerce Settings', 'imagecraft'),
            'Configure alt text generation for WooCommerce products' => __('Configure alt text generation for WooCommerce products', 'imagecraft'),
            'Auto-generate on product upload' => __('Auto-generate on product upload', 'imagecraft'),
            'Automatically generate alt text when adding product images' => __('Automatically generate alt text when adding product images', 'imagecraft'),
            'Include product information' => __('Include product information', 'imagecraft'),
            'Use product name, categories, and SKU in alt text generation' => __('Use product name, categories, and SKU in alt text generation', 'imagecraft'),
            'Default Tone for Products' => __('Default Tone for Products', 'imagecraft'),
            'WooCommerce Optimized' => __('WooCommerce Optimized', 'imagecraft'),
            'Product SEO (Recommended)' => __('Product SEO (Recommended)', 'imagecraft'),
            'Product Descriptive (Accessibility)' => __('Product Descriptive (Accessibility)', 'imagecraft'),
            'Product Lifestyle (In-Use Photos)' => __('Product Lifestyle (In-Use Photos)', 'imagecraft'),
            'General' => __('General', 'imagecraft'),
            'Custom WooCommerce Prompt' => __('Custom WooCommerce Prompt', 'imagecraft'),
            "e.g. Generate product alt text (max {max_length} characters) with the product name, visible features, and use case. Do not start with 'Image of'. Return only the alt text." => __("e.g. Generate product alt text (max {max_length} characters) with the product name, visible features, and use case. Do not start with 'Image of'. Return only the alt text.", 'imagecraft'),
            'Use {max_length} as a placeholder. Product name, category, and SKU are automatically included as context.' => __('Use {max_length} as a placeholder. Product name, category, and SKU are automatically included as context.', 'imagecraft'),
            'WooCommerce is not installed. Install WooCommerce to access product image optimization features.' => __('WooCommerce is not installed. Install WooCommerce to access product image optimization features.', 'imagecraft'),
            'Save Settings' => __('Save Settings', 'imagecraft'),

            // Notifications
            'Failed to load settings' => __('Failed to load settings', 'imagecraft'),
            'Settings saved successfully' => __('Settings saved successfully', 'imagecraft'),
            'Failed to save settings' => __('Failed to save settings', 'imagecraft'),
            'Please enter an API key' => __('Please enter an API key', 'imagecraft'),
            'API key saved successfully' => __('API key saved successfully', 'imagecraft'),
            'Failed to save API key' => __('Failed to save API key', 'imagecraft'),
        ];
    }

    private static function setupWizard(): array {
        return [
            // Welcome
            'Welcome to AI Alt Text Generator' => __('Welcome to AI Alt Text Generator', 'imagecraft'),
            'Generate accessible alt text for your images using AI. You provide your own API key with no middleman fees.' => __('Generate accessible alt text for your images using AI. You provide your own API key with no middleman fees.', 'imagecraft'),
            'Easy Setup' => __('Easy Setup', 'imagecraft'),
            'Get started in 3 minutes' => __('Get started in 3 minutes', 'imagecraft'),
            'BYOK Model' => __('BYOK Model', 'imagecraft'),
            'Use your own API key' => __('Use your own API key', 'imagecraft'),
            'Unlimited' => __('Unlimited', 'imagecraft'),
            'Process as many images as you want' => __('Process as many images as you want', 'imagecraft'),
            'Get Started' => __('Get Started', 'imagecraft'),

            // Provider
            'Choose Your AI Provider' => __('Choose Your AI Provider', 'imagecraft'),
            'Low cost with quality results' => __('Low cost with quality results', 'imagecraft'),
            'Cost: ~$0.0008/image' => __('Cost: ~$0.0008/image', 'imagecraft'),
            '$5 = 6,000+ images' => __('$5 = 6,000+ images', 'imagecraft'),
            'Great quality, widely used' => __('Great quality, widely used', 'imagecraft'),
            'Cost: ~$0.01/image' => __('Cost: ~$0.01/image', 'imagecraft'),
            '$5 = 500 images' => __('$5 = 500 images', 'imagecraft'),
            "Google's AI vision model" => __("Google's AI vision model", 'imagecraft'),
            'Cost: ~$0.0005/image' => __('Cost: ~$0.0005/image', 'imagecraft'),
            '$5 = 10,000+ images' => __('$5 = 10,000+ images', 'imagecraft'),
            'Back' => __('Back', 'imagecraft'),
            'Continue' => __('Continue', 'imagecraft'),

            // API Key
            'Add Your API Key' => __('Add Your API Key', 'imagecraft'),
            'How to get your API key:' => __('How to get your API key:', 'imagecraft'),
            'Visit' => __('Visit', 'imagecraft'),
            'Create an account and add credits' => __('Create an account and add credits', 'imagecraft'),
            'Generate an API key' => __('Generate an API key', 'imagecraft'),
            'Paste it below' => __('Paste it below', 'imagecraft'),
            'Enter your API key' => __('Enter your API key', 'imagecraft'),
            'Connection successful!' => __('Connection successful!', 'imagecraft'),
            'Connection failed:' => __('Connection failed:', 'imagecraft'),
            'Connection failed' => __('Connection failed', 'imagecraft'),
            'Testing...' => __('Testing...', 'imagecraft'),
            'Test Connection' => __('Test Connection', 'imagecraft'),
            'Save & Continue' => __('Save & Continue', 'imagecraft'),
            'Failed to test connection' => __('Failed to test connection', 'imagecraft'),
            'API key saved successfully!' => __('API key saved successfully!', 'imagecraft'),

            // Complete
            'Setup Complete!' => __('Setup Complete!', 'imagecraft'),
            "You're all set to start generating alt text for your images." => __("You're all set to start generating alt text for your images.", 'imagecraft'),
            'Go to Dashboard' => __('Go to Dashboard', 'imagecraft'),
            'Failed to complete wizard' => __('Failed to complete wizard', 'imagecraft'),
        ];
    }

    private static function batch(): array {
        return [
            'Batch Processing' => __('Batch Processing', 'imagecraft'),
            'Progress' => __('Progress', 'imagecraft'),
            /* translators: %1$s: number of processed images, %2$s: total number of images */
            '%1$s of %2$s images processed' => __('%1$s of %2$s images processed', 'imagecraft'),
            /* translators: %s: number of failed images */
            '%s failed' => __('%s failed', 'imagecraft'),
            'Review & Edit Alt Text' => __('Review & Edit Alt Text', 'imagecraft'),
            'Troubleshooting Failed Images' => __('Troubleshooting Failed Images', 'imagecraft'),
            /* translators: %s: number of failed images */
            'All %s image(s) failed to process. Common causes:' => __('All %s image(s) failed to process. Common causes:', 'imagecraft'),
            'No API key configured:' => __('No API key configured:', 'imagecraft'),
            'Go to Settings and add your API key' => __('Go to Settings and add your API key', 'imagecraft'),
            'Invalid API key:' => __('Invalid API key:', 'imagecraft'),
            'Verify your API key is correct' => __('Verify your API key is correct', 'imagecraft'),
            'API key permissions:' => __('API key permissions:', 'imagecraft'),
            'Ensure your API key has access' => __('Ensure your API key has access', 'imagecraft'),
            'Image URL not accessible:' => __('Image URL not accessible:', 'imagecraft'),
            'Images must be publicly accessible' => __('Images must be publicly accessible', 'imagecraft'),
            'API rate limit exceeded:' => __('API rate limit exceeded:', 'imagecraft'),
            'Wait a few minutes and try again' => __('Wait a few minutes and try again', 'imagecraft'),
            'Network/connection error:' => __('Network/connection error:', 'imagecraft'),
            'Check your internet connection and firewall settings' => __('Check your internet connection and firewall settings', 'imagecraft'),
            'To find the exact error:' => __('To find the exact error:', 'imagecraft'),
            'Enable WordPress debug logging in wp-config.php' => __('Enable WordPress debug logging in wp-config.php', 'imagecraft'),
            'Check wp-content/debug.log for error entries' => __('Check wp-content/debug.log for error entries', 'imagecraft'),
            'The log will show the specific error message for each failed image' => __('The log will show the specific error message for each failed image', 'imagecraft'),
            'Go to Settings' => __('Go to Settings', 'imagecraft'),
            'Back to Scanner' => __('Back to Scanner', 'imagecraft'),
            'No active batch job' => __('No active batch job', 'imagecraft'),
            'No generated images to review.' => __('No generated images to review.', 'imagecraft'),
            'Enter alt text...' => __('Enter alt text...', 'imagecraft'),
            'Save All' => __('Save All', 'imagecraft'),

            // Notifications
            /* translators: %1$s: number of successful images, %2$s: number of failed images */
            'Batch processing completed with %1$s successful and %2$s failed.' => __('Batch processing completed with %1$s successful and %2$s failed.', 'imagecraft'),
            'Batch processing completed successfully!' => __('Batch processing completed successfully!', 'imagecraft'),
            /* translators: %s: number of failed images */
            'Batch processing failed. All %s images failed to process.' => __('Batch processing failed. All %s images failed to process.', 'imagecraft'),
            'Batch job was cancelled.' => __('Batch job was cancelled.', 'imagecraft'),
            'Failed to load generated images' => __('Failed to load generated images', 'imagecraft'),
            'Alt texts saved successfully.' => __('Alt texts saved successfully.', 'imagecraft'),
            'Failed to save alt texts' => __('Failed to save alt texts', 'imagecraft'),
            'Batch job cancelled' => __('Batch job cancelled', 'imagecraft'),
            'Failed to cancel job' => __('Failed to cancel job', 'imagecraft'),
        ];
    }

    private static function woocommerce(): array {
        return [
            'WooCommerce Not Detected' => __('WooCommerce Not Detected', 'imagecraft'),
            'Please install and activate WooCommerce to use this feature.' => __('Please install and activate WooCommerce to use this feature.', 'imagecraft'),
            'Total Products' => __('Total Products', 'imagecraft'),
            'Selected' => __('Selected', 'imagecraft'),
            'All Products' => __('All Products', 'imagecraft'),
            'Missing Alt' => __('Missing Alt', 'imagecraft'),
            'Has Alt' => __('Has Alt', 'imagecraft'),
            'Loading products...' => __('Loading products...', 'imagecraft'),
            'SKU:' => __('SKU:', 'imagecraft'),
            'View Details' => __('View Details', 'imagecraft'),
            'Generate' => __('Generate', 'imagecraft'),
            'Product Details' => __('Product Details', 'imagecraft'),
            'Product' => __('Product', 'imagecraft'),
            'SKU' => __('SKU', 'imagecraft'),
            'Featured Image Alt Text' => __('Featured Image Alt Text', 'imagecraft'),
            'Describe for accessibility...' => __('Describe for accessibility...', 'imagecraft'),
            /* translators: %s: number of gallery images */
            'Gallery Images (%s)' => __('Gallery Images (%s)', 'imagecraft'),

            // Notifications
            'Failed to load products' => __('Failed to load products', 'imagecraft'),
            /* translators: %1$s: number of successful products, %2$s: total number of products */
            'Generated alt text for %1$s/%2$s products' => __('Generated alt text for %1$s/%2$s products', 'imagecraft'),
            'Batch generation failed' => __('Batch generation failed', 'imagecraft'),
            'Alt text generated successfully' => __('Alt text generated successfully', 'imagecraft'),
            'Alt text generated' => __('Alt text generated', 'imagecraft'),
            'Failed to generate' => __('Failed to generate', 'imagecraft'),
            'Alt text updated' => __('Alt text updated', 'imagecraft'),
            'Failed to save' => __('Failed to save', 'imagecraft'),
        ];
    }

    private static function seoIntegration(): array {
        return [
            'SEO Integration' => __('SEO Integration', 'imagecraft'),
            'Detected:' => __('Detected:', 'imagecraft'),
            'Use your focus keyphrase to improve alt text SEO relevance.' => __('Use your focus keyphrase to improve alt text SEO relevance.', 'imagecraft'),
            'Enable SEO integration' => __('Enable SEO integration', 'imagecraft'),
            'When enabled, the plugin will read SEO data from your SEO plugin to enhance alt text generation.' => __('When enabled, the plugin will read SEO data from your SEO plugin to enhance alt text generation.', 'imagecraft'),
            'Include focus keyphrase in alt text' => __('Include focus keyphrase in alt text', 'imagecraft'),
            'The AI will naturally incorporate the focus keyphrase from the parent post/page into the generated alt text. The image must be attached to a post that has a focus keyphrase set.' => __('The AI will naturally incorporate the focus keyphrase from the parent post/page into the generated alt text. The image must be attached to a post that has a focus keyphrase set.', 'imagecraft'),
            'No supported SEO plugin detected. Install Yoast SEO, Rank Math, All in One SEO, or SEOPress to enable focus keyphrase integration.' => __('No supported SEO plugin detected. Install Yoast SEO, Rank Math, All in One SEO, or SEOPress to enable focus keyphrase integration.', 'imagecraft'),
        ];
    }
}
