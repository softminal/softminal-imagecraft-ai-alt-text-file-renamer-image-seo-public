<?php

namespace WpAiAltText\Includes;

if (!defined('ABSPATH')) {
    exit;
}

use WpAiAltText\Includes\Enqueue;
use WpAiAltText\Includes\Menu;
use WpAiAltText\Includes\Http\Routes\Router;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use Exception;

/**
 * Class App
 * @package WpAiAltText\Includes
 *
 * This class is responsible for loading all the necessary components of the plugin.
 */
class App
{
    private static ?self $instance = null;
    private Enqueue $enqueue;
    private Menu $menu;
    private Router $router;

    /**
     * Get the singleton instance
     */
    static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor to prevent direct instantiation
     */
    function __construct()
    {
        try {
            $this->loadDependencies();
            $this->initializeClasses();
        } catch (Exception $e) {
            // Log the error and handle it appropriately
            error_log('WP AI Alt Text Plugin Error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Load all required dependencies
     * @throws Exception
     */
    function loadDependencies(): void
    {
        $requiredFiles = [
            // Http files (must be loaded first)
            'includes/Http/Routes/Route.php',
            'includes/Http/Routes/Router.php',
            'includes/Http/Controllers/Controller.php',
            'includes/Http/Sanitizer/RequestSanitizer.php',
            
            // Support files
            'includes/Support/Collection.php',
            
            // Core files
            'includes/Http/api.php',
            'includes/Enqueue.php',
            'includes/Menu.php',
            
            // Models (base model first)
            'includes/Models/Model.php',
        ];

        foreach ($requiredFiles as $file) {
            $filePath = WP_AI_ALT_TEXT_DIR . $file;
            if (!file_exists($filePath)) {
                throw new Exception("Required file not found: {$file}");
            }
            require_once $filePath;
        }

        // Dynamically load controllers and models
        $this->loadControllers();
        $this->loadModels();
    }

    /**
     * Load all controller files dynamically
     * @throws Exception
     */
    function loadControllers(): void
    {
        $controllersDir = WP_AI_ALT_TEXT_DIR . 'includes/Http/Controllers';
        
        if (!is_dir($controllersDir)) {
            return; // Controllers directory is optional
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($controllersDir)
        );

        foreach ($iterator as $file) {
            if ($file->isDir() || $file->getExtension() !== 'php') {
                continue;
            }

            // Skip base Controller.php (already loaded)
            if ($file->getBasename() === 'Controller.php') {
                continue;
            }

            require_once $file->getPathname();
        }
    }

    /**
     * Load all model files dynamically
     * @throws Exception
     */
    function loadModels(): void
    {
        $modelsDir = WP_AI_ALT_TEXT_DIR . 'includes/Models';
        
        if (!is_dir($modelsDir)) {
            return; // Models directory is optional
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($modelsDir)
        );

        foreach ($iterator as $file) {
            if ($file->isDir() || $file->getExtension() !== 'php') {
                continue;
            }

            // Skip base Model.php (already loaded)
            if ($file->getBasename() === 'Model.php') {
                continue;
            }

            require_once $file->getPathname();
        }
    }

    /**
     * Initialize all required classes
     */
    function initializeClasses(): void
    {
        $this->enqueue = new Enqueue();
        $this->menu = new Menu();
        $this->router = new Router();
    }

    /**
     * Run the application
     * @throws Exception
     */
    function run(): void
    {
        try {
            $this->enqueue->init();
            $this->menu->init();
            $this->router->init();
        } catch (Exception $e) {
            error_log('WP AI Alt Text Plugin Error: ' . $e->getMessage());
            throw $e;
        }
    }
}