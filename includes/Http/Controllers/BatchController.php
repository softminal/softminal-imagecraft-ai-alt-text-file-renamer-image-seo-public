<?php

namespace SmartAltTextAi\Includes\Http\Controllers;

use SmartAltTextAi\Includes\Models\BatchJob;
use SmartAltTextAi\Includes\Models\History;
use SmartAltTextAi\Includes\Models\Stats;
use SmartAltTextAi\Includes\Services\Settings;

if (!defined('ABSPATH')) {
    exit;
}

class BatchController extends Controller
{
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
        $imageIds = $params['image_ids'] ?? [];
        
        if (empty($imageIds) || !is_array($imageIds)) {
            return $this->response([
                'success' => false,
                'message' => 'Image IDs are required'
            ], 400);
        }

        if (count($imageIds) > 500) {
            return $this->response([
                'success' => false,
                'message' => 'Maximum 500 images per batch job'
            ], 400);
        }

        $userId = get_current_user_id();
        
        // Check for active batch job
        $activeJob = BatchJob::getActive($userId);
        if ($activeJob) {
            return $this->response([
                'success' => false,
                'message' => 'A batch job is already in progress',
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
        set_transient('satai_batch_' . $job->id, $imageIds, DAY_IN_SECONDS);
        
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
                'message' => 'Job ID is required'
            ], 400);
        }

        $job = $this->findOwnedJob($jobId);
        if (!$job) {
            return $this->response([
                'success' => false,
                'message' => 'Job not found'
            ], 404);
        }
        
        // Validate and correct status if needed (fixes existing jobs with incorrect status)
        $job->validateStatus();
        
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
                'message' => 'Job ID is required'
            ], 400);
        }

        $job = $this->findOwnedJob($jobId);
        if (!$job) {
            return $this->response([
                'success' => false,
                'message' => 'Job not found'
            ], 404);
        }
        
        // Get image IDs
        $imageIds = get_transient('satai_batch_' . $jobId);
        if (!$imageIds) {
            return $this->response([
                'success' => false,
                'message' => 'Image IDs not found for this job'
            ], 400);
        }
        
        // Update job status
        if ($job->status === 'pending') {
            $job->status = 'processing';
            $job->started_at = current_time('mysql');
            $job->save();
        }
        
        // Get batch size
        $batchSize = Settings::get('satai_batch_size', 5);
        $tone = Settings::get('satai_default_tone', 'professional');
        
        // Process chunk
        $processed = 0;
        $failed = 0;
        $startIndex = $job->processed_images;
        $chunk = array_slice($imageIds, $startIndex, $batchSize);
        
        // Get user ID from batch job to ensure we use the correct user's API settings
        $userId = (int) $job->user_id;
        $generator = new GeneratorController();

        foreach ($chunk as $attachmentId) {
            try {
                $result = $generator->generateAltText((int) $attachmentId, $tone, $userId);

                if ($result['success'] ?? false) {
                    $processed++;
                } else {
                    $failed++;
                }
            } catch (\Exception $e) {
                $failed++;
            }
        }
        
        // Update job
        $job->processed_images += $processed;
        $job->failed_images += $failed;
        
        // Check if complete
        if ($job->processed_images + $job->failed_images >= $job->total_images) {
            // If all images failed, mark as failed; otherwise completed
            $processed = (int) $job->processed_images;
            $failed = (int) $job->failed_images;
            $total = (int) $job->total_images;
            
            if ($failed === $total && $processed === 0) {
                $job->status = 'failed';
            } else {
                $job->status = 'completed';
            }
            $job->completed_at = current_time('mysql');
        }
        
        $job->save();
        
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
                'message' => 'Job ID is required'
            ], 400);
        }

        $job = $this->findOwnedJob($jobId);
        if (!$job) {
            return $this->response([
                'success' => false,
                'message' => 'Job not found'
            ], 404);
        }
        
        // Mark as cancelled (using 'failed' status for cancelled jobs)
        // Note: We could add a 'cancelled' status in the future if needed
        $job->status = 'failed';
        $job->completed_at = current_time('mysql');
        $job->save();
        
        return $this->response([
            'success' => true,
            'message' => 'Batch job cancelled'
        ]);
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
                'message' => 'Job ID is required'
            ], 400);
        }

        $job = $this->findOwnedJob($jobId);
        if (!$job) {
            return $this->response([
                'success' => false,
                'message' => 'Job not found'
            ], 404);
        }

        if ($job->status !== 'completed') {
            return $this->response([
                'success' => false,
                'message' => 'Job is not completed yet'
            ], 400);
        }

        $imageIds = get_transient('satai_batch_' . $jobId);
        if (!$imageIds || !is_array($imageIds)) {
            return $this->response([
                'success' => true,
                'images' => []
            ]);
        }

        $images = [];
        foreach ($imageIds as $attachmentId) {
            $attachmentId = (int) $attachmentId;
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

            $images[] = [
                'id' => $attachmentId,
                'url' => wp_get_attachment_url($attachmentId),
                'thumbnail' => $thumbnail ? $thumbnail[0] : null,
                'filename' => $post ? basename(get_attached_file($attachmentId)) : '',
                'title' => $post ? $post->post_title : '',
                'alt_text' => $history->generated_alt ?: '',
                'history_id' => (int) $history->id
            ];
        }

        return $this->response([
            'success' => true,
            'images' => $images
        ]);
    }
}
