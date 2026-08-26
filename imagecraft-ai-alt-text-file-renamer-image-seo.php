<?php

/**
 * Plugin Name:       ImageCraft - Image Optimizer, WebP/AVIF & AI Alt Text
 * Plugin URI:        https://www.softminal.com/products/imagecraft-ai-alt-text-file-renamer-image-seo
 * Description:       All-in-one image optimizer: smart compression, WebP & AVIF conversion, image fallbacks, AI alt text & titles, SEO file renaming, and an image SEO audit. No account, no credits — optimization runs locally for free. AI works with WordPress 7.0 native AI, Anthropic, OpenAI or Gemini.
 * Version:           1.6.1
 * Author:            Softminal
 * Author URI:        https://softminal.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       imagecraft-ai-alt-text-file-renamer-image-seo
 * Domain Path:       /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . 'config/constants.php';

// Load shipped translations from /languages. Without this call the bundled
// .mo files are never read — WP auto-loading covers ONLY wp.org language
// packs, which are generated per-locale once community translation passes
// ~90% on translate.wordpress.org. We bundle complete catalogs for 8 locales
// that have no language pack (yet); packs still take priority when present.
// Hooked on init per the WP 6.7+ "textdomain loaded too early" requirement.
add_action('init', static function () {
    // phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- Required: translations are BUNDLED in /languages; auto-loading only covers wp.org language packs, which do not exist for these locales.
    load_plugin_textdomain(
        'imagecraft-ai-alt-text-file-renamer-image-seo',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages'
    );
});
require_once ICAIS_DIR . 'includes/Install.php';
require_once ICAIS_DIR . 'includes/App.php';
require_once ICAIS_DIR . 'includes/Services/Database.php';

use Softminal\ImageCraft\App;
use Softminal\ImageCraft\Services\Database;



Database::init(__FILE__);

// Translations are loaded automatically by WordPress 4.6+ for plugins hosted on WordPress.org.

function icais_init() {
    $app = App::getInstance();
    $app->run();
}
add_action('plugins_loaded', 'icais_init');
