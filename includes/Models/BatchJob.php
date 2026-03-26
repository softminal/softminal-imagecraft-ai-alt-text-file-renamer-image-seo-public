<?php

namespace Softminal\ImageCraft\Models;

if (!defined('ABSPATH')) {
    exit;
}

class BatchJob extends Model
{
    protected $table = 'batch_jobs';
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
     * Invalidate model cache (public wrapper for bumpCacheVersion).
     *
     * Useful after direct-DB updates that bypass Model::save().
     */
    public static function invalidateCache(): void
    {
        static::bumpCacheVersion();
    }

    /**
     * Get active batch job for user
     */
    public static function getActive(?int $userId = null)
    {
        if ($userId === null) {
            $userId = get_current_user_id();
        }

        global $wpdb;
        // Table name is constructed from trusted sources (wpdb prefix + plugin constant + hardcoded table name)
        $tableName = static::table();
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $tableName)) {
            return null;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Active job lookups need real-time data; table name validated above
        $result = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$tableName}` WHERE user_id = %d AND status IN ('pending', 'processing') ORDER BY created_at DESC LIMIT 1", $userId ), ARRAY_A );

        if (!$result) {
            return null;
        }

        return static::hydrate($result);
    }

    /**
     * Atomically increment a counter field using SQL to prevent race conditions.
     */
    public function increment(string $field, int $amount = 1): void
    {
        $allowed = ['processed_images', 'failed_images'];
        if (!in_array($field, $allowed, true) || !isset($this->attributes['id'])) {
            return;
        }

        global $wpdb;
        $safeTable = esc_sql(static::table());
        $safeField = esc_sql($field);

        // Build SQL with safe identifiers first, then prepare value placeholders.
        $sql = sprintf('UPDATE `%s` SET `%s` = `%s` + %%d WHERE id = %%d', $safeTable, $safeField, $safeField);
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Atomic increment; column from allow-list; table via esc_sql()
        $wpdb->query(
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Identifiers escaped via esc_sql(); values via $wpdb->prepare()
            $wpdb->prepare($sql, $amount, (int) $this->attributes['id'])
        );

        // Keep in-memory attribute in sync
        $this->attributes[$field] = (int) ($this->attributes[$field] ?? 0) + $amount;

        // Invalidate object cache so the next find() reads fresh data from DB
        static::bumpCacheVersion();
    }

    /**
     * Get progress percentage
     * Progress is based on total attempted (processed + failed) vs total images
     */
    public function getProgress(): float
    {
        if ((int) $this->total_images === 0) {
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

        // If all images failed, status should be 'failed'
        if ($failed === $total && $processed === 0) {
            if ($this->status !== 'failed') {
                $this->status = 'failed';
                if (!$this->completed_at) {
                    $this->completed_at = current_time('mysql');
                }
                $this->save();
            }
        } elseif ($this->status !== 'completed') {
            // If some images succeeded, ensure status is 'completed'
            $this->status = 'completed';
            if (!$this->completed_at) {
                $this->completed_at = current_time('mysql');
            }
            $this->save();
        }
    }
}
