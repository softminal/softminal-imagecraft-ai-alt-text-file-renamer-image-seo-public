<?php

namespace SmartAltTextAi\Includes\Http\Controllers;

if (!defined('ABSPATH')) {
    exit;
}

abstract class Controller
{
    protected function response($data, $status = 200)
    {
        return new \WP_REST_Response($data, $status);
    }

    private function verifyNonce($request)
    {
        $nonce = $request->get_header('X-WP-Nonce');
        
        if (!wp_verify_nonce($nonce, 'wp_rest')) {
            return $this->response([
                'success' => false,
                'message' => 'Invalid nonce token'
            ], 403);
        }
        
        return true;
    }

    public function __call($name, $arguments)
    {
        $request = $arguments[0];
        
        // Verify nonce first
        $nonceCheck = $this->verifyNonce($request);
        if ($nonceCheck !== true) {
            return $nonceCheck;
        }

        // If nonce is valid, call the actual method
        if (method_exists($this, $name)) {
            return $this->$name($request);
        }

        return $this->response([
            'success' => false,
            'message' => 'Method not found'
        ], 404);
    }
}