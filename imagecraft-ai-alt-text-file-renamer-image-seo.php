<?php

/**
 * Plugin Name:       ImageCraft Alt Text, File Renaming and Image SEO
 * Plugin URI:        https://www.softminal.com/products/imagecraft-ai-alt-text-file-renamer-image-seo
 * Description:       Generate AI-powered alt text, rename files for SEO, and audit image accessibility. Supports WordPress 7.0 native AI Client Anthropic, OpenAI and Gemini.
 * Version:           1.3.9
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
