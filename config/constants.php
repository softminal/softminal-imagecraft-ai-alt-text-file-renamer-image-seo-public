<?php

if (!defined('ABSPATH')) {
    exit;
}

// Plugin Basic Information
define('ICAIS_VERSION', '1.2.0');
define('ICAIS_DIR', plugin_dir_path(dirname(__FILE__)));
define('ICAIS_URL', plugin_dir_url(dirname(__FILE__)));
define('ICAIS_PLUGIN_BASE', plugin_basename(dirname(__FILE__)));
define('ICAIS_PLUGIN_NAME', 'icais');

// Database Tables
define('ICAIS_TABLE_PREFIX', 'icais_');

// API Information
define('ICAIS_API_VERSION', 'v1');
define('ICAIS_API_NAMESPACE', 'icais/' . ICAIS_API_VERSION);

// Dev mode: auto-detect based on presence of a .dev file in the plugin root.
// Create a file named ".dev" in the plugin directory to enable Vite dev server.
// This file MUST NOT be shipped in production builds.
define('ICAIS_DEV_MODE', file_exists(ICAIS_DIR . '.dev'));
