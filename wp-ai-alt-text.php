<?php

/**
 * Plugin Name:       AI Alt Text Generator
 * Plugin URI:        https://wpaialttext.com/
 * Description:       A plugin that generates alt text for images using AI.
 * Version:           1.0.0
 * Author:            WP AI Alt Text
 * Author URI:        https://wpaialttext.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       wp-ai-alt-text
 * Domain Path:       /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . 'config/constants.php';
require_once WP_AI_ALT_TEXT_DIR . 'includes/Install.php';
require_once WP_AI_ALT_TEXT_DIR . 'includes/App.php';
require_once WP_AI_ALT_TEXT_DIR . 'includes/Services/Database.php';

use WpAiAltText\Includes\App;
use WpAiAltText\Includes\Services\Database;



Database::init(__FILE__);

function wp_ai_alt_text_init() {
    $app = App::getInstance();
    $app->run();
}
add_action('plugins_loaded', 'wp_ai_alt_text_init');