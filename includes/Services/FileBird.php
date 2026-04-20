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

        global $wpdb;

        $createdBy = (int) apply_filters('fbv_folder_created_by', 0);
        $folder = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT fbv.id, fbv.name
                 FROM " . self::folderTable() . " AS fbv
                 INNER JOIN " . self::relationTable() . " AS fbva ON fbv.id = fbva.folder_id
                 WHERE fbva.attachment_id = %d AND fbv.created_by = %d
                 ORDER BY fbv.ord ASC
                 LIMIT 1",
                $attachmentId,
                $createdBy
            )
        );

        if (!$folder) {
            return null;
        }

        return [
            'id' => (int) $folder->id,
            'name' => $folder->name,
            'slug' => sanitize_title($folder->name),
        ];
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

        global $wpdb;

        $ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT attachment_id
                 FROM " . self::relationTable() . "
                 WHERE folder_id = %d",
                $folderId
            )
        );

        return array_map('intval', $ids ?: []);
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

        global $wpdb;

        $createdBy = (int) apply_filters('fbv_folder_created_by', 0);
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT fbv.id, fbv.name, fbv.ord, COUNT(posts.ID) AS count
                 FROM " . self::folderTable() . " AS fbv
                 LEFT JOIN " . self::relationTable() . " AS fbva ON fbv.id = fbva.folder_id
                 LEFT JOIN {$wpdb->posts} AS posts
                    ON posts.ID = fbva.attachment_id
                    AND posts.post_type = 'attachment'
                    AND posts.post_status = 'inherit'
                    AND posts.post_mime_type LIKE 'image/%'
                 WHERE fbv.created_by = %d
                 GROUP BY fbv.id, fbv.name, fbv.ord
                 HAVING count > 0
                 ORDER BY fbv.ord ASC, fbv.name ASC",
                $createdBy
            )
        );

        $folders = [];
        foreach ($rows as $row) {
            $folders[] = [
                'id' => (int) $row->id,
                'name' => $row->name,
                'slug' => sanitize_title($row->name),
                'count' => (int) $row->count,
            ];
        }

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
