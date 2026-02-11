<?php

namespace SmartAltTextAi\Includes\Http;

use SmartAltTextAi\Includes\Http\Controllers\BatchController;
use SmartAltTextAi\Includes\Http\Controllers\FileRenamerController;
use SmartAltTextAi\Includes\Http\Controllers\GeneratorController;
use SmartAltTextAi\Includes\Http\Controllers\ScannerController;
use SmartAltTextAi\Includes\Http\Controllers\SettingsController;
use SmartAltTextAi\Includes\Http\Controllers\StatsController;
use SmartAltTextAi\Includes\Http\Controllers\WooCommerceController;
use SmartAltTextAi\Includes\Http\Routes\Route;

if (!defined('ABSPATH')) {
    exit;
}

// Settings routes
Route::prefix('settings')->group(function () {
    Route::get('/', [SettingsController::class, 'index']);
    Route::post('/', [SettingsController::class, 'update']);
    Route::post('/api-key', [SettingsController::class, 'saveApiKey']);
    Route::post('/test-api', [SettingsController::class, 'testApi']);
    Route::post('/wizard/complete', [SettingsController::class, 'completeWizard']);
});

// Scanner routes
Route::prefix('scanner')->group(function () {
    Route::get('/scan', [ScannerController::class, 'scan']);
    Route::get('/select-all', [ScannerController::class, 'selectAll']);
});

// Generator routes
Route::prefix('generator')->group(function () {
    Route::post('/generate', [GeneratorController::class, 'generate']);
    Route::post('/accept', [GeneratorController::class, 'accept']);
    Route::post('/accept-bulk', [GeneratorController::class, 'acceptBulk']);
    Route::post('/update-alt', [GeneratorController::class, 'updateAlt']);
    Route::post('/reject', [GeneratorController::class, 'reject']);
});

// Batch routes
Route::prefix('batch')->group(function () {
    Route::post('/start', [BatchController::class, 'start']);
    Route::get('/status', [BatchController::class, 'status']);
    Route::get('/results', [BatchController::class, 'results']);
    Route::post('/process', [BatchController::class, 'process']);
    Route::post('/cancel', [BatchController::class, 'cancel']);
});

// Stats routes
Route::prefix('stats')->group(function () {
    Route::get('/dashboard', [StatsController::class, 'dashboard']);
    Route::get('/audit', [StatsController::class, 'audit']);
});

// File Renamer routes
Route::prefix('renamer')->group(function () {
    Route::post('/rename', [FileRenamerController::class, 'rename']);
    Route::post('/rename-bulk', [FileRenamerController::class, 'renameBulk']);
    Route::get('/preview', [FileRenamerController::class, 'preview']);
});

// WooCommerce routes
Route::prefix('woocommerce')->group(function () {
    Route::get('/products', [WooCommerceController::class, 'getProducts']);
    Route::get('/categories', [WooCommerceController::class, 'getCategories']);
    Route::get('/stats', [WooCommerceController::class, 'getStats']);
    Route::post('/generate', [WooCommerceController::class, 'generate']);
    Route::post('/generate-gallery', [WooCommerceController::class, 'generateGallery']);
    Route::get('/gallery', [WooCommerceController::class, 'getGallery']);
});

