<?php

namespace Softminal\ImageCraft\Http\Controllers;

use Softminal\ImageCraft\Models\BatchJob;
use Softminal\ImageCraft\Models\History;
use Softminal\ImageCraft\Services\AI\AIFactory;
use Softminal\ImageCraft\Services\FileBird;
use Softminal\ImageCraft\Services\SeoIntegration;
use Softminal\ImageCraft\Services\Settings;
use Softminal\ImageCraft\Services\UsageLimiter;
use Softminal\ImageCraft\Support\AltTextHelper;

if (!defined('ABSPATH')) {
    exit;
}

class BatchController extends Controller
{
    /**
     * Heartbeat TTL in seconds.
     * A job is considered "actively running" if a heartbeat was set within this window.
     * Must be longer than the maximum poll interval (MAX_POLL_INTERVAL = 10s on the frontend).
     */
    private const HEARTBEAT_TTL = 30;

    /**
     * Set the heartbeat for a batch job, stamped with a client_id so we can
     * distinguish the owning browser tab from a second tab.
     */
    private function setHeartbeat(int $jobId, string $clientId): void
    {
        set_transient('icais_batch_hb_' . $jobId, $clientId, self::HEARTBEAT_TTL);
    }

    /**
     * Return the client_id that owns the heartbeat, or empty string if expired/missing.
     */
    private function getHeartbeatOwner(int $jobId): string
    {
        return (string) get_transient('icais_batch_hb_' . $jobId);
    }

    /**
     * Check whether a batch job has a fresh heartbeat from any client.
     */
    private function hasHeartbeat(int $jobId): bool
    {
        return $this->getHeartbeatOwner($jobId) !== '';
    }

    /**
     * Delete the heartbeat for a batch job.
     */
    private function clearHeartbeat(int $jobId): void
    {
        delete_transient('icais_batch_hb_' . $jobId);
    }

    /**
     * Per-field selection stored for a meta batch (subset of alt/title/caption),
     * or an empty array for non-meta batches / legacy "all fields" jobs where no
     * explicit subset was stored. Lets the UI show the exact fields being generated.
     *
     * @return string[]
     */
    private function getBatchFields(int $jobId): array
    {
        $stored = get_transient('icais_batch_fields_' . $jobId);
        if (is_array($stored) && !empty($stored)) {
            return array_values(array_intersect($stored, ['alt', 'title', 'caption']));
        }
        return [];
    }

    /**
     * Find batch job and verify ownership
     */
    private function findOwnedJob(int $jobId)
    {
        $job = BatchJob::find($jobId);
        if (!$job) {
            return null;
        }

        if ((int) $job->user_id !== get_current_user_id()) {
            return null;
        }

        return $job;
    }

    /**
     * Start batch processing
     */
    public function start($request)
    {
        $params = $request->get_json_params();
        $imageIds = array_map('absint', $params['image_ids'] ?? []);

        if (empty($imageIds) || !is_array($imageIds)) {
            return $this->response([
                'success' => false,
                'message' => __('Image IDs are required', 'imagecraft-ai-alt-text-file-renamer-image-seo')
            ], 400);
        }

        if (count($imageIds) > 500) {
            return $this->response([
                'success' => false,
                'message' => __('Maximum 500 images per batch job', 'imagecraft-ai-alt-text-file-renamer-image-seo')
            ], 400);
        }

        $userId = get_current_user_id();

        // Check for active batch job
        $activeJob = BatchJob::getActive($userId);
        if ($activeJob) {
            return $this->response([
                'success' => false,
                'message' => __('A batch job is already in progress', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
                'job_id' => $activeJob->id
            ], 400);
        }
        
        // Create batch job
        $job = BatchJob::create([
            'user_id' => $userId,
            'total_images' => count($imageIds),
            'processed_images' => 0,
            'failed_images' => 0,
            'status' => 'pending'
        ]);
        
        // Store image IDs in job meta (we'll use transients for this)
        set_transient('icais_batch_' . $job->id, $imageIds, DAY_IN_SECONDS);

        // Store batch type: 'alt' (default), 'title' (title-only), 'compress', or 'meta' (alt+title+caption)
        $batchType = sanitize_text_field($params['type'] ?? 'alt');
        if (in_array($batchType, ['title', 'compress', 'meta', 'convert'], true)) {
            set_transient('icais_batch_type_' . $job->id, $batchType, DAY_IN_SECONDS);
        }

        // Force re-compression of already-compressed images (e.g. "Re-compress Selected").
        if ($batchType === 'compress' && !empty($params['force'])) {
            set_transient('icais_batch_force_' . $job->id, '1', DAY_IN_SECONDS);
        }

        // For a meta batch, store the per-action field selection (alt/title/caption)
        // so the picker overrides the icais_meta_include_* settings for this run.
        if ($batchType === 'meta' && !empty($params['fields']) && is_array($params['fields'])) {
            $allowed = ['alt', 'title', 'caption'];
            $fields = array_values(array_intersect(array_map('sanitize_text_field', $params['fields']), $allowed));
            if (!empty($fields)) {
                set_transient('icais_batch_fields_' . $job->id, $fields, DAY_IN_SECONDS);
            }
        }

        return $this->response([
            'success' => true,
            'job_id' => $job->id,
            'total_images' => $job->total_images
        ]);
    }
    
    /**
     * Get batch job status
     */
    public function status($request)
    {
        $params = $request->get_params();
        $jobId = (int) ($params['job_id'] ?? 0);

        if (!$jobId) {
            return $this->response([
                'success' => false,
                'message' => __('Job ID is required', 'imagecraft-ai-alt-text-file-renamer-image-seo')
            ], 400);
        }

        $job = $this->findOwnedJob($jobId);
        if (!$job) {
            return $this->response([
                'success' => false,
                'message' => __('Job not found', 'imagecraft-ai-alt-text-file-renamer-image-seo')
            ], 404);
        }

        // Validate and correct status if needed (fixes existing jobs with incorrect status)
        $job->validateStatus();

        // Refresh heartbeat only if the caller is the owning processor tab.
        // Watcher tabs (no client_id or different client_id) just read status without touching the heartbeat.
        $clientId = sanitize_text_field($params['client_id'] ?? '');
        if ($clientId !== '' && in_array($job->status, ['pending', 'processing'], true)) {
            $currentOwner = $this->getHeartbeatOwner($jobId);
            if ($currentOwner === '' || $currentOwner === $clientId) {
                $this->setHeartbeat($jobId, $clientId);
            }
        }

        $batchType = (string) get_transient('icais_batch_type_' . $jobId);

        return $this->response([
            'success' => true,
            'job' => [
                'id' => $job->id,
                'status' => $job->status,
                'total_images' => $job->total_images,
                'processed_images' => $job->processed_images,
                'failed_images' => $job->failed_images,
                'progress' => $job->getProgress(),
                'started_at' => $job->started_at,
                'completed_at' => $job->completed_at,
                'batch_type' => in_array($batchType, ['title', 'compress', 'meta', 'convert'], true) ? $batchType : 'alt',
                'batch_fields' => $this->getBatchFields((int) $job->id)
            ]
        ]);
    }

    /**
     * Process batch chunk
     */
    public function process($request)
    {
        $params = $request->get_json_params();
        $jobId = (int) ($params['job_id'] ?? 0);

        if (!$jobId) {
            return $this->response([
                'success' => false,
                'message' => __('Job ID is required', 'imagecraft-ai-alt-text-file-renamer-image-seo')
            ], 400);
        }

        $job = $this->findOwnedJob($jobId);
        if (!$job) {
            return $this->response([
                'success' => false,
                'message' => __('Job not found', 'imagecraft-ai-alt-text-file-renamer-image-seo')
            ], 404);
        }

        // Only process jobs that are pending or processing
        if (!in_array($job->status, ['pending', 'processing'], true)) {
            return $this->response([
                'success' => false,
                'message' => __('This job is no longer active', 'imagecraft-ai-alt-text-file-renamer-image-seo')
            ], 400);
        }

        // Enforce single-processor ownership via heartbeat.
        // Each browser tab sends a unique client_id. The first tab to call process()
        // claims the lock. Subsequent tabs with a different client_id are rejected
        // as long as the owning tab's heartbeat is fresh.
        $clientId = sanitize_text_field($params['client_id'] ?? '');
        if ($clientId === '') {
            return $this->response([
                'success' => false,
                'message' => __('Client ID is required', 'imagecraft-ai-alt-text-file-renamer-image-seo')
            ], 400);
        }

        $currentOwner = $this->getHeartbeatOwner($jobId);
        if ($currentOwner !== '' && $currentOwner !== $clientId) {
            // Another tab owns this job and its heartbeat is still fresh
            return $this->response([
                'success' => false,
                'message' => __('This job is being processed by another window', 'imagecraft-ai-alt-text-file-renamer-image-seo')
            ], 409);
        }

        // Claim or refresh the heartbeat for this client
        $this->setHeartbeat($jobId, $clientId);

        // Get image IDs
        $imageIds = get_transient('icais_batch_' . $jobId);
        if (!$imageIds) {
            return $this->response([
                'success' => false,
                'message' => __('Image IDs not found for this job', 'imagecraft-ai-alt-text-file-renamer-image-seo')
            ], 400);
        }

        // Update job status (targeted update to avoid touching counter fields)
        if ($job->status === 'pending') {
            $job->status = 'processing';
            $job->started_at = current_time('mysql');
            global $wpdb;
            $safeTable = esc_sql(BatchJob::table());
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Targeted status update
            $wpdb->update($safeTable, ['status' => 'processing', 'started_at' => $job->started_at], ['id' => (int) $job->id], ['%s', '%s'], ['%d']);
            BatchJob::invalidateCache();
        }

        // Get batch size
        $batchSize = Settings::get('icais_batch_size', 3);
        $tone = Settings::get('icais_default_tone', 'professional');
        
        // Re-fetch to get accurate counter values before calculating chunk offset
        $job = BatchJob::find($job->id);

        // Process chunk
        $processed = 0;
        $failed = 0;
        $startIndex = (int) $job->processed_images + (int) $job->failed_images;
        $chunk = array_slice($imageIds, $startIndex, $batchSize);
        
        // Get user ID from batch job to ensure we use the correct user's API settings
        $userId = (int) $job->user_id;
        $generator = new GeneratorController();

        // Wall-clock time guard: bail before the PHP request times out
        $startTime = time();
        $maxSeconds = max(30, (int) Settings::get('icais_request_timeout', 60) * 2);

        $skipExisting = (bool) Settings::get('icais_skip_existing', '0');

        // Check if this is a title-only or compression batch
        $batchType = (string) get_transient('icais_batch_type_' . $jobId);
        $isTitleOnly = ($batchType === 'title');
        $isCompress  = ($batchType === 'compress');
        $isMeta      = ($batchType === 'meta');
        $isConvert   = ($batchType === 'convert');
        $forceCompress = $isCompress && (string) get_transient('icais_batch_force_' . $jobId) === '1';

        // Per-action field selection for a meta batch (null = use settings defaults).
        $metaFields = null;
        if ($isMeta) {
            $storedFields = get_transient('icais_batch_fields_' . $jobId);
            if (is_array($storedFields) && !empty($storedFields)) {
                $metaFields = $storedFields;
            }
        }

        $compressor = $isCompress ? new \Softminal\ImageCraft\Services\ImageCompressor() : null;
        $converter  = $isConvert ? new \Softminal\ImageCraft\Services\NextGenConverter() : null;
        $convertFormats = $isConvert ? \Softminal\ImageCraft\Services\NextGenConverter::enabledFormats() : [];

        $failures = [];

        foreach ($chunk as $attachmentId) {
            // Check elapsed time before each image to avoid request timeout
            if ((time() - $startTime) >= $maxSeconds) {
                break;
            }

            if ($isCompress) {
                // Compression batch: re-encode the image locally (no AI calls)
                try {
                    if (!UsageLimiter::canCompress($userId, (int) $attachmentId)) {
                        $failed++;
                        $failures[] = [
                            'attachment_id' => (int) $attachmentId,
                            'reason' => __('Daily compression limit reached. Upgrade to Pro for unlimited compression.', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
                        ];
                        continue;
                    }

                    $compressResult = $compressor->compress((int) $attachmentId, ['force' => $forceCompress]);
                    if ($compressResult['success'] ?? false) {
                        if (empty($compressResult['skipped'])) {
                            UsageLimiter::recordCompressUsage($userId, (int) $attachmentId);
                        }
                        $processed++;
                    } else {
                        $failed++;
                        $failures[] = [
                            'attachment_id' => (int) $attachmentId,
                            'reason' => $compressResult['error'] ?? __('Compression failed', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
                        ];
                    }
                } catch (\Throwable $e) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                    error_log('[ImageCraft] Compress batch error for #' . $attachmentId . ': ' . $e->getMessage());
                    $failed++;
                    $failures[] = [
                        'attachment_id' => (int) $attachmentId,
                        'reason' => __('Compression failed. Check error log for details.', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
                    ];
                }
            } elseif ($isConvert) {
                // Next-gen format batch: generate WebP/AVIF sidecars (no AI calls).
                // Shares the daily compress counter, like single conversions.
                try {
                    if (!UsageLimiter::canCompress($userId, (int) $attachmentId)) {
                        $failed++;
                        $failures[] = [
                            'attachment_id' => (int) $attachmentId,
                            'reason' => __('Daily compression limit reached. Upgrade to Pro for unlimited conversions.', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
                        ];
                        continue;
                    }

                    $convertResult = $converter->convert((int) $attachmentId, $convertFormats);
                    if ($convertResult['success'] ?? false) {
                        if (!empty($convertResult['generated'])) {
                            UsageLimiter::recordCompressUsage($userId, (int) $attachmentId);
                        }
                        $processed++;
                    } elseif (!empty($convertResult['skipped'])) {
                        // Nothing to do for this image (unsupported type / already
                        // converted / nothing smaller) — count as processed, not failed.
                        $processed++;
                    } else {
                        $failed++;
                        $failures[] = [
                            'attachment_id' => (int) $attachmentId,
                            'reason' => $convertResult['error'] ?? __('Conversion failed', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
                        ];
                    }
                } catch (\Throwable $e) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                    error_log('[ImageCraft] Convert batch error for #' . $attachmentId . ': ' . $e->getMessage());
                    $failed++;
                    $failures[] = [
                        'attachment_id' => (int) $attachmentId,
                        'reason' => __('Conversion failed. Check error log for details.', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
                    ];
                }
            } elseif ($isTitleOnly) {
                // Title-only batch: generate title via AI and save to meta
                try {
                    $titleResult = $this->generateTitleForImage((int) $attachmentId, $userId);
                    if ($titleResult) {
                        $processed++;
                    } else {
                        $failed++;
                        $failures[] = [
                            'attachment_id' => (int) $attachmentId,
                            'reason' => __('Title generation failed', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
                        ];
                    }
                } catch (\Exception $e) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                    error_log('[ImageCraft] Title batch error for #' . $attachmentId . ': ' . $e->getMessage());
                    $failed++;
                    $failures[] = [
                        'attachment_id' => (int) $attachmentId,
                        'reason' => __('Title generation failed. Check error log for details.', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
                    ];
                }
            } elseif ($isMeta) {
                // Combined meta batch: generate alt + title + caption in one AI call
                try {
                    $context = SeoIntegration::buildSeoContext((int) $attachmentId);
                    if (Settings::get('icais_integration_filebird', '1') === '1') {
                        $folderContext = FileBird::buildFolderContext((int) $attachmentId);
                        if ($folderContext !== null) {
                            $context = $context !== null ? $context . ' | ' . $folderContext : $folderContext;
                        }
                    }
                    $metaResult = $generator->generateMetaForImage((int) $attachmentId, $userId, $context, true, $metaFields);

                    if ($metaResult['success'] ?? false) {
                        $processed++;
                    } else {
                        $failed++;
                        $failures[] = [
                            'attachment_id' => (int) $attachmentId,
                            'reason' => $metaResult['message'] ?? __('Metadata generation failed', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
                        ];
                    }
                } catch (\Throwable $e) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                    error_log('[ImageCraft] Meta batch error for #' . $attachmentId . ': ' . $e->getMessage());
                    $failed++;
                    $failures[] = [
                        'attachment_id' => (int) $attachmentId,
                        'reason' => __('Metadata generation failed. Check error log for details.', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
                    ];
                }
            } else {
                // Standard alt text batch (may include title if setting enabled)
                // Skip images that already have alt text (saves API credits)
                if ($skipExisting) {
                    $existingAlt = get_post_meta((int) $attachmentId, '_wp_attachment_image_alt', true);
                    if (!empty($existingAlt)) {
                        $processed++;
                        continue;
                    }
                }

                try {
                    $context = SeoIntegration::buildSeoContext((int) $attachmentId);
                    if (Settings::get('icais_integration_filebird', '1') === '1') {
                        $folderContext = FileBird::buildFolderContext((int) $attachmentId);
                        if ($folderContext !== null) {
                            $context = $context !== null ? $context . ' | ' . $folderContext : $folderContext;
                        }
                    }
                    $result = $generator->generateAltText((int) $attachmentId, $tone, $userId, $context, true);

                    if ($result['success'] ?? false) {
                        $processed++;
                    } else {
                        $failed++;
                        $failures[] = [
                            'attachment_id' => (int) $attachmentId,
                            'reason' => $result['message'] ?? __('Alt text generation failed', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
                        ];
                    }
                } catch (\Exception $e) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                    error_log('[ImageCraft] Batch error for #' . $attachmentId . ': ' . $e->getMessage());
                    $failed++;
                    $failures[] = [
                        'attachment_id' => (int) $attachmentId,
                        'reason' => __('Generation failed. Check error log for details.', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
                    ];
                }
            }
        }

        // Store failure details in transient for later retrieval
        if (!empty($failures)) {
            $existing = get_transient('icais_batch_failures_' . $jobId);
            $all = is_array($existing) ? array_merge($existing, $failures) : $failures;
            set_transient('icais_batch_failures_' . $jobId, $all, DAY_IN_SECONDS);
        }
        
        // Atomically update counters to prevent race conditions
        if ($processed > 0) {
            $job->increment('processed_images', $processed);
        }
        if ($failed > 0) {
            $job->increment('failed_images', $failed);
        }

        // Re-fetch job from DB to get true counter values after concurrent increments
        $job = BatchJob::find($job->id);

        // Check if complete and update status
        if ($job->processed_images + $job->failed_images >= $job->total_images) {
            if ((int) $job->failed_images === (int) $job->total_images && (int) $job->processed_images === 0) {
                $job->status = 'failed';
            } else {
                $job->status = 'completed';
            }
            $job->completed_at = current_time('mysql');
        }

        // Only update status fields, NOT the counter fields (which were atomically incremented)
        global $wpdb;
        $safeTable = esc_sql(BatchJob::table());
        $updateData = ['status' => $job->status];
        $updateFormats = ['%s'];
        if ($job->started_at) {
            $updateData['started_at'] = $job->started_at;
            $updateFormats[] = '%s';
        }
        if ($job->completed_at) {
            $updateData['completed_at'] = $job->completed_at;
            $updateFormats[] = '%s';
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Targeted update of status fields only; counter fields updated atomically via increment()
        $wpdb->update($safeTable, $updateData, ['id' => (int) $job->id], $updateFormats, ['%d']);

        // Invalidate cache after direct DB update so next find() reads fresh data
        BatchJob::invalidateCache();

        return $this->response([
            'success' => true,
            'processed' => $processed,
            'failed' => $failed,
            'job' => [
                'id' => $job->id,
                'status' => $job->status,
                'total_images' => $job->total_images,
                'processed_images' => $job->processed_images,
                'failed_images' => $job->failed_images,
                'progress' => $job->getProgress(),
                'started_at' => $job->started_at,
                'completed_at' => $job->completed_at,
                'batch_type' => in_array($batchType, ['title', 'compress', 'meta', 'convert'], true) ? $batchType : 'alt',
                'batch_fields' => $this->getBatchFields((int) $job->id)
            ]
        ]);
    }

    /**
     * Cancel batch job
     */
    public function cancel($request)
    {
        $params = $request->get_json_params();
        $jobId = (int) ($params['job_id'] ?? 0);

        if (!$jobId) {
            return $this->response([
                'success' => false,
                'message' => __('Job ID is required', 'imagecraft-ai-alt-text-file-renamer-image-seo')
            ], 400);
        }

        $job = $this->findOwnedJob($jobId);
        if (!$job) {
            return $this->response([
                'success' => false,
                'message' => __('Job not found', 'imagecraft-ai-alt-text-file-renamer-image-seo')
            ], 404);
        }

        // Prevent discarding a job that is actively being processed in another window.
        // The "Cancel" button in the active-job UI doesn't send discard=true,
        // so only the "Discard" button from the resume banner is blocked.
        $isDiscardAction = !empty($params['discard']);
        if ($isDiscardAction && $this->hasHeartbeat($jobId)) {
            return $this->response([
                'success' => false,
                'message' => __('This job is currently being processed in another window.', 'imagecraft-ai-alt-text-file-renamer-image-seo')
            ], 409);
        }

        // Mark as cancelled (using 'failed' status for cancelled jobs)
        // Note: We could add a 'cancelled' status in the future if needed
        $job->status = 'failed';
        $job->completed_at = current_time('mysql');
        $job->save();

        // Clean up the stored image IDs, failure details, and heartbeat
        delete_transient('icais_batch_' . $jobId);
        delete_transient('icais_batch_type_' . $jobId);
        delete_transient('icais_batch_force_' . $jobId);
        delete_transient('icais_batch_failures_' . $jobId);
        $this->clearHeartbeat($jobId);

        return $this->response([
            'success' => true,
            'message' => __('Batch job cancelled', 'imagecraft-ai-alt-text-file-renamer-image-seo')
        ]);
    }

    /**
     * Check for an interrupted (resumable) batch job for the current user
     */
    public function active($request)
    {
        $userId = get_current_user_id();
        $job = BatchJob::getActive($userId);

        if (!$job) {
            return $this->response([
                'success' => true,
                'has_active' => false
            ]);
        }

        // Validate and correct status if needed (e.g., all images done but status still 'processing')
        $job->validateStatus();

        // After validation, job may no longer be active (status corrected to completed/failed)
        if (!in_array($job->status, ['pending', 'processing'], true)) {
            return $this->response([
                'success' => true,
                'has_active' => false
            ]);
        }

        $batchType = (string) get_transient('icais_batch_type_' . $job->id);
        $batchTypeLabel = in_array($batchType, ['title', 'compress', 'meta', 'convert'], true) ? $batchType : 'alt';

        // Check if another browser tab is actively processing this job
        if ($this->hasHeartbeat((int) $job->id)) {
            return $this->response([
                'success' => true,
                'has_active' => true,
                'is_running' => true,
                'job' => [
                    'id' => $job->id,
                    'status' => $job->status,
                    'total_images' => $job->total_images,
                    'processed_images' => $job->processed_images,
                    'failed_images' => $job->failed_images,
                    'progress' => $job->getProgress(),
                    'remaining' => 0,
                    'started_at' => $job->started_at,
                    'batch_type' => $batchTypeLabel,
                    'batch_fields' => $this->getBatchFields((int) $job->id)
                ]
            ]);
        }

        // No heartbeat — job is interrupted. Check if it can be resumed.
        $imageIds = get_transient('icais_batch_' . $job->id);
        $hasImageIds = !empty($imageIds) && is_array($imageIds);

        $totalAttempted = (int) $job->processed_images + (int) $job->failed_images;
        $remaining = max(0, (int) $job->total_images - $totalAttempted);

        // If transient expired, the job can't be resumed — auto-mark as failed so it stops blocking new jobs
        if (!$hasImageIds) {
            $job->status = 'failed';
            $job->completed_at = current_time('mysql');
            $job->save();

            return $this->response([
                'success' => true,
                'has_active' => false
            ]);
        }

        return $this->response([
            'success' => true,
            'has_active' => true,
            'is_running' => false,
            'job' => [
                'id' => $job->id,
                'status' => $job->status,
                'total_images' => $job->total_images,
                'processed_images' => $job->processed_images,
                'failed_images' => $job->failed_images,
                'progress' => $job->getProgress(),
                'remaining' => $remaining,
                'started_at' => $job->started_at,
                'batch_type' => $batchTypeLabel,
                'batch_fields' => $this->getBatchFields((int) $job->id)
            ]
        ]);
    }

    /**
     * Resume an interrupted batch job
     */
    public function resume($request)
    {
        $params = $request->get_json_params();
        $jobId = (int) ($params['job_id'] ?? 0);

        if (!$jobId) {
            return $this->response([
                'success' => false,
                'message' => __('Job ID is required', 'imagecraft-ai-alt-text-file-renamer-image-seo')
            ], 400);
        }

        $job = $this->findOwnedJob($jobId);
        if (!$job) {
            return $this->response([
                'success' => false,
                'message' => __('Job not found', 'imagecraft-ai-alt-text-file-renamer-image-seo')
            ], 404);
        }

        // Only allow resuming pending/processing jobs
        if (!in_array($job->status, ['pending', 'processing'], true)) {
            return $this->response([
                'success' => false,
                'message' => __('This job cannot be resumed', 'imagecraft-ai-alt-text-file-renamer-image-seo')
            ], 400);
        }

        // Prevent resuming a job that another browser tab is actively processing
        if ($this->hasHeartbeat($jobId)) {
            return $this->response([
                'success' => false,
                'message' => __('This job is currently being processed in another window.', 'imagecraft-ai-alt-text-file-renamer-image-seo')
            ], 409);
        }

        // Verify transient still has image IDs
        $imageIds = get_transient('icais_batch_' . $jobId);
        if (!$imageIds || !is_array($imageIds)) {
            // Image IDs expired — mark job as failed
            $job->status = 'failed';
            $job->completed_at = current_time('mysql');
            $job->save();

            return $this->response([
                'success' => false,
                'message' => __('Image data for this job has expired. Please start a new batch.', 'imagecraft-ai-alt-text-file-renamer-image-seo')
            ], 400);
        }

        // Refresh the transient TTL so it doesn't expire mid-resume
        set_transient('icais_batch_' . $jobId, $imageIds, DAY_IN_SECONDS);

        // Ensure status is processing
        if ($job->status === 'pending') {
            $job->status = 'processing';
            $job->started_at = $job->started_at ?: current_time('mysql');
            global $wpdb;
            $safeTable = esc_sql(BatchJob::table());
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Targeted status update for resume
            $wpdb->update($safeTable, ['status' => 'processing', 'started_at' => $job->started_at], ['id' => (int) $job->id], ['%s', '%s'], ['%d']);
            BatchJob::invalidateCache();
        }

        return $this->response([
            'success' => true,
            'job_id' => $job->id,
            'total_images' => $job->total_images,
            'processed_images' => $job->processed_images,
            'failed_images' => $job->failed_images,
            'progress' => $job->getProgress()
        ]);
    }

    /**
     * Generate a title for a single image using AI (title-only batch mode).
     */
    private function generateTitleForImage(int $attachmentId, int $userId): bool
    {
        if (!wp_attachment_is_image($attachmentId)) {
            return false;
        }

        $aiProvider = AIFactory::getProviderForUser($userId);
        if (!$aiProvider) {
            return false;
        }

        $imageUrl = wp_get_attachment_url($attachmentId);
        if (!$imageUrl) {
            return false;
        }

        // Daily usage limit check
        if (!UsageLimiter::canGenerate($userId, $attachmentId)) {
            return false;
        }

        $context = null;
        if (Settings::get('icais_title_context_mode', 'image') === 'context') {
            $context = SeoIntegration::buildSeoContext($attachmentId);
        }

        // Use title-only mode
        $aiProvider->setTitleOnlyMode(true);
        $result = $aiProvider->generateAltText($imageUrl, 'professional', $context);

        if (empty($result['success']) || empty($result['alt_text'])) {
            return false;
        }

        // Track daily usage
        UsageLimiter::recordUsage($userId, $attachmentId);

        $generatedTitle = trim($result['alt_text']);

        $generatedTitle = AltTextHelper::truncateTitle($generatedTitle, (int) Settings::get('icais_title_max_length', 30));

        // Save to meta and update post_title
        update_post_meta($attachmentId, '_icais_generated_title', sanitize_text_field($generatedTitle));

        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->update($wpdb->posts, ['post_title' => $generatedTitle], ['ID' => $attachmentId], ['%s'], ['%d']);
        clean_post_cache($attachmentId);

        return true;
    }

    /**
     * Get batch job results (images with generated alt text) for review modal
     */
    public function results($request)
    {
        $params = $request->get_params();
        $jobId = (int) ($params['job_id'] ?? 0);

        if (!$jobId) {
            return $this->response([
                'success' => false,
                'message' => __('Job ID is required', 'imagecraft-ai-alt-text-file-renamer-image-seo')
            ], 400);
        }

        $job = $this->findOwnedJob($jobId);
        if (!$job) {
            return $this->response([
                'success' => false,
                'message' => __('Job not found', 'imagecraft-ai-alt-text-file-renamer-image-seo')
            ], 404);
        }

        if ($job->status !== 'completed') {
            return $this->response([
                'success' => false,
                'message' => __('Job is not completed yet', 'imagecraft-ai-alt-text-file-renamer-image-seo')
            ], 400);
        }

        $imageIds = get_transient('icais_batch_' . $jobId);
        if (!$imageIds || !is_array($imageIds)) {
            return $this->response([
                'success' => true,
                'images' => []
            ]);
        }

        // Prime WP post caches in a single query to avoid N+1
        $intIds = array_map('intval', $imageIds);
        // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Priming post caches for batch result display
        _prime_post_caches($intIds, true, true);

        // Load all histories in one bulk query then build a per-attachment map,
        // replacing what was previously one query per image (N+1 pattern).
        $allHistories = History::where('attachment_id', 'IN', $intIds)
            ->orderBy('created_at', 'DESC')
            ->get();
        $historyMap = [];
        foreach ($allHistories as $h) {
            $aid = (int) $h->attachment_id;
            if (!isset($historyMap[$aid])) {
                $historyMap[$aid] = $h; // first = latest (DESC order)
            }
        }

        $images = [];
        foreach ($intIds as $attachmentId) {
            if (!wp_attachment_is_image($attachmentId)) {
                continue;
            }

            $history = $historyMap[$attachmentId] ?? null;

            if (!$history) {
                continue;
            }

            $thumbnail = wp_get_attachment_image_src($attachmentId, 'thumbnail');
            $post = get_post($attachmentId);

            $generatedTitle = (string) get_post_meta($attachmentId, '_icais_generated_title', true);

            $images[] = [
                'id' => $attachmentId,
                'url' => wp_get_attachment_url($attachmentId),
                'thumbnail' => $thumbnail ? $thumbnail[0] : null,
                'filename' => $post ? basename(get_attached_file($attachmentId)) : '',
                'title' => $post ? $post->post_title : '',
                'alt_text' => $history->generated_alt ?: '',
                'generated_title' => $generatedTitle,
                'history_id' => (int) $history->id
            ];
        }

        return $this->response([
            'success' => true,
            'images' => $images
        ]);
    }

    /**
     * Get paginated list of failed images for a batch job
     */
    public function failures($request)
    {
        $params = $request->get_params();
        $jobId = (int) ($params['job_id'] ?? 0);

        if (!$jobId) {
            return $this->response([
                'success' => false,
                'message' => __('Job ID is required', 'imagecraft-ai-alt-text-file-renamer-image-seo')
            ], 400);
        }

        $job = $this->findOwnedJob($jobId);
        if (!$job) {
            return $this->response([
                'success' => false,
                'message' => __('Job not found', 'imagecraft-ai-alt-text-file-renamer-image-seo')
            ], 404);
        }

        $page = max(1, (int) ($params['page'] ?? 1));
        $perPage = max(1, min(100, (int) ($params['per_page'] ?? 20)));

        $allFailures = get_transient('icais_batch_failures_' . $jobId);
        if (!is_array($allFailures) || empty($allFailures)) {
            return $this->response([
                'success' => true,
                'images' => [],
                'pagination' => [
                    'current_page' => 1,
                    'total_pages' => 1,
                    'total' => 0,
                    'per_page' => $perPage,
                ],
            ]);
        }

        $total = count($allFailures);
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        $pageItems = array_slice($allFailures, $offset, $perPage);

        // Prime post caches for the page
        $attachmentIds = array_map(function ($item) {
            return (int) $item['attachment_id'];
        }, $pageItems);
        if (!empty($attachmentIds)) {
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Priming post caches for failure display
            _prime_post_caches($attachmentIds, true, true);
        }

        $images = [];
        foreach ($pageItems as $item) {
            $attachmentId = (int) $item['attachment_id'];
            $thumbnail = wp_get_attachment_image_src($attachmentId, 'thumbnail');
            $post = get_post($attachmentId);

            $images[] = [
                'id' => $attachmentId,
                'thumbnail' => $thumbnail ? $thumbnail[0] : null,
                'filename' => $post ? basename(get_attached_file($attachmentId)) : __('(deleted)', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
                'reason' => $item['reason'] ?? __('Unknown error', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
            ];
        }

        return $this->response([
            'success' => true,
            'images' => $images,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => $totalPages,
                'total' => $total,
                'per_page' => $perPage,
            ],
        ]);
    }

    /**
     * Retry all failed images from a completed batch job by starting a new batch
     */
    public function retryFailed($request)
    {
        $params = $request->get_json_params();
        $jobId = (int) ($params['job_id'] ?? 0);

        if (!$jobId) {
            return $this->response([
                'success' => false,
                'message' => __('Job ID is required', 'imagecraft-ai-alt-text-file-renamer-image-seo')
            ], 400);
        }

        $job = $this->findOwnedJob($jobId);
        if (!$job) {
            return $this->response([
                'success' => false,
                'message' => __('Job not found', 'imagecraft-ai-alt-text-file-renamer-image-seo')
            ], 404);
        }

        $allFailures = get_transient('icais_batch_failures_' . $jobId);
        if (!is_array($allFailures) || empty($allFailures)) {
            return $this->response([
                'success' => false,
                'message' => __('No failure details available to retry', 'imagecraft-ai-alt-text-file-renamer-image-seo')
            ], 400);
        }

        $imageIds = array_unique(array_map(function ($item) {
            return (int) $item['attachment_id'];
        }, $allFailures));
        $imageIds = array_values(array_filter($imageIds));

        if (empty($imageIds)) {
            return $this->response([
                'success' => false,
                'message' => __('No valid images to retry', 'imagecraft-ai-alt-text-file-renamer-image-seo')
            ], 400);
        }

        $userId = get_current_user_id();

        // Check for active batch job
        $activeJob = BatchJob::getActive($userId);
        if ($activeJob) {
            return $this->response([
                'success' => false,
                'message' => __('A batch job is already in progress', 'imagecraft-ai-alt-text-file-renamer-image-seo'),
                'job_id' => $activeJob->id
            ], 400);
        }

        // Preserve batch type from original job
        $batchType = (string) get_transient('icais_batch_type_' . $jobId);

        // Create new batch job
        $newJob = BatchJob::create([
            'user_id' => $userId,
            'total_images' => count($imageIds),
            'processed_images' => 0,
            'failed_images' => 0,
            'status' => 'pending'
        ]);

        set_transient('icais_batch_' . $newJob->id, $imageIds, DAY_IN_SECONDS);

        if (in_array($batchType, ['title', 'compress', 'meta', 'convert'], true)) {
            set_transient('icais_batch_type_' . $newJob->id, $batchType, DAY_IN_SECONDS);
        }

        // Preserve the meta field selection for retried meta batches.
        if ($batchType === 'meta') {
            $storedFields = get_transient('icais_batch_fields_' . $jobId);
            if (is_array($storedFields) && !empty($storedFields)) {
                set_transient('icais_batch_fields_' . $newJob->id, $storedFields, DAY_IN_SECONDS);
            }
        }

        return $this->response([
            'success' => true,
            'job_id' => $newJob->id,
            'total_images' => $newJob->total_images
        ]);
    }
}
