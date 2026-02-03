<?php

namespace SmartAltTextAi\Includes\Http\Routes;

use SmartAltTextAi\Includes\Http\Routes\Route;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Router class for handling API routes.
 *
 * @package SmartAltTextAi\Includes\Http\Routes
 */
class Router {
    /**
     * API namespace
     *
     * @var string
     */
    private $namespace;

    /**
     * Initialize the router
     */
    public function __construct() {
        $this->namespace = SATAI_API_NAMESPACE;
    }

    /**
     * Initialize the router
     */
    public function init() {
        // Load API route definitions
        require_once SATAI_DIR . 'includes/Http/api.php';

        // Register routes with WordPress REST API
        add_action('rest_api_init', [$this, 'registerRoutes']);

        // Add debug information if in debug mode
        // if (defined('WP_DEBUG') && WP_DEBUG) {
        //     add_action('admin_footer', [$this, 'debugRoutes']);
        // }
    }

    /**
     * Register all routes with WordPress REST API
     */
    public function registerRoutes() {
        // Ensure we have a valid namespace
        if (empty($this->namespace)) {
            return;
        }

        try {
            // Call the static method on the Route class to register all routes
            Route::registerRoutes($this->namespace);

            // Register a test endpoint to verify the API is working
            register_rest_route($this->namespace, '/test', [
                'methods' => 'GET',
                'callback' => function() {
                    return [
                        'status' => 'success',
                        'message' => 'API is working correctly',
                        'routes' => Route::getRegisteredRoutes()
                    ];
                },
                'permission_callback' => function() {
                    return true; // Open endpoint for testing
                }
            ]);
        } catch (\Exception $e) {

        }
    }

    /**
     * Debug the registered routes in admin footer
     */
    // public function debugRoutes() {
    //     if (!current_user_can('manage_options')) {
    //         return;
    //     }

    //     echo '<script>console.group("AltText AI API Routes");</script>';
    //     foreach (Route::getRegisteredRoutes() as $route) {
    //         $endpoint = $this->namespace . '/' . $route['endpoint'];
    //         echo '<script>console.log("' . esc_js($route['method']) . ': ' . esc_js($endpoint) . '");</script>';
    //     }
    //     echo '<script>console.groupEnd();</script>';
    // }
}
