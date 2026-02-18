<?php

if (!defined('ABSPATH')) {
    exit;
}

// Plugin Basic Information
define('ICAIS_VERSION', '1.0.0');
define('ICAIS_DIR', plugin_dir_path(dirname(__FILE__)));
define('ICAIS_URL', plugin_dir_url(dirname(__FILE__)));
define('ICAIS_PLUGIN_BASE', plugin_basename(dirname(__FILE__)));
define('ICAIS_PLUGIN_NAME', 'icais');

// Database Tables
define('ICAIS_TABLE_PREFIX', 'icais_');

// API Information
define('ICAIS_API_VERSION', 'v1');
define('ICAIS_API_NAMESPACE', 'icais/' . ICAIS_API_VERSION);

define('ICAIS_DEV_MODE', false);
