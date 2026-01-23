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
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql = [
            // Table 1: API Settings
            "CREATE TABLE IF NOT EXISTS " . self::$wpdb->prefix . WP_AI_ALT_TEXT_TABLE_PREFIX . "alttext_api_settings (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NOT NULL,
                api_provider VARCHAR(20) NOT NULL,
                api_key_encrypted TEXT NOT NULL,
                encryption_iv VARCHAR(32) NOT NULL,
                is_active BOOLEAN DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY user_provider_unique (user_id, api_provider),
                INDEX idx_user (user_id)
            ) " . self::$charset_collate . ";",

            // Table 2: Generation History
            "CREATE TABLE IF NOT EXISTS " . self::$wpdb->prefix . WP_AI_ALT_TEXT_TABLE_PREFIX . "alttext_history (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                attachment_id BIGINT UNSIGNED NOT NULL,
                original_alt TEXT,
                generated_alt TEXT NOT NULL,
                user_id BIGINT UNSIGNED NOT NULL,
                api_provider VARCHAR(20) NOT NULL,
                tone VARCHAR(20) DEFAULT 'professional',
                status VARCHAR(20) DEFAULT 'pending',
                tokens_used INT DEFAULT 0,
                cost_estimate DECIMAL(10,6) DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_attachment (attachment_id),
                INDEX idx_user (user_id),
                INDEX idx_status (status),
                INDEX idx_created (created_at)
            ) " . self::$charset_collate . ";",

            // Table 3: Usage Statistics
            "CREATE TABLE IF NOT EXISTS " . self::$wpdb->prefix . WP_AI_ALT_TEXT_TABLE_PREFIX . "alttext_stats (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NOT NULL,
                date DATE NOT NULL,
                images_processed INT DEFAULT 0,
                images_accepted INT DEFAULT 0,
                images_rejected INT DEFAULT 0,
                total_cost_estimate DECIMAL(10,4) DEFAULT 0,
                api_provider VARCHAR(20),
                UNIQUE KEY user_date_unique (user_id, date),
                INDEX idx_date (date)
            ) " . self::$charset_collate . ";",

            // Table 4: Batch Jobs
            "CREATE TABLE IF NOT EXISTS " . self::$wpdb->prefix . WP_AI_ALT_TEXT_TABLE_PREFIX . "alttext_batch_jobs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NOT NULL,
                total_images INT NOT NULL,
                processed_images INT DEFAULT 0,
                failed_images INT DEFAULT 0,
                status VARCHAR(20) DEFAULT 'pending',
                started_at DATETIME,
                completed_at DATETIME,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user (user_id),
                INDEX idx_status (status)
            ) " . self::$charset_collate . ";"
        ];

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
