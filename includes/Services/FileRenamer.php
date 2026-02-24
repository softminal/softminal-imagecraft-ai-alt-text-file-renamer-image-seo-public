<?php

namespace Softminal\ImageCraft\Services;

if (!defined('ABSPATH')) {
    exit;
}

class FileRenamer
{
    private const MAX_FILENAME_LENGTH = 120;

    /**
     * Build a safe slug-like filename (no extension).
     */
    public function generateFilename(string $altText, ?int $maxLength = null): string
    {
        if (empty($altText)) {
            $altText = 'image';
        }

        $maxLength = $maxLength ?? self::MAX_FILENAME_LENGTH;

        $text = strtolower($altText);

        // keep only a-z, 0-9, spaces and dashes
        $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
        $text = preg_replace('/\s+/', '-', $text);
        $text = preg_replace('/-+/', '-', $text);
        $text = trim($text, '-');

        if (strlen($text) > $maxLength) {
            $text = substr($text, 0, $maxLength);
            $text = trim($text, '-');
        }

        if (empty($text)) {
            $text = 'image';
        }

        return $text;
    }

    /**
     * Public API: Rename an image attachment file based on alt text or a custom filename.
     */
    public function rename(int $attachmentId, ?string $customFilename = null): array
    {
        if (!wp_attachment_is_image($attachmentId)) {
            return ['success' => false, 'error' => 'Attachment is not an image'];
        }

        $customFilename = sanitize_text_field($customFilename ?? '');

        if (!empty($customFilename)) {
            $filename = $this->generateFilename($customFilename);
        } else {
            $altText = (string) get_post_meta($attachmentId, '_wp_attachment_image_alt', true);
            if (empty($altText)) {
                return ['success' => false, 'error' => 'No alt text available for this image'];
            }
            $filename = $this->generateFilename($altText);
        }

        return $this->performRename($attachmentId, $filename);
    }

    /**
     * Preview only.
     */
    public function previewRename(int $attachmentId): array
    {
        if (!wp_attachment_is_image($attachmentId)) {
            return ['success' => false, 'error' => 'Attachment is not an image'];
        }

        $altText          = (string) get_post_meta($attachmentId, '_wp_attachment_image_alt', true);
        $currentFile      = get_attached_file($attachmentId);
        $currentFilename  = $currentFile ? basename($currentFile) : '';
        $extension        = $currentFile ? (pathinfo($currentFile, PATHINFO_EXTENSION) ?: 'jpg') : 'jpg';

        $newBaseFilename  = $this->generateFilename($altText ?: 'image');
        $newFilename      = $newBaseFilename . '.' . strtolower($extension);

        return [
            'success'           => true,
            'current_filename'  => $currentFilename,
            'proposed_filename' => $newFilename,
            'based_on'          => $altText ?: '(no alt text - using default)',
            'alt_text_available'=> !empty($altText),
        ];
    }

    /**
     * WP_Filesystem bootstrap.
     */
    private function getFilesystem()
    {
        if (!function_exists('WP_Filesystem')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        global $wp_filesystem;

        if (!$wp_filesystem) {
            WP_Filesystem();
        }

        return $wp_filesystem ?: null;
    }

    /**
     * Core rename logic.
     */
    private function performRename(int $attachmentId, string $newBaseName): array
    {
        $file = get_attached_file($attachmentId);
        if (empty($file)) {
            return ['success' => false, 'error' => 'Original file not found'];
        }

        $fs = $this->getFilesystem();
        if (!$fs) {
            return ['success' => false, 'error' => 'Filesystem API not available'];
        }

        if (!$fs->exists($file)) {
            return ['success' => false, 'error' => 'Original file not found'];
        }

        $extension = pathinfo($file, PATHINFO_EXTENSION);
        $extension = $extension ? strtolower($extension) : 'jpg';

        $uploadDir = wp_upload_dir();
        $baseDir   = rtrim((string) $uploadDir['basedir'], '/');

        // Build the relative path for the current file
        $normalizedFile = wp_normalize_path($file);
        $normalizedBase = wp_normalize_path($baseDir);

        $currentRelativePath = ltrim(str_replace($normalizedBase, '', $normalizedFile), '/');
        $currentDir          = dirname($currentRelativePath);
        if ($currentDir === '.' || $currentDir === DIRECTORY_SEPARATOR) {
            $currentDir = '';
        }

        $newFilename = $newBaseName . '.' . $extension;

        // Target path (keep same directory)
        $newFilePath = $baseDir . (!empty($currentDir) ? '/' . $currentDir : '') . '/' . $newFilename;
        $newFilePath = wp_normalize_path($newFilePath);

        // Handle duplicates
        if ($fs->exists($newFilePath)) {
            $newBaseName = $this->handleDuplicateFilename(dirname($file), $newBaseName, $extension, $fs);
            $newFilename = $newBaseName . '.' . $extension;
            $newFilePath = wp_normalize_path(dirname($file) . '/' . $newFilename);
        }

        // Verify the new path is still within wp_upload_dir (prevents path traversal)
        $normalizedNewPath = wp_normalize_path(realpath(dirname($newFilePath)) ?: dirname($newFilePath));
        if (strpos($normalizedNewPath, $normalizedBase) !== 0) {
            return ['success' => false, 'error' => 'Rename target is outside the uploads directory'];
        }

        $oldUrl = (string) wp_get_attachment_url($attachmentId);

        // Ensure destination directory exists
        $destDir = dirname($newFilePath);
        if (!$fs->is_dir($destDir)) {
            if (!$fs->mkdir($destDir)) {
                return ['success' => false, 'error' => 'Failed to create destination directory'];
            }
        }

        // Move (rename) main file
        if (!$fs->move($file, $newFilePath, true)) {
            return ['success' => false, 'error' => 'Failed to rename file via WP_Filesystem'];
        }

        // Build new relative path (for _wp_attached_file)
        $newRelativePath = (!empty($currentDir) ? $currentDir . '/' : '') . $newFilename;

        // Update attached file reference
        update_attached_file($attachmentId, $newRelativePath);

        // Update attachment metadata 'file' as well (important for WP)
        $metadata = wp_get_attachment_metadata($attachmentId);
        if (is_array($metadata)) {
            $metadata['file'] = $newRelativePath;
            wp_update_attachment_metadata($attachmentId, $metadata);
        }

        // Rename thumbnails / intermediate sizes
        $this->renameThumbnails($attachmentId, $file, $newFilePath, $newBaseName, $extension, $fs);

        // New URL
        $newUrl = rtrim((string) $uploadDir['baseurl'], '/') . '/' . ltrim($newRelativePath, '/');

        // Update references
        $this->updateContentReferences($attachmentId, $oldUrl, $newUrl, $newFilename);
        $this->updatePostMetaReferences($attachmentId, $oldUrl, $newUrl, $newFilename);

        clean_post_cache($attachmentId);

        return [
            'success'      => true,
            'old_filename' => basename($file),
            'new_filename' => $newFilename,
            'old_url'      => $oldUrl,
            'new_url'      => $newUrl,
        ];
    }

    /**
     * Rename intermediate sizes and update metadata sizes[*][file].
     *
     * IMPORTANT: WP filenames for sizes are usually like:
     *   original: my-image.jpg
     *   thumb:    my-image-150x150.jpg
     * So we preserve the "-150x150" suffix instead of using "$sizeKey".
     */
    private function renameThumbnails(
        int $attachmentId,
        string $oldFile,
        string $newFile,
        string $newBase,
        string $extension,
        $fs
    ): void {
        $metadata = wp_get_attachment_metadata($attachmentId);

        if (empty($metadata['sizes']) || !is_array($metadata['sizes'])) {
            return;
        }

        $oldDir = dirname($oldFile);
        $newDir = dirname($newFile);

        foreach ($metadata['sizes'] as $sizeKey => $sizeData) {
            if (empty($sizeData['file'])) {
                continue;
            }

            $oldSizeFilename = (string) $sizeData['file'];
            $oldSizePath     = wp_normalize_path($oldDir . '/' . $oldSizeFilename);

            if (!$fs->exists($oldSizePath)) {
                continue;
            }

            // Extract suffix from old size filename: "oldname-150x150" => "-150x150"
            $oldNoExt = (string) pathinfo($oldSizeFilename, PATHINFO_FILENAME);

            $suffix = '';
            $lastDashPos = strrpos($oldNoExt, '-');
            if ($lastDashPos !== false) {
                $suffix = substr($oldNoExt, $lastDashPos); // includes '-150x150'
            }

            $newSizeFilename = $newBase . $suffix . '.' . $extension;
            $newSizePath     = wp_normalize_path($newDir . '/' . $newSizeFilename);

            // Handle duplicates for size files too
            if ($fs->exists($newSizePath)) {
                $newSizeBase = $this->handleDuplicateFilename($newDir, $newBase . $suffix, $extension, $fs);
                $newSizeFilename = $newSizeBase . '.' . $extension;
                $newSizePath     = wp_normalize_path($newDir . '/' . $newSizeFilename);
            }

            if ($fs->move($oldSizePath, $newSizePath, true)) {
                $metadata['sizes'][$sizeKey]['file'] = $newSizeFilename;
            }
        }

        wp_update_attachment_metadata($attachmentId, $metadata);
    }

    /**
     * Update post content references.
     *
     * Safer approach:
     *  - replace full URL old->new (best)
     *  - also replace basename as fallback (some content stores only basename)
     */
    private function updateContentReferences(int $attachmentId, string $oldUrl, string $newUrl, string $newFilename): void
    {
        global $wpdb;

        $oldBasename = basename($oldUrl);

        // Replace full URL
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bulk REPLACE across all posts; caching not applicable
        $wpdb->query(

            $wpdb->prepare(
                "UPDATE {$wpdb->posts}
                 SET post_content = REPLACE(post_content, %s, %s)
                 WHERE post_content LIKE %s
                 AND ID != %d
                 AND post_type NOT IN ('attachment', 'nav_menu_item')",
                $oldUrl,
                $newUrl,
                '%' . $wpdb->esc_like($oldUrl) . '%',
                $attachmentId
            )
        );

        // Replace basename fallback
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bulk REPLACE across all posts; caching not applicable
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->posts}
                 SET post_content = REPLACE(post_content, %s, %s)
                 WHERE post_content LIKE %s
                 AND ID != %d
                 AND post_type NOT IN ('attachment', 'nav_menu_item')",
                $oldBasename,
                $newFilename,
                '%' . $wpdb->esc_like($oldBasename) . '%',
                $attachmentId
            )
        );
    }

    /**
     * Update post meta references.
     */
    private function updatePostMetaReferences(int $attachmentId, string $oldUrl, string $newUrl, string $newFilename): void
    {
        global $wpdb;

        $oldBasename = basename($oldUrl);

        // Find meta rows that contain old URL OR old basename
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time search for meta rows containing old URL; result set varies per rename
        $metaRows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT post_id, meta_key, meta_value
                 FROM {$wpdb->postmeta}
                 WHERE meta_value LIKE %s OR meta_value LIKE %s",
                '%' . $wpdb->esc_like($oldUrl) . '%',
                '%' . $wpdb->esc_like($oldBasename) . '%'
            )
        );

        if (empty($metaRows)) {
            return;
        }

        foreach ($metaRows as $row) {
            // Don't touch alt text itself
            if ($row->meta_key === '_wp_attachment_image_alt') {
                continue;
            }

            $value = (string) $row->meta_value;

            $newMetaValue = str_replace($oldUrl, $newUrl, $value);
            $newMetaValue = str_replace($oldBasename, $newFilename, $newMetaValue);

            if ($newMetaValue === $value) {
                continue;
            }

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Updating meta rows with new URL references; each row is unique
            $wpdb->update(
                $wpdb->postmeta,
                // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
                ['meta_value' => $newMetaValue],
                [
                    'post_id' => (int) $row->post_id,
                    // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
                    'meta_key' => (string) $row->meta_key,
                ]
            );
        }
    }

    /**
     * Ensure unique name.
     */
    private function handleDuplicateFilename(string $dir, string $filename, string $extension, $fs): string
    {
        $dir = rtrim(wp_normalize_path($dir), '/');

        $counter = 1;
        $newName = $filename;

        while ($fs->exists($dir . '/' . $newName . '.' . $extension)) {
            $newName = $filename . '-' . $counter;
            $counter++;
        }

        return $newName;
    }
}
