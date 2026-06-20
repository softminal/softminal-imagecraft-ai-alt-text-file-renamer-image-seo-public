<?php

namespace Softminal\ImageCraft;

use Softminal\ImageCraft\Services\ImageCompressor;
use Softminal\ImageCraft\Services\Settings;
use Softminal\ImageCraft\Services\UsageLimiter;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Auto-compress images on upload.
 *
 * Pro-only and opt-in: requires both the Pro license (ICAIS_PRO) and the
 * `icais_compression_auto` setting. Compression runs in a scheduled cron event
 * so it never blocks the upload request.
 */
class AutoCompress
{
    public static function init(): void
    {
        add_action('add_attachment', [self::class, 'onAttachmentAdded']);
        add_action('icais_auto_compress', [self::class, 'compressForAttachment']);
    }

    /**
     * Handle a newly uploaded attachment.
     */
    public static function onAttachmentAdded($attachmentId): void
    {
        try {
            // Pro + opt-in gates.
            if (!UsageLimiter::isPro()) {
                return;
            }
            if ((string) Settings::get('icais_compression_enabled', '0') !== '1') {
                return;
            }
            if ((string) Settings::get('icais_compression_auto', '0') !== '1') {
                return;
            }

            if (!wp_attachment_is_image($attachmentId)) {
                return;
            }

            // Capture user ID now (cron context won't have a logged-in user).
            $userId = get_current_user_id();

            wp_schedule_single_event(time() + 5, 'icais_auto_compress', [(int) $attachmentId, $userId]);
        } catch (\Throwable $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log('[ImageCraft] Auto-compress schedule error: ' . $e->getMessage());
            }
        }
    }

    /**
     * Compress an attachment (scheduled cron action).
     */
    public static function compressForAttachment($attachmentId, $userId = 0): void
    {
        try {
            $attachmentId = (int) $attachmentId;
            $userId = $userId ?: get_current_user_id();

            // Re-check gates inside cron (settings/license may have changed).
            if (!UsageLimiter::isPro()) {
                return;
            }
            if ((string) Settings::get('icais_compression_enabled', '0') !== '1') {
                return;
            }
            if ((string) Settings::get('icais_compression_auto', '0') !== '1') {
                return;
            }
            if (!wp_attachment_is_image($attachmentId)) {
                return;
            }

            $compressor = new ImageCompressor();
            $compressor->compress($attachmentId);
        } catch (\Throwable $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log('[ImageCraft] Auto-compress error for #' . $attachmentId . ': ' . $e->getMessage());
            }
        }
    }
}
