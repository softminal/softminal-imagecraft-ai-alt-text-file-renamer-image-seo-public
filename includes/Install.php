<?php

namespace Softminal\ImageCraft;

if (!defined('ABSPATH')) {
    exit;
}

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
            wp_die(esc_html__('Could not install ImageCraft plugin. Please check error logs.', 'imagecraft-ai-alt-text-file-renamer-image-seo'));
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
            "CREATE TABLE IF NOT EXISTS " . self::$wpdb->prefix . ICAIS_TABLE_PREFIX . "api_settings (
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
            "CREATE TABLE IF NOT EXISTS " . self::$wpdb->prefix . ICAIS_TABLE_PREFIX . "history (
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
            "CREATE TABLE IF NOT EXISTS " . self::$wpdb->prefix . ICAIS_TABLE_PREFIX . "stats (
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
            "CREATE TABLE IF NOT EXISTS " . self::$wpdb->prefix . ICAIS_TABLE_PREFIX . "batch_jobs (
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
            $userId = get_current_user_id();
            foreach ($sql as $query) {
                $results = dbDelta($query);
                if ($results === false) {
                    return false;
                }
            }

            update_option('icais_db_version', '1.0.0');
            add_user_meta($userId, ICAIS_TABLE_PREFIX.'wizard_completed', false);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Deactivate the plugin
     */
    public static function deactivate()
    {
        // Do not delete data on deactivation — users may reactivate
    }

    /**
     * Uninstall the plugin
     */
    public static function uninstall()
    {
        self::init();

        $prefix = self::$wpdb->prefix . ICAIS_TABLE_PREFIX;

        // Drop custom tables
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table prefix from trusted constants
        self::$wpdb->query("DROP TABLE IF EXISTS `{$prefix}api_settings`");
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table prefix from trusted constants
        self::$wpdb->query("DROP TABLE IF EXISTS `{$prefix}history`");
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table prefix from trusted constants
        self::$wpdb->query("DROP TABLE IF EXISTS `{$prefix}stats`");
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table prefix from trusted constants
        self::$wpdb->query("DROP TABLE IF EXISTS `{$prefix}batch_jobs`");

        // Delete all plugin options — dynamically from Settings defaults
        $optionKeys = array_keys(\Softminal\ImageCraft\Services\Settings::getDefaults());

        // Legacy keys no longer in defaults but may exist in older installations
        $optionKeys[] = 'icais_email_on_complete';

        foreach ($optionKeys as $key) {
            delete_option(ICAIS_TABLE_PREFIX . $key);
        }
        delete_option('icais_db_version');

        // Delete user meta for all users
        delete_metadata('user', 0, ICAIS_TABLE_PREFIX . 'wizard_completed', '', true);
        delete_metadata('user', 0, 'icais_review_notice_dismissed', '', true);

        // Clean up all plugin transients (batch, heartbeat, rate-limit, etc.)
        global $wpdb;
        $transientLike = $wpdb->esc_like('_transient_icais_') . '%';
        $timeoutLike = $wpdb->esc_like('_transient_timeout_icais_') . '%';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                $transientLike,
                $timeoutLike
            )
        );
    }
}
