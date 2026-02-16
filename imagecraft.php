<?php

/**
 * Plugin Name:       ImageCraft – AI Alt Text, File Renamer & Image SEO
 * Plugin URI:        https://github.com/softminal/imagecraft
 * Description:       Generate AI-powered alt text, rename files for SEO, and audit image accessibility using your own Anthropic, OpenAI, or Gemini API key.
 * Version:           1.0.0
 * Author:            Softminal
 * Author URI:        https://softminal.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       imagecraft
 * Domain Path:       /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . 'config/constants.php';
require_once IMGCRAFT_DIR . 'includes/Install.php';
require_once IMGCRAFT_DIR . 'includes/App.php';
require_once IMGCRAFT_DIR . 'includes/Services/Database.php';

use ImageCraft\Includes\App;
use ImageCraft\Includes\Services\Database;



Database::init(__FILE__);

function imgcraft_init() {
    $app = App::getInstance();
    $app->run();
}
add_action('plugins_loaded', 'imgcraft_init');

add_action('init', function() {
    load_plugin_textdomain('imagecraft', false, dirname(plugin_basename(__FILE__)) . '/languages');
});
