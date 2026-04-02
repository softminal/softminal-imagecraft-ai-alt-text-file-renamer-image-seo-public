<?php

namespace Softminal\ImageCraft;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Reusable Vite asset enqueue helper.
 *
 * Resolves entry paths to dev server URLs or production manifest URLs
 * and automatically adds type="module" to script tags.
 */
class EnqueueAsset {
    private const DEV_SERVER_URL = 'http://localhost:4000';

    /** @var array|null Cached Vite manifest */
    private static ?array $manifest = null;

    /** @var array Script handles that need type="module" */
    private static array $moduleHandles = [];

    /** @var bool Whether the script_loader_tag filter has been registered */
    private static bool $filterRegistered = false;

    /**
     * Check if the plugin is in dev mode (Vite dev server).
     */
    public static function isDev(): bool {
        return defined('ICAIS_DEV_MODE') && ICAIS_DEV_MODE;
    }

    /**
     * Enqueue a Vite-managed JS entry.
     *
     * Automatically resolves the URL from the dev server or production manifest,
     * registers the handle for type="module" injection, and enqueues any
     * associated CSS files from the manifest in production.
     */
    public static function script(string $handle, string $entryPath, array $deps = [], bool $inFooter = true): bool {
        $url = self::resolveUrl($entryPath);
        if (!$url) {
            return false;
        }

        self::ensureFilter();
        wp_enqueue_script($handle, $url, $deps, ICAIS_VERSION, $inFooter);

        if (!in_array($handle, self::$moduleHandles, true)) {
            self::$moduleHandles[] = $handle;
        }

        // In production, auto-enqueue any CSS associated with this entry
        if (!self::isDev()) {
            $manifest = self::getManifest();
            if (!empty($manifest[$entryPath]['css'])) {
                foreach ($manifest[$entryPath]['css'] as $i => $cssFile) {
                    $cssHandle = $handle . '-css' . ($i > 0 ? '-' . $i : '');
                    wp_enqueue_style($cssHandle, ICAIS_URL . 'assets/' . $cssFile, [], ICAIS_VERSION);
                }
            }
        }

        return true;
    }

    /**
     * Enqueue a Vite-managed CSS/SCSS entry.
     *
     * In dev mode, loads from the Vite dev server (works for SCSS too).
     * In production, resolves from the manifest.
     */
    public static function style(string $handle, string $entryPath, array $deps = []): bool {
        $url = self::resolveUrl($entryPath);
        if (!$url) {
            return false;
        }

        wp_enqueue_style($handle, $url, $deps, ICAIS_VERSION);

        return true;
    }

    /**
     * Resolve a Vite entry path to its URL.
     */
    private static function resolveUrl(string $entryPath): ?string {
        if (self::isDev()) {
            return self::DEV_SERVER_URL . '/' . $entryPath;
        }

        $manifest = self::getManifest();
        if (empty($manifest) || !isset($manifest[$entryPath])) {
            return null;
        }

        return ICAIS_URL . 'assets/' . $manifest[$entryPath]['file'];
    }

    /**
     * Load and cache the Vite manifest.
     */
    private static function getManifest(): ?array {
        if (self::$manifest !== null) {
            return self::$manifest;
        }

        self::$manifest = wp_json_file_decode(ICAIS_DIR . 'assets/.vite/manifest.json', ['associative' => true]);
        if (empty(self::$manifest)) {
            self::$manifest = wp_json_file_decode(ICAIS_DIR . 'assets/manifest.json', ['associative' => true]);
        }

        return self::$manifest ?: null;
    }

    /**
     * Register the script_loader_tag filter once.
     */
    private static function ensureFilter(): void {
        if (self::$filterRegistered) {
            return;
        }

        add_filter('script_loader_tag', [self::class, 'filterScriptModule'], 10, 2);
        self::$filterRegistered = true;
    }

    /**
     * Add type="module" to Vite-managed script tags.
     */
    public static function filterScriptModule(string $tag, string $handle): string {
        if (in_array($handle, self::$moduleHandles, true)) {
            return str_replace('<script ', '<script type="module" ', $tag);
        }
        return $tag;
    }
}
