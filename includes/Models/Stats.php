<?php

namespace Softminal\ImageCraft\Models;

if (!defined('ABSPATH')) {
    exit;
}

class Stats extends Model
{
    protected $table = 'stats';
    protected $fillable = [
        'user_id',
        'date',
        'images_processed',
        'images_accepted',
        'images_rejected',
        'total_cost_estimate',
        'api_provider'
    ];

    /**
     * Get or create stats for a date
     */
    public static function getOrCreate(int $userId, string $date)
    {
        $stats = static::where('user_id', $userId)
            ->andWhere('date', '=', $date)
            ->first();

        if (!$stats) {
            $stats = static::create([
                'user_id' => $userId,
                'date' => $date,
                'images_processed' => 0,
                'images_accepted' => 0,
                'images_rejected' => 0,
                'total_cost_estimate' => 0
            ]);
        }

        return $stats;
    }

    /**
     * Atomically increment a stats counter using SQL to prevent race conditions.
     */
    public function increment(string $field, float $amount = 1): void
    {
        $allowed = ['images_processed', 'images_accepted', 'images_rejected', 'total_cost_estimate'];
        if (!in_array($field, $allowed, true)) {
            return;
        }

        if (!isset($this->attributes['id'])) {
            return;
        }

        global $wpdb;
        $safeTable = esc_sql(static::table());
        $safeField = esc_sql($field);

        // Build SQL with safe identifiers first, then prepare value placeholders.
        $sql = sprintf('UPDATE `%s` SET `%s` = `%s` + %%f WHERE id = %%d', $safeTable, $safeField, $safeField);
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Atomic increment; column from allow-list; table via esc_sql()
        $wpdb->query(
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Identifiers escaped via esc_sql(); values via $wpdb->prepare()
            $wpdb->prepare($sql, $amount, (int) $this->attributes['id'])
        );

        // Keep in-memory attribute in sync
        $this->attributes[$field] = (float) ($this->attributes[$field] ?? 0) + $amount;
    }
}
