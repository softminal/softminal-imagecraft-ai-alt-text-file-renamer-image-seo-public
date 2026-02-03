<?php

/**
 * Plugin Name:       Smart Ai Alt Text Generator
 * Plugin URI:        https://alttextai.com/
 * Description:       A plugin that generates alt text for images using AI.
 * Version:           1.0.0
 * Author:            WP AI Alt Text
 * Author URI:        https://alttextai.com/
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
