<?php

namespace WpAiAltText\Includes;

/**
 * Install class for handling plugin installation
 */
class Install
{
    private static $wpdb;
    private static $charset_collate;

    /**
     * Initialize static properties
     */
    private static function init()
    {
        global $wpdb;
        self::$wpdb = $wpdb;
        self::$charset_collate = $wpdb->get_charset_collate();
    }

    /**
     * Activate the plugin
     */
    public static function activate()
    {
        self::init();
        if (!self::createTables()) {
            wp_die('Could not install WP AI Alt Text plugin. Please check error logs.');
        }
    }

    /**
     * Create database tables
     */
    private static function createTables()
    {
        $sql = [];

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        try {
            foreach ($sql as $query) {
                $results = dbDelta($query);
                if ($results === false) {
                    error_log('WP AI Alt Text Installation Error: Failed to create table');
                    return false;
                }
            }

            update_option('wp_ai_alt_text_db_version', '1.0.0');
            return true;
        } catch (\Exception $e) {
            error_log('WP AI Alt Text Installation Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Deactivate the plugin
     */
    public static function deactivate()
    {
        delete_option('wp_ai_alt_text_db_version');
    }

    /**
     * Uninstall the plugin
     */
    public static function uninstall()
    {
        self::init();

        try {

            // Clean up options
            delete_option('wp_ai_alt_text_db_version');
        } catch (\Exception $e) {
            error_log('WP AI Alt Text Uninstall Error: ' . $e->getMessage());
        }
    }
}
