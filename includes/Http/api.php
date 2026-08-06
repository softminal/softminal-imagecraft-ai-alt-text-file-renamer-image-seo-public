<?php

namespace Softminal\ImageCraft\Http;

use Softminal\ImageCraft\AdminNotice;
use Softminal\ImageCraft\Http\Controllers\BatchController;
use Softminal\ImageCraft\Http\Controllers\CompressorController;
use Softminal\ImageCraft\Http\Controllers\FallbackController;
use Softminal\ImageCraft\Http\Controllers\FeedbackController;
use Softminal\ImageCraft\Http\Controllers\FileBirdController;
use Softminal\ImageCraft\Http\Controllers\FileRenamerController;
use Softminal\ImageCraft\Http\Controllers\GeneratorController;
use Softminal\ImageCraft\Http\Controllers\ScannerController;
use Softminal\ImageCraft\Http\Controllers\SettingsController;
use Softminal\ImageCraft\Http\Controllers\StatsController;
use Softminal\ImageCraft\Http\Controllers\WooCommerceController;
use Softminal\ImageCraft\Http\Routes\Route;

if (!defined('ABSPATH')) {
    exit;
}

// Settings routes (admin only)
Route::prefix('settings')->capability('manage_options')->group(function () {
    Route::get('/', [SettingsController::class, 'index']);
    Route::post('/', [SettingsController::class, 'update']);
    Route::post('/api-key', [SettingsController::class, 'saveApiKey']);
    Route::post('/test-api', [SettingsController::class, 'testApi']);
    Route::post('/wizard/complete', [SettingsController::class, 'completeWizard']);
});

// Scanner routes
Route::prefix('scanner')->capability('upload_files')->group(function () {
    Route::get('/scan', [ScannerController::class, 'scan']);
    Route::get('/select-all', [ScannerController::class, 'selectAll']);
});

// Generator routes (role-gated via RoleGate: alt text, title, filename generation)
Route::prefix('generator')->capability('upload_files')->middleware([\Softminal\ImageCraft\Http\Middleware\RoleGate::class])->group(function () {
    Route::post('/generate', [GeneratorController::class, 'generate']);
    Route::post('/generate-meta', [GeneratorController::class, 'generateMeta']);
    Route::post('/accept', [GeneratorController::class, 'accept']);
    Route::post('/accept-bulk', [GeneratorController::class, 'acceptBulk']);
    Route::post('/accept-meta', [GeneratorController::class, 'acceptMeta']);
    Route::post('/update-alt', [GeneratorController::class, 'updateAlt']);
    Route::post('/reject', [GeneratorController::class, 'reject']);
});

// Batch routes (admin only)
Route::prefix('batch')->capability('manage_options')->group(function () {
    Route::post('/start', [BatchController::class, 'start']);
    Route::get('/active', [BatchController::class, 'active']);
    Route::post('/resume', [BatchController::class, 'resume']);
    Route::get('/status', [BatchController::class, 'status']);
    Route::get('/results', [BatchController::class, 'results']);
    Route::get('/failures', [BatchController::class, 'failures']);
    Route::post('/retry-failed', [BatchController::class, 'retryFailed']);
    Route::post('/process', [BatchController::class, 'process']);
    Route::post('/cancel', [BatchController::class, 'cancel']);
});

// Stats routes
Route::prefix('stats')->capability('upload_files')->group(function () {
    Route::get('/dashboard', [StatsController::class, 'dashboard']);
    Route::get('/audit', [StatsController::class, 'audit']);
});

// File Renamer routes (role-gated via RoleGate)
Route::prefix('renamer')->capability('upload_files')->middleware([\Softminal\ImageCraft\Http\Middleware\RoleGate::class])->group(function () {
    Route::post('/rename', [FileRenamerController::class, 'rename']);
    Route::post('/rename-bulk', [FileRenamerController::class, 'renameBulk']);
    Route::get('/preview', [FileRenamerController::class, 'preview']);
});

// Image compression routes (role-gated via RoleGate)
Route::prefix('compressor')->capability('upload_files')->middleware([\Softminal\ImageCraft\Http\Middleware\RoleGate::class])->group(function () {
    Route::get('/scan', [CompressorController::class, 'scan']);
    Route::post('/compress', [CompressorController::class, 'compress']);
    Route::post('/restore', [CompressorController::class, 'restore']);
    Route::get('/preview', [CompressorController::class, 'preview']);
    Route::get('/stats', [CompressorController::class, 'stats']);
    // Next-gen formats (WebP/AVIF sidecars) — additive endpoints
    Route::post('/convert', [CompressorController::class, 'convert']);
    Route::get('/convert-scan', [CompressorController::class, 'convertScan']);
    Route::post('/convert-clean', [CompressorController::class, 'convertClean']);
});

// Image Fallbacks routes (admin-side broken-image scan; no public endpoint)
Route::prefix('fallback')->capability('upload_files')->group(function () {
    Route::get('/scan-broken', [FallbackController::class, 'scanBroken']);
});

// Notice routes
Route::prefix('notice')->capability('edit_posts')->group(function () {
    Route::post('/dismiss', [AdminNotice::class, 'dismiss']);
});

// Feedback routes (deactivation survey)
Route::prefix('feedback')->capability('activate_plugins')->group(function () {
    Route::post('/deactivation', [FeedbackController::class, 'deactivation']);
});

// WooCommerce routes
Route::prefix('woocommerce')->capability('manage_woocommerce')->group(function () {
    Route::get('/products', [WooCommerceController::class, 'getProducts']);
    Route::get('/categories', [WooCommerceController::class, 'getCategories']);
    Route::get('/stats', [WooCommerceController::class, 'getStats']);
    Route::post('/generate', [WooCommerceController::class, 'generate']);
    Route::post('/generate-gallery', [WooCommerceController::class, 'generateGallery']);
    Route::get('/gallery', [WooCommerceController::class, 'getGallery']);
});

// FileBird routes
Route::prefix('filebird')->capability('upload_files')->group(function () {
    Route::get('/folders', [FileBirdController::class, 'getFolders']);
    Route::post('/folder/attachments', [FileBirdController::class, 'getAttachments']);
});
