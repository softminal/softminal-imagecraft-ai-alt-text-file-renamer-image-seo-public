<?php

namespace Softminal\ImageCraft\Http\Controllers;

use Softminal\ImageCraft\Models\BatchJob;
use Softminal\ImageCraft\Models\History;
use Softminal\ImageCraft\Services\SeoIntegration;
use Softminal\ImageCraft\Services\Settings;

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

        // Store batch type: 'alt' (default) or 'title' (title-only generation)
        $batchType = sanitize_text_field($params['type'] ?? 'alt');
        if ($batchType === 'title') {
            set_transient('icais_batch_type_' . $job->id, 'title', DAY_IN_SECONDS);
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
                'completed_at' => $job->completed_at
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
        $batchSize = Settings::get('icais_batch_size', 5);
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

        $skipExisting = (bool) Settings::get('icais_skip_existing', false);

        // Check if this is a title-only batch
        $batchType = (string) get_transient('icais_batch_type_' . $jobId);
        $isTitleOnly = ($batchType === 'title');

        foreach ($chunk as $attachmentId) {
            // Check elapsed time before each image to avoid request timeout
            if ((time() - $startTime) >= $maxSeconds) {
                break;
            }

            if ($isTitleOnly) {
                // Title-only batch: generate title via AI and save to meta
                try {
                    $titleResult = $this->generateTitleForImage((int) $attachmentId, $userId);
                    if ($titleResult) {
                        $processed++;
                    } else {
                        $failed++;
                    }
                } catch (\Exception $e) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                    error_log('[ImageCraft] Title batch error for #' . $attachmentId . ': ' . $e->getMessage());
                    $failed++;
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
                    $result = $generator->generateAltText((int) $attachmentId, $tone, $userId, $context);

                    if ($result['success'] ?? false) {
                        $processed++;
                    } else {
                        $failed++;
                    }
                } catch (\Exception $e) {
                    $failed++;
                }
            }
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
                'processed_images' => $job->processed_images,
                'failed_images' => $job->failed_images,
                'progress' => $job->getProgress()
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

        // Clean up the stored image IDs and heartbeat
        delete_transient('icais_batch_' . $jobId);
        delete_transient('icais_batch_type_' . $jobId);
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
                    'started_at' => $job->started_at
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
                'started_at' => $job->started_at
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

        $aiProvider = \Softminal\ImageCraft\Services\AI\AIFactory::getProviderForUser($userId);
        if (!$aiProvider) {
            return false;
        }

        $imageUrl = wp_get_attachment_url($attachmentId);
        if (!$imageUrl) {
            return false;
        }

        $context = SeoIntegration::buildSeoContext($attachmentId);

        // Use title-only mode
        $aiProvider->setTitleOnlyMode(true);
        $result = $aiProvider->generateAltText($imageUrl, 'professional', $context);

        if (empty($result['success']) || empty($result['alt_text'])) {
            return false;
        }

        $generatedTitle = trim($result['alt_text']);

        // Cap at 80 chars
        if (mb_strlen($generatedTitle) > 80) {
            $generatedTitle = mb_substr($generatedTitle, 0, 80);
            $generatedTitle = preg_replace('/\s+\S*$/', '', $generatedTitle);
        }

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

        $images = [];
        foreach ($intIds as $attachmentId) {
            if (!wp_attachment_is_image($attachmentId)) {
                continue;
            }

            $history = History::where('attachment_id', $attachmentId)
                ->orderBy('created_at', 'DESC')
                ->first();

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
}
