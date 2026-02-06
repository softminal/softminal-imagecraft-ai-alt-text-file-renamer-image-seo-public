<?php

if (!defined('ABSPATH')) {
    exit;
}

// Plugin Basic Information
define('SATAI_VERSION', '1.0.0');
define('SATAI_DIR', plugin_dir_path(dirname(__FILE__)));
define('SATAI_URL', plugin_dir_url(dirname(__FILE__)));
define('SATAI_PLUGIN_BASE', plugin_basename(dirname(__FILE__)));
define('SATAI_PLUGIN_NAME', 'smart-alt-text-ai');

// Database Tables
define('SATAI_TABLE_PREFIX', 'satai_');

// API Information
define('SATAI_API_VERSION', 'v1');
define('SATAI_API_NAMESPACE', 'smart-alt-text-ai/' . SATAI_API_VERSION);

define('SATAI_DEV_MODE', false);
