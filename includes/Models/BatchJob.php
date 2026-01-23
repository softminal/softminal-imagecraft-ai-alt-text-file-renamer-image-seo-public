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
    public static function getActive(int $userId = null)
    {
        if ($userId === null) {
            $userId = get_current_user_id();
        }
        
        global $wpdb;
        $table = static::table();
        
        $result = $wpdb->get_row(
            $wpdb->prepare(
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
     */
    public function getProgress(): float
    {
        if ($this->total_images == 0) {
            return 0;
        }
        
        return round(($this->processed_images / $this->total_images) * 100, 2);
    }
}
