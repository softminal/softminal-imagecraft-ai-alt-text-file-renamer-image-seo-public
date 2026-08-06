<?php

/**
 * Plugin Name:       ImageCraft - AI Alt Text, Image Compression & WebP
 * Plugin URI:        https://www.softminal.com/products/imagecraft-ai-alt-text-file-renamer-image-seo
 * Description:       All-in-one image toolkit: AI-powered alt text & titles, smart image compression, SEO file renaming, and image SEO audit. Supports WordPress 7.0 native AI Client, Anthropic, OpenAI and Gemini.
 * Version:           1.6.0
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
// .mo files are never read — WP only auto-loads wp.org language packs from
// WP_LANG_DIR (which still take priority when present). Hooked on init per
// the WP 6.7+ "textdomain loaded too early" requirement.
add_action('init', static function () {
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
