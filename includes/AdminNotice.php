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

    public static function init(): void
    {
        add_action('admin_notices', [self::class, 'displayReviewNotice']);
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
        <script>
            (function() {
                document.querySelector('.icais-dismiss-review').addEventListener('click', function(e) {
                    e.preventDefault();
                    var notice = document.querySelector('.icais-review-notice');
                    fetch('<?php echo esc_js($dismissUrl); ?>', {
                        method: 'POST',
                        headers: {
                            'X-WP-Nonce': '<?php echo esc_js($nonce); ?>',
                            'Content-Type': 'application/json'
                        }
                    });
                    if (notice) {
                        notice.style.display = 'none';
                    }
                });
            })();
        </script>
        <?php
    }

    public static function dismiss(): \WP_REST_Response
    {
        $userId = get_current_user_id();
        update_user_meta($userId, self::$metaKey, '1');

        return new \WP_REST_Response(['success' => true]);
    }
}
