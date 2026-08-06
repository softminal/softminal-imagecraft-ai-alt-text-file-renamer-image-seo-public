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
    private static $nextgenMetaKey = 'icais_nextgen_notice_dismissed';

    public static function init(): void
    {
        add_action('admin_notices', [self::class, 'displayReviewNotice']);
        // One-time "What's New" for the current release: Next-gen WebP + Image
        // Fallbacks. Uses its OWN dismissal meta so users who dismissed the old
        // Fallbacks-only notice still hear about WebP. The legacy Fallbacks
        // notice (displayFallbackFeatureNotice) stays unhooked for backwards
        // compatibility only.
        add_action('admin_notices', [self::class, 'displayNextGenFeatureNotice']);
    }

    /**
     * One-time "What's New" notice for Next-gen WebP + Image Fallbacks.
     *
     * Reaches existing users who updated but don't visit the ImageCraft page.
     * Self-limiting: admins only, per-user dismissal, hidden mid-wizard (fresh
     * installs meet both features in the SPA; the wizard marks it dismissed),
     * and hidden once BOTH features are already in use.
     */
    public static function displayNextGenFeatureNotice(): void
    {
        $userId = get_current_user_id();

        if (!current_user_can('manage_options')) {
            return;
        }

        if (get_user_meta($userId, self::$nextgenMetaKey, true)) {
            return;
        }

        // Mid-wizard installs are excluded; the SPA introduces the features.
        if (!Settings::isWizardCompleted($userId)) {
            return;
        }

        // Already using both — nothing to announce.
        if (Settings::get('icais_fallback_enabled', '0') === '1'
            && Settings::get('icais_compression_convert_webp', '0') === '1') {
            return;
        }

        $setupUrl = admin_url('admin.php?page=icais#/performance');
        $dismissUrl = esc_url(rest_url(ICAIS_API_NAMESPACE . '/notice/dismiss'));
        $nonce = wp_create_nonce('wp_rest');
        ?>
        <div class="notice notice-info is-dismissible icais-nextgen-notice">
            <p>
                <strong><?php echo esc_html__('New in ImageCraft: Next-gen WebP & Image Fallbacks.', 'imagecraft-ai-alt-text-file-renamer-image-seo'); ?></strong><br>
                <?php echo esc_html__('Serve smaller WebP copies of your images without touching the originals, and show a fallback image instead of broken or missing ones. Both run locally, need no API key, and are off by default.', 'imagecraft-ai-alt-text-file-renamer-image-seo'); ?>
            </p>
            <p>
                <a href="<?php echo esc_url($setupUrl); ?>" class="button button-primary">
                    <?php echo esc_html__('Explore the new features', 'imagecraft-ai-alt-text-file-renamer-image-seo'); ?>
                </a>
                <a href="#" class="icais-dismiss-nextgen" style="margin-left: 12px; color: #646970; text-decoration: none; box-shadow: none; outline: none;">
                    <?php echo esc_html__('Dismiss', 'imagecraft-ai-alt-text-file-renamer-image-seo'); ?>
                </a>
            </p>
        </div>
        <?php
        self::printDismissScript('.icais-nextgen-notice', '.icais-dismiss-nextgen', $dismissUrl . '?notice=nextgen_feature', $nonce);
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

        $metaKey = self::$metaKey; // 'review' (legacy default)
        if ($notice === 'fallback_feature') {
            $metaKey = self::$fallbackMetaKey;
        } elseif ($notice === 'nextgen_feature') {
            $metaKey = self::$nextgenMetaKey;
        }
        update_user_meta($userId, $metaKey, '1');

        return new \WP_REST_Response(['success' => true]);
    }
}
