<?php

namespace Softminal\ImageCraft;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Deactivation feedback survey.
 *
 * Shows a modal when the admin clicks "Deactivate" on the ImageCraft row of the
 * Plugins screen. The user can pick a reason and submit it, or skip — either way
 * the deactivation proceeds. Feedback is only sent on an explicit submit; the
 * actual network call is handled server-side by FeedbackController.
 *
 * Self-contained: renders its own markup in the footer, ships a hand-written CSS
 * file, and attaches a small vanilla-JS handler. No Vue/Vite involvement, so it
 * works on plugins.php (which is outside the SPA).
 */
class DeactivationSurvey
{
    public static function init(): void
    {
        add_action('current_screen', [self::class, 'maybeHook']);
    }

    /**
     * Only engage on the Plugins screen, and only for users who can deactivate.
     */
    public static function maybeHook($screen): void
    {
        if (!($screen instanceof \WP_Screen) || $screen->id !== 'plugins') {
            return;
        }

        if (!current_user_can('activate_plugins')) {
            return;
        }

        add_action('admin_footer', [self::class, 'render']);
    }

    /**
     * The survey reasons. `detail` indicates an optional free-text follow-up.
     *
     * @return array<int,array<string,mixed>>
     */
    private static function reasons(): array
    {
        return [
            [
                'id'    => 'no_longer',
                'label' => __('I no longer need the plugin', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            ],
            [
                'id'          => 'better_found',
                'label'       => __('I found a better plugin', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
                'placeholder' => __('Which plugin? (optional)', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            ],
            [
                'id'          => 'not_working',
                'label'       => __("I couldn't get the plugin to work", 'imagecraft-ai-alt-text-file-renamer-image-seo'),
                'placeholder' => __("What went wrong? We'd love to help. (optional)", 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            ],
            [
                'id'    => 'temporary',
                'label' => __("It's a temporary deactivation", 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            ],
            [
                'id'          => 'too_complex',
                'label'       => __("I don't know how to use it", 'imagecraft-ai-alt-text-file-renamer-image-seo'),
                'placeholder' => __('What were you trying to do? (optional)', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            ],
            [
                'id'          => 'other',
                'label'       => __('Other', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
                'placeholder' => __('Please tell us more (optional)', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            ],
        ];
    }

    /**
     * Output the modal markup, styles and behaviour.
     */
    public static function render(): void
    {
        $pluginBasename = plugin_basename(ICAIS_DIR . 'imagecraft-ai-alt-text-file-renamer-image-seo.php');

        wp_enqueue_style(
            'icais-deactivation-survey',
            ICAIS_URL . 'resources/admin/css/deactivation-survey.css',
            [],
            ICAIS_VERSION
        );

        self::enqueueScript($pluginBasename);
        ?>
        <div id="icais-deactivation-modal" class="icais-deactivation-modal" aria-hidden="true">
            <div class="icais-modal-overlay" data-icais-close></div>
            <div class="icais-modal-box" role="dialog" aria-modal="true" aria-labelledby="icais-modal-title">
                <button type="button" class="icais-modal-x" data-icais-close aria-label="<?php echo esc_attr__('Close', 'imagecraft-ai-alt-text-file-renamer-image-seo'); ?>">&times;</button>

                <h2 id="icais-modal-title" class="icais-modal-title">
                    <?php echo esc_html__('Quick question before you go', 'imagecraft-ai-alt-text-file-renamer-image-seo'); ?>
                </h2>
                <p class="icais-modal-subtitle">
                    <?php echo esc_html__('If you have a moment, please let us know why you are deactivating ImageCraft. It helps us improve.', 'imagecraft-ai-alt-text-file-renamer-image-seo'); ?>
                </p>

                <div class="icais-modal-reasons">
                    <?php foreach (self::reasons() as $reason) : ?>
                        <label class="icais-reason">
                            <input
                                type="radio"
                                name="icais_reason"
                                value="<?php echo esc_attr($reason['id']); ?>"
                            >
                            <span class="icais-reason-label"><?php echo esc_html($reason['label']); ?></span>
                        </label>
                        <?php if (!empty($reason['placeholder'])) : ?>
                            <div class="icais-reason-detail" data-for="<?php echo esc_attr($reason['id']); ?>" style="display:none;">
                                <textarea
                                    rows="2"
                                    class="icais-reason-textarea"
                                    placeholder="<?php echo esc_attr($reason['placeholder']); ?>"
                                ></textarea>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>

                <div class="icais-modal-actions">
                    <button type="button" class="button icais-modal-skip" data-icais-skip>
                        <?php echo esc_html__('Skip & Deactivate', 'imagecraft-ai-alt-text-file-renamer-image-seo'); ?>
                    </button>
                    <button type="button" class="button button-primary icais-modal-submit" data-icais-submit>
                        <?php echo esc_html__('Submit & Deactivate', 'imagecraft-ai-alt-text-file-renamer-image-seo'); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Register a virtual script handle, localise config, and attach the handler.
     */
    private static function enqueueScript(string $pluginBasename): void
    {
        wp_register_script('icais-deactivation-survey', false, [], ICAIS_VERSION, true);
        wp_enqueue_script('icais-deactivation-survey');

        wp_localize_script('icais-deactivation-survey', 'icaisDeactivation', [
            'plugin'  => $pluginBasename,
            'restUrl' => rest_url(ICAIS_API_NAMESPACE . '/feedback/deactivation'),
            'nonce'   => wp_create_nonce('wp_rest'),
        ]);

        $js = <<<'JS'
(function () {
    var cfg = window.icaisDeactivation || {};
    var modal = document.getElementById('icais-deactivation-modal');
    if (!modal || !cfg.plugin) {
        return;
    }

    var link = document.querySelector('tr[data-plugin="' + cfg.plugin + '"] span.deactivate a');
    if (!link) {
        return;
    }

    var deactivateUrl = link.getAttribute('href');

    function openModal() {
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('icais-modal-open');
    }

    function closeModal() {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('icais-modal-open');
    }

    function goDeactivate() {
        window.location.href = deactivateUrl;
    }

    link.addEventListener('click', function (e) {
        e.preventDefault();
        openModal();
    });

    modal.querySelectorAll('input[name="icais_reason"]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            modal.querySelectorAll('.icais-reason-detail').forEach(function (d) {
                d.style.display = 'none';
            });
            var detail = modal.querySelector('.icais-reason-detail[data-for="' + radio.value + '"]');
            if (detail) {
                detail.style.display = 'block';
                var field = detail.querySelector('textarea, input');
                if (field) {
                    field.focus();
                }
            }
        });
    });

    modal.querySelectorAll('[data-icais-close]').forEach(function (el) {
        el.addEventListener('click', function (e) {
            e.preventDefault();
            closeModal();
        });
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.classList.contains('is-open')) {
            closeModal();
        }
    });

    var skipBtn = modal.querySelector('[data-icais-skip]');
    if (skipBtn) {
        skipBtn.addEventListener('click', function (e) {
            e.preventDefault();
            goDeactivate();
        });
    }

    var submitBtn = modal.querySelector('[data-icais-submit]');
    if (submitBtn) {
        submitBtn.addEventListener('click', function (e) {
            e.preventDefault();
            var checked = modal.querySelector('input[name="icais_reason"]:checked');
            if (!checked) {
                goDeactivate();
                return;
            }

            var reason = checked.value;
            var detail = modal.querySelector('.icais-reason-detail[data-for="' + reason + '"] textarea, .icais-reason-detail[data-for="' + reason + '"] input');
            var message = detail ? detail.value : '';

            submitBtn.disabled = true;

            // Never trap the user: deactivate even if the request hangs or fails.
            var done = false;
            function finish() {
                if (done) { return; }
                done = true;
                goDeactivate();
            }
            setTimeout(finish, 2500);

            try {
                fetch(cfg.restUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': cfg.nonce
                    },
                    body: JSON.stringify({ reason: reason, message: message, action: 'deactivate' })
                }).then(finish).catch(finish);
            } catch (err) {
                finish();
            }
        });
    }
})();
JS;

        wp_add_inline_script('icais-deactivation-survey', $js);
    }
}
