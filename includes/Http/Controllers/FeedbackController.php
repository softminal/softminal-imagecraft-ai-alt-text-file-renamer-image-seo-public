<?php

namespace Softminal\ImageCraft\Http\Controllers;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles optional, user-initiated feedback such as the deactivation survey.
 *
 * Feedback is only ever sent when the user explicitly clicks "Submit & Deactivate"
 * in the survey modal — never silently. The payload is forwarded to the Softminal
 * feedback endpoint via wp_remote_post(). A remote failure never blocks the user:
 * the modal redirects to the real deactivation URL regardless of the response.
 */
class FeedbackController extends Controller
{
    /**
     * Plugin slug the remote endpoint keys feedback by.
     */
    private const PLUGIN_SLUG = 'imagecraft';

    /**
     * Allowed reason keys — must match the Softminal feedback API's vocabulary.
     * Anything outside this list is rejected so the endpoint only ever receives
     * known, expected values.
     */
    private const ALLOWED_REASONS = [
        'temporary',
        'not_working',
        'better_found',
        'too_complex',
        'no_longer',
        'other',
    ];

    /**
     * Receive a deactivation survey response and forward it to Softminal.
     */
    public function deactivation($request)
    {
        $params = $request->get_json_params();

        $reason = sanitize_text_field($params['reason'] ?? '');
        if (!in_array($reason, self::ALLOWED_REASONS, true)) {
            return $this->response([
                'success' => false,
                'message' => __('Invalid feedback reason', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            ], 400);
        }

        // Only "deactivate" / "delete" are meaningful; default and coerce to deactivate.
        $action = sanitize_text_field($params['action'] ?? 'deactivate');
        if ($action !== 'delete') {
            $action = 'deactivate';
        }

        $message = sanitize_textarea_field($params['message'] ?? '');
        // Keep the free-text comment bounded.
        if (strlen($message) > 2000) {
            $message = substr($message, 0, 2000);
        }

        $payload = [
            'plugin_slug'    => self::PLUGIN_SLUG,
            'action'         => $action,
            'reason'         => $reason,
            'message'        => $message,
            'site_url'       => home_url(),
            'plugin_version' => substr((string) (defined('ICAIS_VERSION') ? ICAIS_VERSION : ''), 0, 32),
            'wp_version'     => substr((string) get_bloginfo('version'), 0, 32),
            'php_version'    => substr(PHP_VERSION, 0, 32),
        ];

        $sent = $this->forwardToRemote($payload);

        // Always report success to the client — feedback is best-effort and must
        // never prevent the user from completing the deactivation they requested.
        return $this->response([
            'success'    => true,
            'forwarded'  => $sent,
            'message'    => __('Thanks for your feedback', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
        ]);
    }

    /**
     * POST the feedback payload to the Softminal endpoint.
     *
     * The URL can be overridden with the ICAIS_FEEDBACK_ENDPOINT constant or the
     * `icais_feedback_endpoint` filter. Returns true only on a 2xx response.
     */
    private function forwardToRemote(array $payload): bool
    {
        $endpoint = defined('ICAIS_FEEDBACK_ENDPOINT')
            ? ICAIS_FEEDBACK_ENDPOINT
            : 'https://admin.softminal.com/wp-json/softminal/v1/feedback';

        /**
         * Filters the deactivation-feedback endpoint URL.
         *
         * @param string $endpoint The remote URL feedback is posted to.
         */
        $endpoint = apply_filters('icais_feedback_endpoint', $endpoint);

        if (empty($endpoint) || !wp_http_validate_url($endpoint)) {
            return false;
        }

        $response = wp_remote_post($endpoint, [
            'timeout'     => 5,
            'blocking'    => true,
            'headers'     => ['Content-Type' => 'application/json'],
            'body'        => wp_json_encode($payload),
            'user-agent'  => 'ImageCraft/' . (defined('ICAIS_VERSION') ? ICAIS_VERSION : '0') . '; ' . home_url(),
        ]);

        if (is_wp_error($response)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging only when WP_DEBUG is enabled
                error_log('ImageCraft feedback forward failed: ' . $response->get_error_message());
            }
            return false;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        return $code >= 200 && $code < 300;
    }
}
