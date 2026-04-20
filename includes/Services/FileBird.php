<?php

namespace Softminal\ImageCraft\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * FileBird integration service
 *
 * Provides methods to interact with FileBird plugin's folder system.
 * FileBird uses a custom taxonomy 'attachment_folder' to organize media files.
 */
class FileBird
{
    /**
     * Cache group for FileBird-related lookups.
     *
     * @var string
     */
    private static string $cacheGroup = 'icais_filebird';

    /**
     * Get the folder table name.
     */
    private static function folderTable(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'fbv';
    }

    /**
     * Get the attachment-folder relation table name.
     */
    private static function relationTable(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'fbv_attachment_folder';
    }

    /**
     * Get validated and escaped FileBird table names for SQL interpolation.
     *
     * @return array<string, string>|null
     */
    private static function getSafeTables(): ?array
    {
        $folderTable = self::folderTable();
        $relationTable = self::relationTable();

        if (!preg_match('/^[A-Za-z0-9_]+$/', $folderTable) || !preg_match('/^[A-Za-z0-9_]+$/', $relationTable)) {
            return null;
        }

        return [
            'folder' => esc_sql($folderTable),
            'relation' => esc_sql($relationTable),
        ];
    }

    /**
     * Check if FileBird plugin is active
     *
     * @return bool True if FileBird is active, false otherwise
     */
    public static function isActive(): bool
    {
        return class_exists('NV_Folder')
            || class_exists('FileBird')
            || class_exists('\FileBird\Model\Folder')
            || defined('NJFB_PREFIX')
            || defined('NJFB_VERSION');
    }

    /**
     * Get folder information for an attachment
     *
     * @param int $attachmentId Attachment post ID
     * @return array|null Folder data with id, name, slug or null if not found
     */
    public static function getFolderForAttachment(int $attachmentId): ?array
    {
        if (!self::isActive()) {
            return null;
        }

        $tables = self::getSafeTables();
        if (!$tables) {
            return null;
        }

        $folderTable = $tables['folder'];
        $relationTable = $tables['relation'];

        $cacheKey = 'attachment_folder:' . $attachmentId;
        $cached = wp_cache_get($cacheKey, self::$cacheGroup);
        if ($cached !== false) {
            return is_array($cached) ? $cached : null;
        }

        global $wpdb;

        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- FileBird integration uses the upstream plugin's hook name.
        $createdBy = (int) apply_filters('fbv_folder_created_by', 0);
        // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter
        $sql = sprintf(
            'SELECT fbv.id, fbv.name
             FROM `%s` AS fbv
             INNER JOIN `%s` AS fbva ON fbv.id = fbva.folder_id
             WHERE fbva.attachment_id = %%d AND fbv.created_by = %%d
             ORDER BY fbv.ord ASC
             LIMIT 1',
            $folderTable,
            $relationTable
        );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom FileBird tables require direct reads; result is cached below.
        $folder = $wpdb->get_row(
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Identifiers are validated and escaped before sprintf(); values are prepared below.
            $wpdb->prepare($sql, $attachmentId, $createdBy)
        );
        // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter

        if (!$folder) {
            wp_cache_set($cacheKey, null, self::$cacheGroup, MINUTE_IN_SECONDS * 10);
            return null;
        }

        $data = [
            'id' => (int) $folder->id,
            'name' => sanitize_text_field($folder->name),
            'slug' => sanitize_title($folder->name),
        ];

        wp_cache_set($cacheKey, $data, self::$cacheGroup, MINUTE_IN_SECONDS * 10);

        return $data;
    }

    /**
     * Get all attachment IDs in a specific folder
     *
     * @param int $folderId FileBird folder term ID
     * @return array Array of attachment post IDs
     */
    public static function getAttachmentIdsByFolder(int $folderId): array
    {
        if (!self::isActive()) {
            return [];
        }

        $tables = self::getSafeTables();
        if (!$tables) {
            return [];
        }

        $relationTable = $tables['relation'];

        $cacheKey = 'folder_attachment_ids:' . $folderId;
        $cached = wp_cache_get($cacheKey, self::$cacheGroup);
        if ($cached !== false) {
            return is_array($cached) ? $cached : [];
        }

        global $wpdb;

        // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter
        $sql = sprintf(
            'SELECT attachment_id
             FROM `%s`
             WHERE folder_id = %%d',
            $relationTable
        );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom FileBird relation lookups require direct reads; result is cached below.
        $ids = $wpdb->get_col(
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Identifier is validated and escaped before sprintf(); folder ID is prepared below.
            $wpdb->prepare($sql, $folderId)
        );
        // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter

        $attachmentIds = array_map('intval', $ids ?: []);
        wp_cache_set($cacheKey, $attachmentIds, self::$cacheGroup, MINUTE_IN_SECONDS * 10);

        return $attachmentIds;
    }

    /**
     * Build folder context string for AI prompts
     *
     * @param int $attachmentId Attachment post ID
     * @return string|null Context string or null if no folder
     */
    public static function buildFolderContext(int $attachmentId): ?string
    {
        $folder = self::getFolderForAttachment($attachmentId);

        if (!$folder) {
            return null;
        }

        return 'Folder: ' . $folder['name'];
    }

    /**
     * Get all folders with attachment counts
     *
     * @return array Array of folder data arrays
     */
    public static function getFolders(): array
    {
        if (!self::isActive()) {
            return [];
        }

        $tables = self::getSafeTables();
        if (!$tables) {
            return [];
        }

        $folderTable = $tables['folder'];
        $relationTable = $tables['relation'];

        $cacheKey = 'folders';
        $cached = wp_cache_get($cacheKey, self::$cacheGroup);
        if ($cached !== false) {
            return is_array($cached) ? $cached : [];
        }

        global $wpdb;

        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- FileBird integration uses the upstream plugin's hook name.
        $createdBy = (int) apply_filters('fbv_folder_created_by', 0);
        $imageMimeLike = 'image/%';
        // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter
        $sql = sprintf(
            'SELECT fbv.id, fbv.name, fbv.ord, COUNT(posts.ID) AS folder_count
             FROM `%s` AS fbv
             LEFT JOIN `%s` AS fbva ON fbv.id = fbva.folder_id
             LEFT JOIN %s AS posts
                ON posts.ID = fbva.attachment_id
                AND posts.post_type = \'attachment\'
                AND posts.post_status = \'inherit\'
                AND posts.post_mime_type LIKE %%s
             WHERE fbv.created_by = %%d
             GROUP BY fbv.id, fbv.name, fbv.ord
             HAVING folder_count > 0
             ORDER BY fbv.ord ASC, fbv.name ASC',
            $folderTable,
            $relationTable,
            $wpdb->posts
        );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom FileBird folder reads require direct queries; result is cached below.
        $rows = $wpdb->get_results(
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Identifiers are validated and escaped before sprintf(); LIKE wildcard and created_by are prepared below.
            $wpdb->prepare($sql, $imageMimeLike, $createdBy)
        );
        // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter

        $folders = [];
        foreach ($rows as $row) {
            $folders[] = [
                'id' => (int) $row->id,
                'name' => sanitize_text_field($row->name),
                'slug' => sanitize_title($row->name),
                'count' => (int) $row->folder_count,
            ];
        }

        wp_cache_set($cacheKey, $folders, self::$cacheGroup, MINUTE_IN_SECONDS * 10);

        return $folders;
    }

    /**
     * Get attachment count for a specific folder
     *
     * @param int $folderId FileBird folder term ID
     * @return int Number of attachments in folder
     */
    public static function getAttachmentCount(int $folderId): int
    {
        if (!self::isActive()) {
            return 0;
        }

        return count(self::getAttachmentIdsByFolder($folderId));
    }
}
