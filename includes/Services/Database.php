<?php

namespace WpAiAltText\Includes\Services;

/**
 * Database class for handling database operations.
 *
 * This class is responsible for managing the database operations related to the plugin.
 * It includes methods for activating, deactivating, and uninstalling the plugin.
 *
 * @package WpAiAltText\Includes\Services
 */
if (!defined('ABSPATH')) {
    exit;
}

use WpAiAltText\Includes\Install;

class Database 
{
    private static $mainFile;

    public static function init($mainFile)
    {
        self::$mainFile = $mainFile;
        self::registerHooks();
    }

    private static function registerHooks()
    {
        register_activation_hook(self::$mainFile, [Install::class, 'activate']);
        register_deactivation_hook(self::$mainFile, [Install::class, 'deactivate']);
        register_uninstall_hook(self::$mainFile, [Install::class, 'uninstall']);
    }
}