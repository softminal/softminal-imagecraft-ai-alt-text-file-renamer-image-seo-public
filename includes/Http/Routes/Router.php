<?php

namespace Softminal\ImageCraft\Http\Routes;

use Softminal\ImageCraft\Http\Routes\Route;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Router class for handling API routes.
 *
 * @package Softminal\ImageCraft\Http\Routes
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
        $this->namespace = ICAIS_API_NAMESPACE;
    }

    /**
     * Initialize the router
     */
    public function init() {
        // Load API route definitions
        require_once ICAIS_DIR . 'includes/Http/api.php';

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
        } catch (\Exception $e) {
            // Route registration failed silently
        }
    }

}
