<?php

namespace Softminal\ImageCraft\Services;

use Softminal\ImageCraft\Models\Compression;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Local image compressor.
 *
 * Re-encodes an attachment's full-size image (and regenerates its intermediate
 * sizes) at a configurable quality, optionally downscaling to a max width.
 * The file extension/URL never changes, so no post-content rewriting is needed.
 *
 * Safety: every original is backed up under uploads/icais-originals/ before the
 * destructive write, and restore() puts it back. All image-editor and filesystem
 * work is wrapped so a failure can never fatal the caller.
 */
class ImageCompressor
{
    /** Post meta flag set once an image has been compressed. */
    private const META_FLAG = '_icais_compressed';

    /** Post meta holding the backup manifest for restore. */
    private const META_BACKUP = '_icais_compression_backup';

    /** Post meta holding the latest savings stats (for quick display). */
    private const META_STATS = '_icais_compression_stats';

    /** Image mime types we can safely re-encode. */
    private const SUPPORTED_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    /** Fixed downscale cap (longest edge, px) applied for free-tier users. */
    private const FREE_MAX_DIM = 2048;

    /** Default visually-lossless quality (free fixed; Pro adjustable). */
    private const DEFAULT_QUALITY = 85;

    /**
     * Compress a single attachment.
     *
     * @param int   $attachmentId Attachment post ID.
     * @param array $opts         Optional overrides: quality, max_width, force (bool).
     * @return array{success:bool, ...}
     */
    public function compress(int $attachmentId, array $opts = []): array
    {
        try {
            return $this->doCompress($attachmentId, $opts);
        } catch (\Throwable $e) {
            $this->log('compress', $attachmentId, $e);
            return ['success' => false, 'error' => __('Compression failed. Check error log for details.', 'imagecraft-ai-alt-text-file-renamer-image-seo')];
        }
    }

    /**
     * Restore an attachment's original (pre-compression) files.
     *
     * @return array{success:bool, ...}
     */
    public function restore(int $attachmentId): array
    {
        try {
            return $this->doRestore($attachmentId);
        } catch (\Throwable $e) {
            $this->log('restore', $attachmentId, $e);
            return ['success' => false, 'error' => __('Restore failed. Check error log for details.', 'imagecraft-ai-alt-text-file-renamer-image-seo')];
        }
    }

    /**
     * Preview potential savings without modifying anything.
     *
     * @return array{success:bool, ...}
     */
    public function preview(int $attachmentId): array
    {
        try {
            if (!wp_attachment_is_image($attachmentId)) {
                return ['success' => false, 'error' => __('Attachment is not an image', 'imagecraft-ai-alt-text-file-renamer-image-seo')];
            }

            $file = get_attached_file($attachmentId);
            $fs = $this->getFilesystem();
            if (empty($file) || !$fs || !$fs->exists($file)) {
                return ['success' => false, 'error' => __('Original file not found', 'imagecraft-ai-alt-text-file-renamer-image-seo')];
            }

            $currentBytes = $this->totalSize($attachmentId, $file, $fs);

            return [
                'success'         => true,
                'attachment_id'   => $attachmentId,
                'already_compressed' => (bool) get_post_meta($attachmentId, self::META_FLAG, true),
                'current_bytes'   => $currentBytes,
                'current_human'   => size_format($currentBytes),
            ];
        } catch (\Throwable $e) {
            $this->log('preview', $attachmentId, $e);
            return ['success' => false, 'error' => __('Preview failed', 'imagecraft-ai-alt-text-file-renamer-image-seo')];
        }
    }

    // ---------------------------------------------------------------------
    // Internals
    // ---------------------------------------------------------------------

    private function doCompress(int $attachmentId, array $opts): array
    {
        if (!function_exists('wp_get_image_editor')) {
            return ['success' => false, 'error' => __('Image editing is not available on this server', 'imagecraft-ai-alt-text-file-renamer-image-seo')];
        }

        if (!wp_attachment_is_image($attachmentId)) {
            return ['success' => false, 'skipped' => true, 'reason' => 'not_an_image'];
        }

        $force = !empty($opts['force']);

        // Skip already-compressed images unless forced.
        $skipCompressed = (string) Settings::get('icais_compression_skip_compressed', '1') === '1';
        if (!$force && $skipCompressed && get_post_meta($attachmentId, self::META_FLAG, true)) {
            return ['success' => true, 'skipped' => true, 'reason' => 'already_compressed'];
        }

        $file = get_attached_file($attachmentId);
        if (empty($file)) {
            return ['success' => false, 'error' => __('Original file not found', 'imagecraft-ai-alt-text-file-renamer-image-seo')];
        }

        $fs = $this->getFilesystem();
        if (!$fs) {
            return ['success' => false, 'error' => __('Filesystem API not available', 'imagecraft-ai-alt-text-file-renamer-image-seo')];
        }
        if (!$fs->exists($file)) {
            return ['success' => false, 'error' => __('Original file not found', 'imagecraft-ai-alt-text-file-renamer-image-seo')];
        }

        // Only re-encode types we understand.
        $mime = (string) get_post_mime_type($attachmentId);
        if (!in_array($mime, self::SUPPORTED_MIMES, true)) {
            return ['success' => true, 'skipped' => true, 'reason' => 'unsupported_type'];
        }

        // Skip tiny files (not worth compressing) unless forced.
        $minKb = (int) Settings::get('icais_compression_min_size_kb', 50);
        $originalBytes = $this->totalSize($attachmentId, $file, $fs);
        if (!$force && $minKb > 0 && $originalBytes < ($minKb * 1024)) {
            return ['success' => true, 'skipped' => true, 'reason' => 'too_small'];
        }

        // Resolve effective options. The free plugin ships visually-lossless
        // defaults; the Pro add-on overrides these via filters when a valid
        // license is active (see the Pro plugin's compression module).
        $quality = isset($opts['quality'])
            ? (int) $opts['quality']
            : (int) apply_filters('icais_compression_quality', self::DEFAULT_QUALITY);
        $quality = max(1, min(100, $quality));

        // Downscale cap (longest edge). Free uses a fixed sensible cap; Pro can
        // change or disable it (return 0) via the filter. 0 disables resizing.
        $maxDim = isset($opts['max_width'])
            ? (int) $opts['max_width']
            : (int) apply_filters('icais_compression_max_dim', self::FREE_MAX_DIM);
        $maxDim = max(0, $maxDim);

        // Re-encode into a TEMP file first so we can reject a result that isn't
        // actually smaller — guaranteeing we never enlarge or needlessly re-encode
        // an already-optimal image (which would drop quality for no benefit).
        $origFullBytes = (int) $fs->size($file);
        $pi  = pathinfo($file);
        $tmp = $pi['dirname'] . '/' . $pi['filename'] . '.icaistmp.' . ($pi['extension'] ?? 'jpg');

        $encoded = $this->reencodeFull($file, $tmp, $mime, $quality, $maxDim);
        if (empty($encoded['success'])) {
            if ($fs->exists($tmp)) {
                $fs->delete($tmp);
            }
            return ['success' => false, 'error' => $encoded['error'] ?? __('Compression failed', 'imagecraft-ai-alt-text-file-renamer-image-seo')];
        }

        clearstatcache();
        $newFullBytes = $fs->exists($tmp) ? (int) $fs->size($tmp) : 0;

        // Never-enlarge / already-optimal guard: if the re-encode didn't shrink the
        // full file, discard it and leave the original untouched (no quality change).
        if ($newFullBytes <= 0 || $newFullBytes >= $origFullBytes) {
            if ($fs->exists($tmp)) {
                $fs->delete($tmp);
            }
            return ['success' => true, 'skipped' => true, 'reason' => 'already_optimized'];
        }

        // Worth committing — back up the original(s) first, unless the user has
        // disabled backups. A pre-existing backup (from an earlier compression) is
        // ALWAYS preserved even when backups are now off: it holds the true original
        // and still powers restore for that image.
        $keepBackup     = (string) Settings::get('icais_compression_keep_backup', '1') === '1';
        $existingBackup = get_post_meta($attachmentId, self::META_BACKUP, true);
        if (is_array($existingBackup) && !empty($existingBackup['dir'])) {
            $backup = ['success' => true, 'manifest' => $existingBackup];
        } elseif ($keepBackup) {
            $backup = $this->backupOriginals($attachmentId, $file, $fs);
            if (!$backup['success']) {
                if ($fs->exists($tmp)) {
                    $fs->delete($tmp);
                }
                return ['success' => false, 'error' => $backup['error']];
            }
        } else {
            // Backups disabled and none exists — proceed without one. This image
            // cannot be restored (empty manifest → can_restore = false).
            $backup = ['success' => true, 'manifest' => []];
        }

        // Commit: move the temp file over the original (same path/format → URL unchanged).
        if (!$fs->move($tmp, $file, true)) {
            if ($fs->exists($tmp)) {
                $fs->delete($tmp);
            }
            return ['success' => false, 'error' => __('Failed to write compressed image', 'imagecraft-ai-alt-text-file-renamer-image-seo')];
        }

        // Regenerate intermediate sizes from the new full file at our quality.
        $this->regenerateSizes($attachmentId, $file, $quality);

        // Measure savings.
        clearstatcache();
        $newBytes   = $this->totalSize($attachmentId, $file, $fs);
        $savedBytes = max(0, $originalBytes - $newBytes);
        $savedPct   = $originalBytes > 0 ? round(($savedBytes / $originalBytes) * 100, 2) : 0.0;

        // Persist flag + stats + backup manifest.
        update_post_meta($attachmentId, self::META_FLAG, time());
        update_post_meta($attachmentId, self::META_BACKUP, $backup['manifest']);
        update_post_meta($attachmentId, self::META_STATS, [
            'original_size'   => $originalBytes,
            'compressed_size' => $newBytes,
            'saved_bytes'     => $savedBytes,
            'saved_percent'   => $savedPct,
        ]);

        // Record history row.
        try {
            Compression::create([
                'attachment_id'   => $attachmentId,
                'user_id'         => get_current_user_id(),
                'original_size'   => $originalBytes,
                'compressed_size' => $newBytes,
                'saved_bytes'     => $savedBytes,
                'saved_percent'   => $savedPct,
                'method'          => 'local',
                'backup_path'     => $backup['manifest']['dir'] ?? '',
                'status'          => 'completed',
            ]);
        } catch (\Throwable $e) {
            $this->log('record', $attachmentId, $e); // non-fatal
        }

        return [
            'success'         => true,
            'attachment_id'   => $attachmentId,
            'original_size'   => $originalBytes,
            'compressed_size' => $newBytes,
            'saved_bytes'     => $savedBytes,
            'saved_percent'   => $savedPct,
            'original_human'  => size_format($originalBytes),
            'compressed_human' => size_format($newBytes),
            'saved_human'     => size_format($savedBytes),
        ];
    }

    /**
     * Re-encode the source image into a destination file (quality-preserving).
     *
     * Prefers a direct Imagick pass — strips EXIF/profiles and writes a progressive
     * JPEG at a visually-lossless quality (no chroma subsampling, so color detail is
     * preserved) — which yields smaller files than WordPress' editor (which keeps
     * metadata). Falls back to the WP image editor (GD/Imagick) when ext-imagick is
     * unavailable or errors. The caller decides whether to keep the result.
     *
     * @param string $src    Source image path.
     * @param string $dest   Destination path to write (may equal $src).
     * @param int    $maxDim Downscale cap on the longest edge (px); 0 disables resizing.
     * @return array{success:bool, error?:string}
     */
    private function reencodeFull(string $src, string $dest, string $mime, int $quality, int $maxDim): array
    {
        if (extension_loaded('imagick') && class_exists('\\Imagick')) {
            try {
                $im = new \Imagick($src);

                // Bake any EXIF orientation into pixels before stripping metadata.
                if (method_exists($im, 'autoOrientImage')) {
                    try {
                        $im->autoOrientImage();
                    } catch (\Throwable $e) {
                        // Older ImageMagick — ignore.
                    }
                }

                // Downscale by the longest edge, preserving aspect ratio.
                if ($maxDim > 0) {
                    $w = (int) $im->getImageWidth();
                    $h = (int) $im->getImageHeight();
                    $longest = max($w, $h);
                    if ($longest > $maxDim) {
                        $scale = $maxDim / $longest;
                        $im->resizeImage((int) round($w * $scale), (int) round($h * $scale), \Imagick::FILTER_LANCZOS, 1);
                    }
                }

                $format = strtolower((string) $im->getImageFormat());
                if ($mime === 'image/jpeg' || in_array($format, ['jpeg', 'jpg'], true)) {
                    $im->setImageCompression(\Imagick::COMPRESSION_JPEG);
                    $im->setImageCompressionQuality($quality);
                    $im->setInterlaceScheme(\Imagick::INTERLACE_PLANE); // progressive
                    // No chroma subsampling: preserves color detail (visually lossless)
                    // and, at high quality, actually produces smaller files.
                } elseif ($mime === 'image/webp') {
                    $im->setImageCompressionQuality($quality);
                } elseif ($mime === 'image/png') {
                    $im->setOption('png:compression-level', '9');
                    $im->setOption('png:compression-filter', '5');
                }

                $im->stripImage();
                $im->writeImage($dest);
                $im->clear();
                $im->destroy();

                return ['success' => true];
            } catch (\Throwable $e) {
                $this->log('imagick', 0, $e);
                // Fall through to the WP editor below.
            }
        }

        // Fallback: WordPress image editor (quality + optional resize only).
        if (!function_exists('wp_get_image_editor')) {
            return ['success' => false, 'error' => __('Image editing is not available on this server', 'imagecraft-ai-alt-text-file-renamer-image-seo')];
        }

        $editor = wp_get_image_editor($src);
        if (is_wp_error($editor)) {
            return ['success' => false, 'error' => $editor->get_error_message()];
        }

        if ($maxDim > 0) {
            $size = $editor->get_size();
            if (is_array($size)) {
                $w = (int) ($size['width'] ?? 0);
                $h = (int) ($size['height'] ?? 0);
                if (max($w, $h) > $maxDim) {
                    // Fit within a maxDim x maxDim box (longest edge), no crop.
                    $resized = $editor->resize($maxDim, $maxDim, false);
                    if (is_wp_error($resized)) {
                        return ['success' => false, 'error' => $resized->get_error_message()];
                    }
                }
            }
        }

        $editor->set_quality($quality);
        $saved = $editor->save($dest, $mime);
        if (is_wp_error($saved)) {
            return ['success' => false, 'error' => $saved->get_error_message()];
        }

        return ['success' => true];
    }

    private function doRestore(int $attachmentId): array
    {
        $manifest = get_post_meta($attachmentId, self::META_BACKUP, true);
        if (!is_array($manifest) || empty($manifest['dir'])) {
            return ['success' => false, 'error' => __('No backup found for this image', 'imagecraft-ai-alt-text-file-renamer-image-seo')];
        }

        $fs = $this->getFilesystem();
        if (!$fs) {
            return ['success' => false, 'error' => __('Filesystem API not available', 'imagecraft-ai-alt-text-file-renamer-image-seo')];
        }

        $uploadDir = wp_upload_dir();
        $baseDir   = wp_normalize_path(rtrim((string) $uploadDir['basedir'], '/'));
        $backupDir = wp_normalize_path((string) $manifest['dir']);

        // Confine the backup dir to the uploads tree (path-traversal guard).
        if (strpos($backupDir, $baseDir) !== 0) {
            return ['success' => false, 'error' => __('Invalid backup location', 'imagecraft-ai-alt-text-file-renamer-image-seo')];
        }

        $targetDir = wp_normalize_path(dirname((string) get_attached_file($attachmentId)));

        // Restore each backed-up file to its original location.
        foreach (($manifest['files'] ?? []) as $filename) {
            $src = $backupDir . '/' . $filename;
            $dst = $targetDir . '/' . $filename;
            if ($fs->exists($src)) {
                $fs->copy($src, $dst, true);
            }
        }

        // Restore attachment metadata + attached-file pointer.
        if (!empty($manifest['attached_file'])) {
            update_attached_file($attachmentId, $manifest['attached_file']);
        }
        if (isset($manifest['metadata']) && is_array($manifest['metadata'])) {
            wp_update_attachment_metadata($attachmentId, $manifest['metadata']);
        }

        // Remove the backup files + flags.
        $this->deleteDir($backupDir, $fs);
        delete_post_meta($attachmentId, self::META_FLAG);
        delete_post_meta($attachmentId, self::META_BACKUP);
        delete_post_meta($attachmentId, self::META_STATS);
        clean_post_cache($attachmentId);

        return ['success' => true, 'attachment_id' => $attachmentId];
    }

    /**
     * Copy the full-size file and every intermediate size into a backup folder.
     *
     * @return array{success:bool, manifest?:array, error?:string}
     */
    private function backupOriginals(int $attachmentId, string $file, $fs): array
    {
        $uploadDir = wp_upload_dir();
        $baseDir   = wp_normalize_path(rtrim((string) $uploadDir['basedir'], '/'));
        $normFile  = wp_normalize_path($file);

        // Relative dir of the file within uploads, mirrored under icais-originals.
        $relDir = trim(str_replace($baseDir, '', wp_normalize_path(dirname($normFile))), '/');
        $backupDir = $baseDir . '/icais-originals/' . ($relDir !== '' ? $relDir : '') . '/' . $attachmentId;
        $backupDir = wp_normalize_path($backupDir);

        if (!$fs->is_dir($backupDir) && !wp_mkdir_p($backupDir)) {
            return ['success' => false, 'error' => __('Failed to create backup directory', 'imagecraft-ai-alt-text-file-renamer-image-seo')];
        }

        $dir       = dirname($normFile);
        $metadata  = wp_get_attachment_metadata($attachmentId);
        $files     = [basename($normFile)];

        if (is_array($metadata) && !empty($metadata['sizes']) && is_array($metadata['sizes'])) {
            foreach ($metadata['sizes'] as $sizeData) {
                if (!empty($sizeData['file'])) {
                    $files[] = (string) $sizeData['file'];
                }
            }
        }
        $files = array_values(array_unique($files));

        foreach ($files as $filename) {
            $src = $dir . '/' . $filename;
            if ($fs->exists($src)) {
                $fs->copy($src, $backupDir . '/' . $filename, true);
            }
        }

        return [
            'success'  => true,
            'manifest' => [
                'dir'           => $backupDir,
                'files'         => $files,
                'attached_file' => get_post_meta($attachmentId, '_wp_attached_file', true),
                'metadata'      => is_array($metadata) ? $metadata : [],
            ],
        ];
    }

    /**
     * Regenerate intermediate sizes from the (already compressed) full file,
     * forcing our quality during the regeneration.
     */
    private function regenerateSizes(int $attachmentId, string $file, int $quality): void
    {
        if (!function_exists('wp_generate_attachment_metadata')) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }
        if (!function_exists('wp_generate_attachment_metadata')) {
            return;
        }

        $force = static function () use ($quality) {
            return $quality;
        };
        add_filter('wp_editor_set_quality', $force, 99);

        try {
            $metadata = wp_generate_attachment_metadata($attachmentId, $file);
            if (is_array($metadata) && !empty($metadata)) {
                wp_update_attachment_metadata($attachmentId, $metadata);
            }
        } catch (\Throwable $e) {
            $this->log('regen', $attachmentId, $e);
        } finally {
            remove_filter('wp_editor_set_quality', $force, 99);
        }
    }

    /**
     * Total bytes of the full file + all intermediate size files.
     */
    private function totalSize(int $attachmentId, string $file, $fs): int
    {
        $total = 0;
        if ($fs->exists($file)) {
            $total += (int) $fs->size($file);
        }

        $metadata = wp_get_attachment_metadata($attachmentId);
        if (is_array($metadata) && !empty($metadata['sizes']) && is_array($metadata['sizes'])) {
            $dir = dirname($file);
            foreach ($metadata['sizes'] as $sizeData) {
                if (empty($sizeData['file'])) {
                    continue;
                }
                $sizePath = $dir . '/' . $sizeData['file'];
                if ($fs->exists($sizePath)) {
                    $total += (int) $fs->size($sizePath);
                }
            }
        }

        return $total;
    }

    /**
     * Recursively delete a directory's files then the directory itself.
     */
    private function deleteDir(string $dir, $fs): void
    {
        if (!$fs->is_dir($dir)) {
            return;
        }
        $fs->delete($dir, true);
    }

    /**
     * WP_Filesystem bootstrap (mirrors FileRenamer).
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

    private function log(string $stage, int $attachmentId, \Throwable $e): void
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log('[ImageCraft] Compression ' . $stage . ' error for #' . $attachmentId . ': ' . $e->getMessage());
        }
    }
}
