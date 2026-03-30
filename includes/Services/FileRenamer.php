<?php

namespace Softminal\ImageCraft\Services;

if (!defined('ABSPATH')) {
    exit;
}

class FileRenamer
{
    private const DEFAULT_MAX_FILENAME_LENGTH = 120;

    /**
     * Build a safe slug-like filename (no extension).
     */
    public function generateFilename(string $altText, ?int $maxLength = null): string
    {
        if (empty($altText)) {
            $altText = 'image';
        }

        $maxLength = $maxLength ?? (int) Settings::get('icais_rename_max_length', self::DEFAULT_MAX_FILENAME_LENGTH);

        // Transliterate accented Latin characters to ASCII equivalents
        // e.g. "caffè" → "caffe", "über" → "uber", "piñata" → "pinata"
        $text = remove_accents($altText);
        $text = strtolower($text);

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
     *
     * Respects icais_rename_mode setting:
     *   'title_only' — updates post_title only, no file rename (safe, no URL change)
     *   'title_and_filename' — updates post_title AND renames file (changes URL)
     */
    /**
     * @param bool $forceFileRename When true, always rename the file regardless of rename_mode setting.
     *                              Used by AI-powered rename where user explicitly clicked "Rename File".
     */
    public function rename(int $attachmentId, ?string $customFilename = null, bool $forceFileRename = false): array
    {
        if (!wp_attachment_is_image($attachmentId)) {
            return ['success' => false, 'error' => 'Attachment is not an image'];
        }

        // Resolve the title/filename source
        $customFilename = $customFilename ?? '';
        $titleSource = $this->resolveTitleSource($attachmentId, $customFilename);

        if ($titleSource === null) {
            return ['success' => false, 'error' => 'No alt text or title available for this image'];
        }

        $filename = $this->generateFilename($titleSource);

        // Skip mode check when force rename is requested (AI-powered rename button)
        if (!$forceFileRename) {
            $renameMode = Settings::get('icais_rename_mode', 'title_only');
            if ($renameMode === 'title_only') {
                return $this->performTitleOnlyUpdate($attachmentId, $titleSource);
            }
        }

        // title_and_filename mode: skip if filename already matches
        $currentFile = get_attached_file($attachmentId);
        if ($currentFile && pathinfo($currentFile, PATHINFO_FILENAME) === $filename) {
            return [
                'success'      => true,
                'already_named' => true,
                'old_filename' => basename($currentFile),
                'new_filename' => basename($currentFile),
            ];
        }

        return $this->performRename($attachmentId, $filename);
    }

    /**
     * Resolve the best available title source for an attachment.
     *
     * Priority: custom filename > AI-generated title > alt text
     */
    private function resolveTitleSource(int $attachmentId, string $customFilename): ?string
    {
        if (!empty($customFilename)) {
            return $customFilename;
        }

        // Priority 1: AI-generated title
        $aiTitle = (string) get_post_meta($attachmentId, '_icais_generated_title', true);
        if (!empty($aiTitle)) {
            return $aiTitle;
        }

        // Priority 2: Alt text
        $altText = (string) get_post_meta($attachmentId, '_wp_attachment_image_alt', true);
        if (!empty($altText)) {
            return $altText;
        }

        return null;
    }

    /**
     * Update post_title only (no file rename, no URL change).
     */
    private function performTitleOnlyUpdate(int $attachmentId, string $titleSource): array
    {
        $newTitle = $titleSource;

        // If title came from alt text (not AI-generated), convert slug to readable
        $aiTitle = (string) get_post_meta($attachmentId, '_icais_generated_title', true);
        if (empty($aiTitle)) {
            $slug = $this->generateFilename($titleSource);
            $newTitle = ucwords(str_replace('-', ' ', $slug));
        }

        // Cap at 80 chars, trim to last full word
        if (mb_strlen($newTitle) > 80) {
            $newTitle = mb_substr($newTitle, 0, 80);
            $newTitle = preg_replace('/\s+\S*$/', '', $newTitle);
        }

        // Check if title is already the same
        $post = get_post($attachmentId);
        if ($post && $post->post_title === $newTitle) {
            return [
                'success'      => true,
                'already_named' => true,
                'old_filename' => basename(get_attached_file($attachmentId) ?: ''),
                'new_filename' => basename(get_attached_file($attachmentId) ?: ''),
                'new_title'    => $newTitle,
            ];
        }

        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct update to avoid wp_update_post hooks
        $wpdb->update(
            $wpdb->posts,
            ['post_title' => $newTitle],
            ['ID' => $attachmentId],
            ['%s'],
            ['%d']
        );

        clean_post_cache($attachmentId);

        return [
            'success'      => true,
            'new_title'    => $newTitle,
            'old_filename' => basename(get_attached_file($attachmentId) ?: ''),
            'new_filename' => basename(get_attached_file($attachmentId) ?: ''),
        ];
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

        // Rename thumbnails / intermediate sizes (returns old→new filename map)
        $sizeFilenameMap = $this->renameThumbnails($attachmentId, $file, $newFilePath, $newBaseName, $extension, $fs);

        // New URL
        $newUrl = rtrim((string) $uploadDir['baseurl'], '/') . '/' . ltrim($newRelativePath, '/');

        // Update references in post content and post meta
        $this->updateContentReferences($attachmentId, $oldUrl, $newUrl, $newFilename, $sizeFilenameMap);
        $this->updatePostMetaReferences($attachmentId, $oldUrl, $newUrl, $newFilename, $sizeFilenameMap);

        // Update attachment title (slug → readable, capped at 80 chars) and guid
        $newTitle = ucwords(str_replace('-', ' ', $newBaseName));
        if (mb_strlen($newTitle) > 80) {
            $newTitle = mb_substr($newTitle, 0, 80);
            $newTitle = preg_replace('/\s+\S*$/', '', $newTitle); // trim to last full word
        }

        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct update to avoid wp_update_post hooks/side-effects
        $wpdb->update(
            $wpdb->posts,
            [
                'post_title' => $newTitle,
                'guid'       => $newUrl,
            ],
            ['ID' => $attachmentId],
            ['%s', '%s'],
            ['%d']
        );

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
    ): array {
        $filenameMap = [];
        $metadata = wp_get_attachment_metadata($attachmentId);

        if (empty($metadata['sizes']) || !is_array($metadata['sizes'])) {
            return $filenameMap;
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

            // Extract WordPress size suffix: "-150x150", "-scaled", "-rotated"
            $oldNoExt = (string) pathinfo($oldSizeFilename, PATHINFO_FILENAME);

            $suffix = '';
            if (preg_match('/(-\d+x\d+|-scaled|-rotated)$/', $oldNoExt, $matches)) {
                $suffix = $matches[1];
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
                $filenameMap[$oldSizeFilename] = $newSizeFilename;
                $metadata['sizes'][$sizeKey]['file'] = $newSizeFilename;
            }
        }

        wp_update_attachment_metadata($attachmentId, $metadata);

        return $filenameMap;
    }

    /**
     * Update post content references.
     *
     * Approach:
     *  1. Replace full URL old→new (most specific, safest)
     *  2. Replace path-qualified basenames (/old.jpg → /new.jpg) to catch relative paths
     *     without matching plain text occurrences of the filename
     */
    private function updateContentReferences(int $attachmentId, string $oldUrl, string $newUrl, string $newFilename, array $sizeFilenameMap = []): void
    {
        global $wpdb;

        $oldBasename = basename($oldUrl);

        // 1. Replace full URL of main file (exact, no false positives)
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

        // 2. Replace path-qualified basename for main file (catches relative paths like /uploads/.../old.jpg)
        //    The "/" prefix prevents matching plain text occurrences of the filename
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bulk REPLACE across all posts; caching not applicable
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->posts}
                 SET post_content = REPLACE(post_content, %s, %s)
                 WHERE post_content LIKE %s
                 AND ID != %d
                 AND post_type NOT IN ('attachment', 'nav_menu_item')",
                '/' . $oldBasename,
                '/' . $newFilename,
                '%/' . $wpdb->esc_like($oldBasename) . '%',
                $attachmentId
            )
        );

        // 3. Replace path-qualified thumbnail/size filenames (fixes responsive images, srcset, sized embeds)
        foreach ($sizeFilenameMap as $oldSizeFile => $newSizeFile) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bulk REPLACE for thumbnail references in post content
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$wpdb->posts}
                     SET post_content = REPLACE(post_content, %s, %s)
                     WHERE post_content LIKE %s
                     AND ID != %d
                     AND post_type NOT IN ('attachment', 'nav_menu_item')",
                    '/' . $oldSizeFile,
                    '/' . $newSizeFile,
                    '%/' . $wpdb->esc_like($oldSizeFile) . '%',
                    $attachmentId
                )
            );
        }
    }

    /**
     * Update post meta references.
     * Handles both plain strings and serialized data safely.
     */
    private function updatePostMetaReferences(int $attachmentId, string $oldUrl, string $newUrl, string $newFilename, array $sizeFilenameMap = []): void // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed -- $attachmentId kept for API symmetry
    {
        global $wpdb;

        $oldBasename = basename($oldUrl);

        // Build all old→new replacement pairs (longest first to avoid partial matches)
        $replacements = [];

        // Full URLs for main file
        $replacements[$oldUrl] = $newUrl;

        // Full URLs for each thumbnail size
        $baseUrl    = dirname($oldUrl);
        $newBaseUrl = dirname($newUrl);
        foreach ($sizeFilenameMap as $oldSizeFile => $newSizeFile) {
            $replacements[$baseUrl . '/' . $oldSizeFile] = $newBaseUrl . '/' . $newSizeFile;
        }

        // Basename fallbacks (after full URLs so longer strings match first)
        $replacements[$oldBasename] = $newFilename;
        foreach ($sizeFilenameMap as $oldSizeFile => $newSizeFile) {
            $replacements[$oldSizeFile] = $newSizeFile;
        }

        // Search broadly using the old base name stem (catches main file + all sizes)
        $oldBasenameNoExt = pathinfo($oldBasename, PATHINFO_FILENAME);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time search for meta rows containing old filename stem
        $metaRows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT meta_id, meta_key, meta_value
                 FROM {$wpdb->postmeta}
                 WHERE meta_value LIKE %s",
                '%' . $wpdb->esc_like($oldBasenameNoExt) . '%'
            )
        );

        if (empty($metaRows)) {
            return;
        }

        // Skip meta keys handled elsewhere or that must not be modified
        $skipKeys = ['_wp_attachment_image_alt', '_wp_attachment_metadata'];

        foreach ($metaRows as $row) {
            if (in_array($row->meta_key, $skipKeys, true)) {
                continue;
            }

            $value    = (string) $row->meta_value;
            $newValue = $this->replaceUrlsInMetaValue($value, $replacements);

            if ($newValue === $value) {
                continue;
            }

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Update by meta_id to avoid overwriting duplicate meta_key rows
            $wpdb->update(
                $wpdb->postmeta,
                // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
                ['meta_value' => $newValue],
                ['meta_id' => (int) $row->meta_id]
            );
        }
    }

    /**
     * Replace URLs in a meta value, handling serialized PHP data safely.
     * Plain strings (including JSON) use direct str_replace.
     * Serialized arrays/objects are unserialized first to preserve length markers.
     */
    private function replaceUrlsInMetaValue(string $value, array $replacements): string
    {
        $unserialized = @maybe_unserialize($value);

        // Serialized array or object: recurse, replace, re-serialize (preserves string lengths)
        if (is_array($unserialized) || is_object($unserialized)) {
            $modified = $this->replaceUrlsRecursive($unserialized, $replacements);
            return maybe_serialize($modified);
        }

        // Plain string or JSON: direct replacement is safe
        $newValue = $value;
        foreach ($replacements as $old => $new) {
            $newValue = str_replace($old, $new, $newValue);
        }
        return $newValue;
    }

    /**
     * Recursively replace URL strings inside arrays and objects.
     */
    private function replaceUrlsRecursive($data, array $replacements)
    {
        if (is_array($data)) {
            foreach ($data as $key => $val) {
                $data[$key] = $this->replaceUrlsRecursive($val, $replacements);
            }
        } elseif (is_object($data)) {
            foreach (get_object_vars($data) as $key => $val) {
                $data->$key = $this->replaceUrlsRecursive($val, $replacements);
            }
        } elseif (is_string($data)) {
            foreach ($replacements as $old => $new) {
                $data = str_replace($old, $new, $data);
            }
        }
        return $data;
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
