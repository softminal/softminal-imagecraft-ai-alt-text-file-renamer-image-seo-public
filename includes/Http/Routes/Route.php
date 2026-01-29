<?php

namespace WpAiAltText\Includes\Http\Routes;

use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

class Route
{
    private static $routes = [];
    private static $prefix = '';
    private static $middleware = [];
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

    public function group(callable $callback)
    {
        $previousPrefix = self::$prefix;
        $previousMiddleware = self::$middleware;
        
        $callback();
        
        self::$prefix = $previousPrefix;
        self::$middleware = $previousMiddleware;
        
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

    public static function put($endpoint, $controller)
    {
        return self::addRoute('PUT', $endpoint, $controller);
    }

    public static function delete($endpoint, $controller)
    {
        return self::addRoute('DELETE', $endpoint, $controller);
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
            'parameters' => $parameters,
            'pattern' => implode('/', $pattern)
        ];

        self::$routes[] = $route;
        self::$lastRoute = &self::$routes[count(self::$routes) - 1];

        return new static();
    }

    public static function resource($controller)
    {
        // Define common RESTful routes for a resource
        // GET /resource - List all resources
        self::get('', $controller);
        
        // GET /resource/{id} - Get a single resource
        self::get('{id}', [$controller, 'show']);
        
        // POST /resource - Create a resource
        self::post('', [$controller, 'store']);
        
        // PUT /resource/{id} - Update a resource
        self::put('{id}', [$controller, 'update']);
        
        // DELETE /resource/{id} - Delete a resource
        self::delete('{id}', [$controller, 'destroy']);
        
        return new static();
    }

    public static function getWithId($endpoint, $controller, $params = [])
    {
        if (strpos($endpoint, '{id}') === false) {
            $endpoint = '{id}/' . trim($endpoint, '/');
        }
        
        return self::addRoute('GET', $endpoint, $controller, $params);
    }

    public static function postWithId($endpoint, $controller, $params = [])
    {
        if (strpos($endpoint, '{id}') === false) {
            $endpoint = '{id}/' . trim($endpoint, '/');
        }
        
        return self::addRoute('POST', $endpoint, $controller, $params);
    }

    public static function registerRoutes($namespace = null) {
        // Use default namespace if not provided
        if ($namespace === null) {
            $namespace = defined('WP_AI_ALT_TEXT_API_NAMESPACE') 
                ? WP_AI_ALT_TEXT_API_NAMESPACE 
                : 'wp-ai-alt-text/v1';
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
                        return new \WP_Error(
                            'api_error',
                            $e->getMessage(),
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
                'You must be logged in.',
                ['status' => 401]
            );
        }

        // Check user capabilities
        if (!current_user_can('edit_posts')) {
            return new WP_Error(
                'rest_forbidden',
                'Insufficient permissions.',
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
                        'Invalid middleware configuration',
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
                            'Access denied',
                            ['status' => 403]
                        );
                    }
                }
            } catch (\Exception $e) {
                return new WP_Error(
                    'server_error',
                    'Internal server error',
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