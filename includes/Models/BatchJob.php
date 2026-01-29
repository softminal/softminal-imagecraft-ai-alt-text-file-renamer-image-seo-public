<?php

namespace WpAiAltText\Includes\Models;

if (!defined('ABSPATH')) {
    exit;
}

class BatchJob extends Model
{
    protected $table = 'alttext_batch_jobs';
    protected $fillable = [
        'user_id',
        'total_images',
        'processed_images',
        'failed_images',
        'status',
        'started_at',
        'completed_at'
    ];
    
    /**
     * Get active batch job for user
     */
    public static function getActive(?int $userId = null)
    {
        if ($userId === null) {
            $userId = get_current_user_id();
        }
        
        global $wpdb;
        $table = static::table();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is internal from static::table().
        $result = $wpdb->get_row(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is internal from static::table().

            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is internal from static::table().
                "SELECT * FROM {$table} WHERE user_id = %d AND status IN ('pending', 'processing') ORDER BY created_at DESC LIMIT 1",
                $userId
            ),
            ARRAY_A
        );
        
        if (!$result) {
            return null;
        }
        
        return static::hydrate($result);
    }
    
    /**
     * Get progress percentage
     * Progress is based on total attempted (processed + failed) vs total images
     */
    public function getProgress(): float
    {
        if ($this->total_images == 0) {
            return 0;
        }
        
        $totalAttempted = $this->processed_images + $this->failed_images;
        return round(($totalAttempted / $this->total_images) * 100, 2);
    }
    
    /**
     * Validate and correct job status based on processed/failed counts
     * Fixes jobs that were incorrectly marked as completed when all images failed
     */
    public function validateStatus(): void
    {
        $processed = (int) $this->processed_images;
        $failed = (int) $this->failed_images;
        $total = (int) $this->total_images;
        
        // Only validate if all images have been processed
        if ($processed + $failed < $total || $total === 0) {
            return;
        }
        
        // If all images failed, status should be 'failed' not 'completed'
        if ($failed === $total && $processed === 0) {
            if ($this->status === 'completed') {
                $this->status = 'failed';
                $this->save();
            }
        } else if ($this->status !== 'completed') {
            // If some images succeeded, ensure status is 'completed'
            $this->status = 'completed';
            if (!$this->completed_at) {
                $this->completed_at = current_time('mysql');
            }
            $this->save();
        }
    }
}
