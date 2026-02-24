<?php

namespace Softminal\ImageCraft\Http\Routes;

use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

class Route
{
    private static $routes = [];
    private static $prefix = '';
    private static $middleware = [];
    private static $capability = 'manage_options';
    private static $namespace = '';
    private static $lastRoute = null;

    public static function prefix($prefix)
    {
        $instance = new static();
        self::$prefix = '/' . trim($prefix, '/');
        return $instance;
    }

    public static function middleware($middleware)
    {
        $instance = new static();
        self::$middleware = array_merge(
            self::$middleware,
            is_array($middleware) ? $middleware : [$middleware]
        );
        return $instance;
    }

    public function capability(string $capability)
    {
        self::$capability = $capability;
        return $this;
    }

    public function group(callable $callback)
    {
        $previousPrefix = self::$prefix;
        $previousMiddleware = self::$middleware;
        $previousCapability = self::$capability;

        $callback();

        self::$prefix = $previousPrefix;
        self::$middleware = $previousMiddleware;
        self::$capability = $previousCapability;

        return $this;
    }

    public static function get($endpoint, $controller)
    {
        return self::addRoute('GET', $endpoint, $controller);
    }

    public static function post($endpoint, $controller)
    {
        return self::addRoute('POST', $endpoint, $controller);
    }

    private static function addRoute($method, $endpoint, $controller, $params = [])
    {
        $endpoint = self::$prefix . '/' . trim($endpoint, '/');

        if (is_array($controller)) {
            $handler = $controller[0];
            $action = $controller[1];
        } else {
            $handler = $controller;
            $action = 'index';
        }

        if (self::$namespace) {
            $handler = self::$namespace . '\\' . $handler;
        }

        // Process endpoint to identify parameters
        $parts = explode('/', $endpoint);
        $pattern = [];
        $parameters = [];

        foreach ($parts as $part) {
            if (strpos($part, '{') === 0 && strpos($part, '}') === strlen($part) - 1) {
                // This is a parameter
                $paramName = trim($part, '{}');
                $parameterPattern = isset($params[$paramName]) ? $params[$paramName] : '(?P<' . $paramName . '>[^/]+)';
                $pattern[] = $parameterPattern;
                $parameters[] = $paramName;
            } else {
                $pattern[] = $part;
            }
        }

        $route = [
            'method' => $method,
            'endpoint' => $endpoint,
            'controller' => $handler,
            'action' => $action,
            'middleware' => self::$middleware,
            'capability' => self::$capability,
            'parameters' => $parameters,
            'pattern' => implode('/', $pattern)
        ];

        self::$routes[] = $route;
        self::$lastRoute = &self::$routes[count(self::$routes) - 1];

        return new static();
    }

    public static function registerRoutes($namespace = null) {
        // Use default namespace if not provided
        if ($namespace === null) {
            $namespace = defined('ICAIS_API_NAMESPACE')
                ? ICAIS_API_NAMESPACE
                : 'icais/v1';
        }
        if (empty(self::$routes)) {
            return;
        }

        foreach (self::$routes as $route) {
            $endpoint = $route['endpoint'];
            $method = $route['method'];

            // For endpoints with parameters
            if (!empty($route['parameters'])) {
                // Use the pattern if it exists, otherwise build it
                $pattern = isset($route['pattern']) ? $route['pattern'] : $endpoint;
            } else {
                $pattern = $endpoint;
            }


            register_rest_route($namespace, $pattern, [
                'methods' => $method,
                'callback' => function($request) use ($route) {
                    try {
                        $controller = new $route['controller']();
                        $action = $route['action'];

                        // Pass any URL parameters to the controller method
                        $params = $request->get_params();

                        return $controller->$action($request, $params);
                    } catch (\Exception $e) {
                        if (defined('WP_DEBUG') && WP_DEBUG) {
                            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging only when WP_DEBUG is enabled
                            error_log('ImageCraft API error: ' . $e->getMessage());
                        }

                        return new \WP_Error(
                            'api_error',
                            __('An unexpected error occurred.', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
                            ['status' => 500]
                        );
                    }
                },
                'permission_callback' => function($request) use ($route) {
                    return self::handleMiddleware($request, $route);
                }
            ]);
        }
    }

    private static function handleMiddleware($request, $route)
    {
        // Default security check even without middleware
        if (!is_user_logged_in()) {
            return new WP_Error(
                'rest_unauthorized',
                __('You must be logged in.', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
                ['status' => 401]
            );
        }

        // Check user capabilities
        $capability = $route['capability'] ?? 'manage_options';
        if (!current_user_can($capability)) {
            return new WP_Error(
                'rest_forbidden',
                __('Insufficient permissions.', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
                ['status' => 403]
            );
        }

        if (empty($route['middleware'])) {
            return true;
        }

        foreach ($route['middleware'] as $middleware) {
            try {
                if (!class_exists($middleware)) {
                    return new WP_Error(
                        'invalid_middleware',
                        __('Invalid middleware configuration', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
                        ['status' => 500]
                    );
                }

                $instance = new $middleware();
                if (method_exists($instance, 'handle')) {
                    $result = $instance->handle($request);
                    if ($result instanceof WP_Error) {
                        return $result;
                    }
                    if ($result !== true) {
                        return new WP_Error(
                            'middleware_failed',
                            __('Access denied', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
                            ['status' => 403]
                        );
                    }
                }
            } catch (\Exception $e) {
                return new WP_Error(
                    'server_error',
                    __('Internal server error', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
                    ['status' => 500]
                );
            }
        }

        return true;
    }

    public function withPolicy($policy)
    {
        if (self::$lastRoute) {
            self::$lastRoute['middleware'][] = $policy;
        }
        return $this;
    }

    /**
     * Get all registered routes for debugging purposes
     *
     * @return array
     */
    public static function getRegisteredRoutes() {
        return self::$routes;
    }
}
