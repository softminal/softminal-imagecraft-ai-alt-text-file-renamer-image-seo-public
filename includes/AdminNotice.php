<?php

namespace Softminal\ImageCraft;

use Softminal\ImageCraft\Services\Settings;

if (!defined('ABSPATH')) {
    exit;
}

class AdminNotice
{
    private static $generationThreshold = 8;
    private static $metaKey = 'icais_review_notice_dismissed';
    private static $fallbackMetaKey = 'icais_fallback_notice_dismissed';

    public static function init(): void
    {
        add_action('admin_notices', [self::class, 'displayReviewNotice']);
        // The Image Fallbacks "What's New" notice is retired (UX redesign:
        // discovery is consolidated into the SPA — quick action, needs-attention
        // tile, and the settings sidebar). displayFallbackFeatureNotice() and the
        // notice/dismiss REST param are kept for backwards compatibility, but the
        // notice is no longer rendered.
    }

    /**
     * One-time "What's New" notice for the Image Fallbacks feature.
     *
     * Reaches existing users who updated but don't visit the ImageCraft page
     * (where the Dashboard promo card lives). Self-limiting: hidden for
     * non-admins, once dismissed (per user), once the module is enabled, and
     * for fresh installs (the setup wizard's Fallbacks step marks it dismissed).
     */
    public static function displayFallbackFeatureNotice(): void
    {
        $userId = get_current_user_id();

        if (!current_user_can('manage_options')) {
            return;
        }

        if (get_user_meta($userId, self::$fallbackMetaKey, true)) {
            return;
        }

        // Mid-wizard installs are excluded; the wizard itself introduces the feature.
        if (!Settings::isWizardCompleted($userId)) {
            return;
        }

        // Already using it — nothing to announce.
        if (Settings::get('icais_fallback_enabled', '0') === '1') {
            return;
        }

        $setupUrl = admin_url('admin.php?page=icais#/settings/fallback');
        $dismissUrl = esc_url(rest_url(ICAIS_API_NAMESPACE . '/notice/dismiss'));
        $nonce = wp_create_nonce('wp_rest');
        ?>
        <div class="notice notice-info is-dismissible icais-fallback-notice">
            <p>
                <strong><?php echo esc_html__('New in ImageCraft: Image Fallbacks — never show a broken image again.', 'imagecraft-ai-alt-text-file-renamer-image-seo'); ?></strong><br>
                <?php echo esc_html__('Pick one fallback image: it replaces broken images for your visitors and acts as the featured image for posts without one. Non-destructive and off by default.', 'imagecraft-ai-alt-text-file-renamer-image-seo'); ?>
            </p>
            <p>
                <a href="<?php echo esc_url($setupUrl); ?>" class="button button-primary">
                    <?php echo esc_html__('Set up Image Fallbacks', 'imagecraft-ai-alt-text-file-renamer-image-seo'); ?>
                </a>
                <a href="#" class="icais-dismiss-fallback" style="margin-left: 12px; color: #646970; text-decoration: none; box-shadow: none; outline: none;">
                    <?php echo esc_html__('Dismiss', 'imagecraft-ai-alt-text-file-renamer-image-seo'); ?>
                </a>
            </p>
        </div>
        <?php
        self::printDismissScript('.icais-fallback-notice', '.icais-dismiss-fallback', $dismissUrl . '?notice=fallback_feature', $nonce);
    }

    public static function displayReviewNotice(): void
    {
        $userId = get_current_user_id();

        if (!current_user_can('edit_posts')) {
            return;
        }

        if (get_user_meta($userId, self::$metaKey, true)) {
            return;
        }

        if (!Settings::isWizardCompleted($userId)) {
            return;
        }

        global $wpdb;
        $table = esc_sql($wpdb->prefix . ICAIS_TABLE_PREFIX . 'history');
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time count for admin notice, caching unnecessary
        $count = (int) $wpdb->get_var(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is escaped via esc_sql above
                "SELECT COUNT(*) FROM `{$table}` WHERE user_id = %d",
                $userId
            )
        );

        if ($count < self::$generationThreshold) {
            return;
        }

        $reviewUrl = 'https://wordpress.org/support/plugin/imagecraft-ai-alt-text-file-renamer-image-seo/reviews/#new-post';
        $dismissUrl = esc_url(rest_url(ICAIS_API_NAMESPACE . '/notice/dismiss'));
        $nonce = wp_create_nonce('wp_rest');
        ?>
        <div class="notice notice-info is-dismissible icais-review-notice">
            <p>
                <strong><?php echo esc_html__("You've generated over 8 alt texts with ImageCraft — that's awesome!", 'imagecraft-ai-alt-text-file-renamer-image-seo'); ?></strong><br>
                <?php echo esc_html__('If you find the plugin helpful, we\'d love a quick review on WordPress.org. It helps us grow and keep improving!', 'imagecraft-ai-alt-text-file-renamer-image-seo'); ?>
            </p>
            <p>
                <a href="<?php echo esc_url($reviewUrl); ?>" target="_blank" rel="noopener noreferrer" class="button button-primary">
                    &#11088; <?php echo esc_html__('Leave a Review', 'imagecraft-ai-alt-text-file-renamer-image-seo'); ?>
                </a>
                <a href="#" class="button button-link icais-dismiss-review" style="margin-left: 10px;">
                    <?php echo esc_html__('Maybe Later', 'imagecraft-ai-alt-text-file-renamer-image-seo'); ?>
                </a>
            </p>
        </div>
        <?php
        self::printDismissScript('.icais-review-notice', '.icais-dismiss-review', $dismissUrl, $nonce);
    }

    /**
     * Print the shared dismiss handler for a notice: the inline dismiss link
     * and core's is-dismissible 'X' both hide the notice and persist the
     * dismissal via the REST endpoint.
     */
    private static function printDismissScript(string $noticeSelector, string $dismissLinkSelector, string $dismissUrl, string $nonce): void
    {
        $script = sprintf(
            "(function() {
                function ready(fn) {
                    if (document.readyState !== 'loading') {
                        fn();
                    } else {
                        document.addEventListener('DOMContentLoaded', fn);
                    }
                }

                ready(function() {
                    var notice = document.querySelector('%s');
                    if (!notice) {
                        return;
                    }

                    var dismissed = false;
                    function persistDismiss() {
                        if (dismissed) {
                            return;
                        }
                        dismissed = true;
                        fetch('%s', {
                            method: 'POST',
                            headers: {
                                'X-WP-Nonce': '%s',
                                'Content-Type': 'application/json'
                            }
                        });
                    }

                    // Inline dismiss link — hide and persist.
                    var dismissLink = notice.querySelector('%s');
                    if (dismissLink) {
                        dismissLink.addEventListener('click', function(e) {
                            e.preventDefault();
                            persistDismiss();
                            notice.style.display = 'none';
                        });
                    }

                    // Core 'X' button from is-dismissible (injected on ready, after
                    // this may run) — delegate so the click still persists.
                    notice.addEventListener('click', function(e) {
                        if (e.target && e.target.classList && e.target.classList.contains('notice-dismiss')) {
                            persistDismiss();
                        }
                    });
                });
            })();",
            esc_js($noticeSelector),
            esc_js($dismissUrl),
            esc_js($nonce),
            esc_js($dismissLinkSelector)
        );

        // wp_print_inline_script_tag() handles safe output of the inline handler;
        // available since WP 5.7 (plugin floor is 5.8). Dynamic values escaped via esc_js above.
        wp_print_inline_script_tag($script);
    }

    /**
     * Persist a per-user notice dismissal. The optional `notice` param selects
     * which notice (additive — omitting it keeps the legacy review behavior).
     */
    public static function dismiss($request = null): \WP_REST_Response
    {
        $userId = get_current_user_id();

        $notice = 'review';
        if ($request && is_callable([$request, 'get_param'])) {
            $notice = sanitize_key((string) ($request->get_param('notice') ?: 'review'));
        }

        $metaKey = $notice === 'fallback_feature' ? self::$fallbackMetaKey : self::$metaKey;
        update_user_meta($userId, $metaKey, '1');

        return new \WP_REST_Response(['success' => true]);
    }
}
