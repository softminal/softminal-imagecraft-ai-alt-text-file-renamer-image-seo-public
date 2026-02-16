<?php

if (!defined('ABSPATH')) {
    exit;
}

// Plugin Basic Information
define('IMGCRAFT_VERSION', '1.0.0');
define('IMGCRAFT_DIR', plugin_dir_path(dirname(__FILE__)));
define('IMGCRAFT_URL', plugin_dir_url(dirname(__FILE__)));
define('IMGCRAFT_PLUGIN_BASE', plugin_basename(dirname(__FILE__)));
define('IMGCRAFT_PLUGIN_NAME', 'imagecraft');

// Database Tables
define('IMGCRAFT_TABLE_PREFIX', 'imgcraft_');

// API Information
define('IMGCRAFT_API_VERSION', 'v1');
define('IMGCRAFT_API_NAMESPACE', 'imagecraft/' . IMGCRAFT_API_VERSION);

define('IMGCRAFT_DEV_MODE', false);
