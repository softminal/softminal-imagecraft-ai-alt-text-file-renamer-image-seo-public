<?php

namespace Softminal\ImageCraft\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Next-gen format converter (WebP / AVIF).
 *
 * Generates SIDECAR files next to the originals: photo.jpg → photo.jpg.webp
 * (and photo.jpg.avif on Pro). The original file, its URL, _wp_attached_file,
 * and attachment metadata are NEVER touched — delivery happens at render time
 * (see includes/NextGen.php), so the feature is fully reversible: deleting the
 * sidecars restores the exact previous state.
 *
 * The ".jpg.webp" naming (suffix appended to the FULL file name) is collision
 * free (photo.jpg + photo.png can't clash) and lets delivery map any image URL
 * to its sidecar by simple concatenation.
 *
 * Safety: every encoder call is wrapped; a failure on one file or format never
 * fatals the caller — it just means that sidecar isn't produced.
 */
class NextGenConverter
{
    /** Post meta holding the sidecar manifest (files + savings). */
    public const META_MANIFEST = '_icais_nextgen';

    /** Mime types we can convert FROM. */
    private const SOURCE_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    /** Encoder quality for next-gen output (visually lossless territory). */
    private const QUALITY = 82;

    /** @var array|null Cached capability probe for this request. */
    private static $capabilities = null;

    /**
     * Which next-gen formats this server can encode.
     *
     * AVIF: GD needs PHP 8.1+ (imageavif); Imagick needs an AVIF delegate.
     * With the plugin's PHP 7.4 floor, AVIF is genuinely unavailable on many
     * hosts — callers must surface that as "server can't", not a Pro lock.
     *
     * @return array{webp:bool, avif:bool}
     */
    public static function capabilities(): array
    {
        if (self::$capabilities !== null) {
            return self::$capabilities;
        }

        $webp = function_exists('imagewebp');
        $avif = function_exists('imageavif');

        if (extension_loaded('imagick') && class_exists('\Imagick')) {
            try {
                $formats = \Imagick::queryFormats('*');
                $webp = $webp || in_array('WEBP', $formats, true);
                $avif = $avif || in_array('AVIF', $formats, true);
            } catch (\Throwable $e) {
                // Imagick probe failed — fall back to the GD answer.
            }
        }

        self::$capabilities = ['webp' => $webp, 'avif' => $avif];

        return self::$capabilities;
    }

    /**
     * The formats conversion should produce, resolved from the user's opt-in
     * settings: WebP is free; AVIF additionally requires Pro (ICAIS_PRO). The
     * result runs through the icais_compression_formats filter (Pro unlock
     * point) and is always intersected with real server capability, so it is
     * exactly what will work on this host.
     *
     * @return string[] Subset of ['webp', 'avif'].
     */
    public static function enabledFormats(): array
    {
        $formats = [];
        if (Settings::get('icais_compression_convert_webp', '0') === '1') {
            $formats[] = 'webp';
        }
        if (Settings::get('icais_compression_convert_avif', '0') === '1' && defined('ICAIS_PRO') && \ICAIS_PRO) {
            $formats[] = 'avif';
        }

        $formats = apply_filters('icais_compression_formats', $formats);
        $formats = is_array($formats) ? $formats : [];
        $caps = self::capabilities();

        return array_values(array_filter($formats, static function ($format) use ($caps) {
            return in_array($format, ['webp', 'avif'], true) && !empty($caps[$format]);
        }));
    }

    /**
     * Generate next-gen sidecars for an attachment (full size + every
     * intermediate size).
     *
     * @param int      $attachmentId Attachment post ID.
     * @param string[] $formats      Formats to produce (defaults to enabledFormats()).
     * @return array{success:bool, ...}
     */
    public function convert(int $attachmentId, array $formats = []): array
    {
        try {
            return $this->doConvert($attachmentId, $formats);
        } catch (\Throwable $e) {
            $this->log('convert', $attachmentId, $e);
            return ['success' => false, 'error' => __('Conversion failed. Check error log for details.', 'imagecraft-ai-alt-text-file-renamer-image-seo')];
        }
    }

    /**
     * Remove every sidecar this plugin generated for an attachment.
     * Also hooked to delete_attachment so orphaned files never pile up.
     *
     * @return array{success:bool, removed:int}
     */
    public function clean(int $attachmentId): array
    {
        try {
            $manifest = get_post_meta($attachmentId, self::META_MANIFEST, true);
            $removed = 0;

            if (is_array($manifest) && !empty($manifest['files'])) {
                $base = $this->uploadsBase();
                foreach ($manifest['files'] as $relative) {
                    $path = $base !== '' ? path_join($base, $relative) : '';
                    // Confine deletions to the uploads directory (manifest could
                    // be stale/corrupted; never follow it outside uploads).
                    if ($path !== '' && strpos(realpath($path) ?: $path, $base) === 0 && @is_file($path)) {
                        // wp_delete_file() has no return value on older WP —
                        // confirm removal by re-checking the file.
                        wp_delete_file($path);
                        if (!@is_file($path)) {
                            $removed++;
                        }
                    }
                }
            }

            delete_post_meta($attachmentId, self::META_MANIFEST);

            return ['success' => true, 'removed' => $removed];
        } catch (\Throwable $e) {
            $this->log('clean', $attachmentId, $e);
            return ['success' => false, 'removed' => 0];
        }
    }

    // ---------------------------------------------------------------------
    // Internals
    // ---------------------------------------------------------------------

    private function doConvert(int $attachmentId, array $formats): array
    {
        if (!wp_attachment_is_image($attachmentId)) {
            return ['success' => false, 'skipped' => true, 'reason' => 'not_an_image'];
        }

        $mime = get_post_mime_type($attachmentId);
        if (!in_array($mime, self::SOURCE_MIMES, true)) {
            return ['success' => false, 'skipped' => true, 'reason' => 'unsupported_type'];
        }

        $formats = $formats !== [] ? $formats : self::enabledFormats();
        // A WebP source only needs an AVIF sidecar.
        if ($mime === 'image/webp') {
            $formats = array_values(array_diff($formats, ['webp']));
        }
        if ($formats === []) {
            return ['success' => false, 'skipped' => true, 'reason' => 'no_supported_format'];
        }

        $file = get_attached_file($attachmentId);
        if (empty($file) || !@is_file($file)) {
            return ['success' => false, 'error' => __('Original file not found', 'imagecraft-ai-alt-text-file-renamer-image-seo')];
        }

        $sources = $this->sourceFiles($attachmentId, $file);
        $base = $this->uploadsBase();

        $files = [];
        $savedBytes = 0;
        $generated = 0;

        foreach ($sources as $source) {
            $sourceBytes = (int) @filesize($source);
            if ($sourceBytes <= 0) {
                continue;
            }

            foreach ($formats as $format) {
                $dest = $source . '.' . $format;

                // Idempotent: an existing, non-empty sidecar is kept as-is.
                if (@is_file($dest) && (int) @filesize($dest) > 0) {
                    $files[] = $this->relativeTo($base, $dest);
                    continue;
                }

                if (!$this->encode($source, $dest, $format)) {
                    continue;
                }

                $destBytes = (int) @filesize($dest);
                // Never-bigger guard: a sidecar larger than its source would
                // slow the site down — discard it.
                if ($destBytes <= 0 || $destBytes >= $sourceBytes) {
                    wp_delete_file($dest);
                    continue;
                }

                $files[] = $this->relativeTo($base, $dest);
                $savedBytes += ($sourceBytes - $destBytes);
                $generated++;
            }
        }

        if ($files === []) {
            return ['success' => false, 'skipped' => true, 'reason' => 'nothing_generated'];
        }

        // Merge with any prior manifest (e.g. WebP done earlier, AVIF now).
        $manifest = get_post_meta($attachmentId, self::META_MANIFEST, true);
        $existing = (is_array($manifest) && !empty($manifest['files']) && is_array($manifest['files'])) ? $manifest['files'] : [];
        $allFiles = array_values(array_unique(array_merge($existing, $files)));

        update_post_meta($attachmentId, self::META_MANIFEST, [
            'files'   => $allFiles,
            'formats' => $formats,
            'saved'   => $savedBytes + (int) ($manifest['saved'] ?? 0),
            'time'    => time(),
        ]);

        return [
            'success'       => true,
            'attachment_id' => $attachmentId,
            'generated'     => $generated,
            'files'         => count($allFiles),
            'saved_bytes'   => $savedBytes,
            'saved_human'   => size_format($savedBytes),
        ];
    }

    /**
     * Encode one file into one target format. Imagick first, GD fallback.
     * Returns false (never throws) when the server can't produce the format.
     */
    private function encode(string $source, string $dest, string $format): bool
    {
        // --- Imagick ---
        if (extension_loaded('imagick') && class_exists('\Imagick')) {
            try {
                $im = new \Imagick($source);
                if (is_callable([$im, 'autoOrientImage'])) {
                    $im->autoOrientImage();
                }
                $im->setImageFormat(strtoupper($format));
                $im->setImageCompressionQuality(self::QUALITY);
                $im->stripImage();
                $ok = $im->writeImage($dest);
                $im->clear();
                if ($ok && @is_file($dest)) {
                    return true;
                }
            } catch (\Throwable $e) {
                // Fall through to GD.
            }
        }

        // --- GD ---
        $fn = $format === 'avif' ? 'imageavif' : 'imagewebp';
        if (!function_exists($fn)) {
            return false;
        }

        try {
            $image = $this->gdImageFromFile($source);
            if (!$image) {
                return false;
            }
            $ok = @$fn($image, $dest, self::QUALITY);
            if (function_exists('imagedestroy')) {
                @imagedestroy($image);
            }
            return $ok && @is_file($dest);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Create a GD image resource from a JPEG/PNG/WebP file (or null). */
    private function gdImageFromFile(string $path)
    {
        $info = @getimagesize($path);
        if (!$info || empty($info['mime'])) {
            return null;
        }

        switch ($info['mime']) {
            case 'image/jpeg':
                return function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($path) : null;
            case 'image/png':
                $image = function_exists('imagecreatefrompng') ? @imagecreatefrompng($path) : null;
                if ($image && function_exists('imagepalettetotruecolor')) {
                    // Preserve transparency through the conversion.
                    @imagepalettetotruecolor($image);
                    @imagealphablending($image, true);
                    @imagesavealpha($image, true);
                }
                return $image;
            case 'image/webp':
                return function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : null;
        }

        return null;
    }

    /** Full-size file + every intermediate size that exists on disk. */
    private function sourceFiles(int $attachmentId, string $fullFile): array
    {
        $files = [$fullFile];
        $meta = wp_get_attachment_metadata($attachmentId);
        $dir = dirname($fullFile);

        if (is_array($meta) && !empty($meta['sizes']) && is_array($meta['sizes'])) {
            foreach ($meta['sizes'] as $size) {
                if (!empty($size['file'])) {
                    $path = path_join($dir, $size['file']);
                    if (@is_file($path)) {
                        $files[] = $path;
                    }
                }
            }
        }

        return array_values(array_unique($files));
    }

    private function uploadsBase(): string
    {
        $uploads = wp_get_upload_dir();
        return !empty($uploads['basedir']) ? (realpath($uploads['basedir']) ?: $uploads['basedir']) : '';
    }

    private function relativeTo(string $base, string $path): string
    {
        $real = realpath($path) ?: $path;
        if ($base !== '' && strpos($real, $base) === 0) {
            return ltrim(substr($real, strlen($base)), '/\\');
        }
        return $path;
    }

    private function log(string $action, int $attachmentId, \Throwable $e): void
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf('ImageCraft nextgen %s #%d: %s', $action, $attachmentId, $e->getMessage()));
        }
    }
}
