<?php

if (!defined('ABSPATH')) {
    exit;
}

// Plugin Basic Information
define('WP_AI_ALT_TEXT_VERSION', '1.0.0');
define('WP_AI_ALT_TEXT_DIR', plugin_dir_path(dirname(__FILE__)));
define('WP_AI_ALT_TEXT_URL', plugin_dir_url(dirname(__FILE__)));
define('WP_AI_ALT_TEXT_PLUGIN_BASE', plugin_basename(dirname(__FILE__)));
define('WP_AI_ALT_TEXT_PLUGIN_NAME', 'wp-ai-alt-text');

// Database Tables
define('WP_AI_ALT_TEXT_TABLE_PREFIX', 'waiat_');

// API Information
define('WP_AI_ALT_TEXT_API_VERSION', 'v1');
define('WP_AI_ALT_TEXT_API_NAMESPACE', 'wp-ai-alt-text/' . WP_AI_ALT_TEXT_API_VERSION);

// Development Mode
// Set to true to force dev mode, false to force production mode, or null for auto-detect
// Auto-detect checks if Vite dev server is running on port 4000
if (!defined('WP_AI_ALT_TEXT_DEV_MODE')) {
    define('WP_AI_ALT_TEXT_DEV_MODE', null); // null = auto-detect
}