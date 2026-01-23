<?php

namespace WpAiAltText\Includes\Http\Controllers;

use WpAiAltText\Includes\Models\BatchJob;
use WpAiAltText\Includes\Services\Settings;

if (!defined('ABSPATH')) {
    exit;
}

class BatchController extends Controller
{
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
        set_transient('alttext_batch_' . $job->id, $imageIds, DAY_IN_SECONDS);
        
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
        
        $job = BatchJob::find($jobId);
        if (!$job) {
            return $this->response([
                'success' => false,
                'message' => 'Job not found'
            ], 404);
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
                'message' => 'Job ID is required'
            ], 400);
        }
        
        $job = BatchJob::find($jobId);
        if (!$job) {
            return $this->response([
                'success' => false,
                'message' => 'Job not found'
            ], 404);
        }
        
        // Get image IDs
        $imageIds = get_transient('alttext_batch_' . $jobId);
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
        $batchSize = Settings::get('alttext_batch_size', 5);
        $tone = Settings::get('alttext_default_tone', 'professional');
        
        // Process chunk
        $processed = 0;
        $failed = 0;
        $startIndex = $job->processed_images;
        $chunk = array_slice($imageIds, $startIndex, $batchSize);
        
        foreach ($chunk as $attachmentId) {
            // Use GeneratorController logic
            $generator = new \WpAiAltText\Includes\Http\Controllers\GeneratorController();
            $requestData = new \WP_REST_Request('POST');
            $requestData->set_body(json_encode([
                'attachment_id' => $attachmentId,
                'tone' => $tone
            ]));
            
            $result = $generator->generate($requestData);
            $resultData = $result->get_data();
            
            if ($resultData['success']) {
                // Auto-accept if preview is disabled
                if (!Settings::get('alttext_preview_before_accept', true)) {
                    $acceptRequest = new \WP_REST_Request('POST');
                    $acceptRequest->set_body(json_encode([
                        'history_id' => $resultData['history_id'],
                        'alt_text' => $resultData['alt_text']
                    ]));
                    $generator->accept($acceptRequest);
                }
                $processed++;
            } else {
                $failed++;
            }
        }
        
        // Update job
        $job->processed_images += $processed;
        $job->failed_images += $failed;
        
        // Check if complete
        if ($job->processed_images + $job->failed_images >= $job->total_images) {
            $job->status = 'completed';
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
        
        $job = BatchJob::find($jobId);
        if (!$job) {
            return $this->response([
                'success' => false,
                'message' => 'Job not found'
            ], 404);
        }
        
        $job->status = 'failed';
        $job->completed_at = current_time('mysql');
        $job->save();
        
        return $this->response([
            'success' => true,
            'message' => 'Batch job cancelled'
        ]);
    }
}
