<?php

/**
 * Plugin Name:       Smart Alt Text AI
 * Plugin URI:        https://github.com/softminal/smart-alt-text-ai
 * Description:       A plugin that generates alt text for images using AI.
 * Version:           1.0.0
 * Author:            Softminal
 * Author URI:        https://github.com/softminal
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       smart-alt-text-ai
 * Domain Path:       /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . 'config/constants.php';
require_once SATAI_DIR . 'includes/Install.php';
require_once SATAI_DIR . 'includes/App.php';
require_once SATAI_DIR . 'includes/Services/Database.php';

use SmartAltTextAi\Includes\App;
use SmartAltTextAi\Includes\Services\Database;



Database::init(__FILE__);

function satai_init() {
    $app = App::getInstance();
    $app->run();
}
add_action('plugins_loaded', 'satai_init');

add_action('init', function() {
    load_plugin_textdomain('smart-alt-text-ai', false, dirname(plugin_basename(__FILE__)) . '/languages');
});
