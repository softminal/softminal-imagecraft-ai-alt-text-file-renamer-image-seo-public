<?php

namespace ImageCraft\Includes\Http\Sanitizer;

if (!defined('ABSPATH')) {
    exit;
}

class RequestSanitizer
{
    /**
     * Sanitize any input data based on type
     */
    public static function sanitize($data, string $type = 'text')
    {
        if (is_array($data)) {
            return self::sanitizeArray($data);
        }

        switch ($type) {
            case 'text':
                return sanitize_text_field($data);
            case 'html':
                return wp_kses_post($data);
            case 'email':
                return sanitize_email($data);
            case 'url':
                return esc_url_raw($data);
            case 'int':
                return absint($data);
            case 'float':
                return (float) sanitize_text_field($data);
            case 'textarea':
                return sanitize_textarea_field($data);
            case 'filename':
                return sanitize_file_name($data);
            case 'key':
                return sanitize_key($data);
            default:
                return sanitize_text_field($data);
        }
    }

    /**
     * Recursively sanitize an array of data
     */
    public static function sanitizeArray(array $data, array $rules = []): array
    {
        $sanitized = [];

        foreach ($data as $key => $value) {
            // If rules exist for this key, use the specified type
            $type = $rules[$key] ?? 'text';

            if (is_array($value)) {
                $sanitized[$key] = self::sanitizeArray($value);
                continue;
            }

            $sanitized[$key] = self::sanitize($value, $type);
        }

        return $sanitized;
    }

    /**
     * Sanitize request parameters with specific rules
     */
    public static function sanitizeRequest($request, array $rules = []): array
    {
        $params = $request instanceof \WP_REST_Request 
            ? $request->get_params()
            : $request;

        return self::sanitizeArray($params, $rules);
    }
}
