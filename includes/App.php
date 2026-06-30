<?php

namespace Softminal\ImageCraft;

if (!defined('ABSPATH')) {
    exit;
}

use Exception;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Softminal\ImageCraft\Enqueue;
use Softminal\ImageCraft\Http\Routes\Router;
use Softminal\ImageCraft\Menu;

/**
 * Class App
 * @package Softminal\ImageCraft
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
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor to prevent direct instantiation
     */
    private function __construct()
    {
        try {
            $this->loadDependencies();
            $this->initializeClasses();
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Load all required dependencies dynamically
     * @throws Exception
     */
    private function loadDependencies(): void
    {
        // Define load order: directories and their priority files
        $loadOrder = [
            // 1. Support files (no dependencies)
            ['dir' => 'includes/Support', 'priority' => []],

            // 2. HTTP Routes (base Route first)
            ['dir' => 'includes/Http/Routes', 'priority' => ['Route.php']],

            // 2b. HTTP Middleware (referenced by route definitions)
            ['dir' => 'includes/Http/Middleware', 'priority' => []],

            // 3. HTTP Controllers (base Controller first)
            ['dir' => 'includes/Http/Controllers', 'priority' => ['Controller.php']],

            // 4. HTTP Sanitizer
            ['dir' => 'includes/Http/Sanitizer', 'priority' => []],

            // 5. Models (base Model first, then Relations)
            // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude -- Not a WP query, just autoloader config
            ['dir' => 'includes/Models', 'priority' => ['Model.php'], 'exclude' => ['Relations']],
            ['dir' => 'includes/Models/Relations', 'priority' => []],

            // 6. Services (base classes first)
            // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude -- Not a WP query, just autoloader config
            ['dir' => 'includes/Services', 'priority' => [], 'exclude' => ['AI'], 'excludeFiles' => ['WooCommerce.php', 'FileRenamer.php', 'AIFactory.php', 'Encryption.php', 'Settings.php']],
            ['dir' => 'includes/Services', 'files' => ['Encryption.php', 'Settings.php']],
            ['dir' => 'includes/Services/AI', 'priority' => ['BaseAIProvider.php'], 'excludeFiles' => ['AIFactory.php']],
            ['dir' => 'includes/Services/AI', 'files' => ['AIFactory.php']],
            ['dir' => 'includes/Services', 'files' => ['WooCommerce.php', 'FileRenamer.php', 'SeoIntegration.php', 'FileBird.php']],

            // 7. Core files (specific order)
            ['dir' => 'includes/Http', 'files' => ['api.php']],
            ['dir' => 'includes', 'files' => ['EnqueueAsset.php', 'Enqueue.php', 'Menu.php']],

            // 8. AutoGenerate, AdminNotice and DeactivationSurvey (load last as they depend on everything)
            // NOTE: auto-compress-on-upload is a Pro feature and lives in the Pro add-on, not here.
            ['dir' => 'includes', 'files' => ['AutoGenerate.php', 'AdminNotice.php', 'DeactivationSurvey.php']],
        ];

        foreach ($loadOrder as $config) {
            $this->loadDirectory($config);
        }
    }

    /**
     * Load files from a directory with optional priority and exclusions
     * @param array $config Configuration array with dir, priority, exclude, files
     * @throws Exception
     */
    private function loadDirectory(array $config): void
    {
        $dir = ICAIS_DIR . $config['dir'];

        // If specific files are requested, load only those
        if (isset($config['files']) && is_array($config['files'])) {
            foreach ($config['files'] as $file) {
                $filePath = $dir . '/' . $file;
                // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_exists -- Autoloader runs before WP_Filesystem is available
                if (file_exists($filePath)) {
                    require_once $filePath;
                }
            }
            return;
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_dir -- Autoloader runs before WP_Filesystem is available
        if (!is_dir($dir)) {
            return; // Directory doesn't exist, skip
        }

        $priority = $config['priority'] ?? [];
        $exclude = $config['exclude'] ?? [];
        $excludeFiles = $config['excludeFiles'] ?? [];

        // Get all PHP files
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isDir() || $file->getExtension() !== 'php') {
                continue;
            }

            $relativePath = str_replace($dir . '/', '', $file->getPathname());
            $relativeDir = str_replace($dir . '/', '', $file->getPath());
            $fileName = $file->getBasename();

            // Check if this file should be excluded by name
            if (in_array($fileName, $excludeFiles)) {
                continue;
            }

            // Check if this path should be excluded
            $shouldExclude = false;
            foreach ($exclude as $excludePattern) {
                if (strpos($relativePath, $excludePattern) !== false ||
                    strpos($relativeDir, $excludePattern) !== false) {
                    $shouldExclude = true;
                    break;
                }
            }

            if ($shouldExclude) {
                continue;
            }

            $files[] = [
                'path' => $file->getPathname(),
                'name' => $file->getBasename(),
                'priority' => array_search($file->getBasename(), $priority)
            ];
        }

        // Sort: priority files first, then alphabetically
        usort($files, function($a, $b) {
            $aPriority = $a['priority'] !== false ? $a['priority'] : 999;
            $bPriority = $b['priority'] !== false ? $b['priority'] : 999;

            if ($aPriority !== $bPriority) {
                return $aPriority <=> $bPriority;
            }

            return strcmp($a['name'], $b['name']);
        });

        // Load files in sorted order
        foreach ($files as $file) {
            require_once $file['path'];
        }
    }

    /**
     * Initialize all required classes
     */
    private function initializeClasses(): void
    {
        $this->enqueue = new Enqueue();
        $this->menu = new Menu();
        $this->router = new Router();
    }

    /**
     * Migrate per-user wizard_completed meta to a site-wide option.
     * Runs once after plugin replacement — no activation hook required.
     */
    private function maybeMigrateWizardFlag(): void
    {
        if (get_option(ICAIS_TABLE_PREFIX . 'wizard_completed', false)) {
            return; // Already stamped — nothing to do
        }

        $users = get_users([
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- One-time guarded migration; skipped once the site-wide option is stamped, and bounded by number=>1.
            'meta_key'   => ICAIS_TABLE_PREFIX . 'wizard_completed',
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- One-time guarded migration; skipped once the site-wide option is stamped, and bounded by number=>1.
            'meta_value' => '1',
            'number'     => 1,
            'fields'     => 'ids',
        ]);

        if (!empty($users)) {
            update_option(ICAIS_TABLE_PREFIX . 'wizard_completed', true);
        }
    }

    /**
     * Run the application
     * @throws Exception
     */
    public function run(): void
    {
        try {
            if (class_exists('\Softminal\ImageCraft\Install')) {
                \Softminal\ImageCraft\Install::maybeUpgrade();
            }
            $this->maybeMigrateWizardFlag();
            $this->enqueue->init();
            $this->menu->init();
            $this->router->init();

            // Auto-generate-on-upload is a Pro feature: the Pro add-on owns the
            // add_attachment handler (Features\Meta), which calls the free
            // GeneratorController::generateMetaForImage core. The free plugin no
            // longer auto-generates on upload (AutoGenerate::init() intentionally
            // not called). The class file is kept for backwards-compat references.

            if (class_exists('\Softminal\ImageCraft\AdminNotice')) {
                \Softminal\ImageCraft\AdminNotice::init();
            }

            if (class_exists('\Softminal\ImageCraft\DeactivationSurvey')) {
                \Softminal\ImageCraft\DeactivationSurvey::init();
            }
        } catch (Exception $e) {
            throw $e;
        }
    }
}
