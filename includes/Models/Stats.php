<?php

namespace WpAiAltText\Includes\Models;

if (!defined('ABSPATH')) {
    exit;
}

class Stats extends Model
{
    protected $table = 'alttext_stats';
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
     * Increment stats
     */
    public function increment(string $field, float $amount = 1): bool
    {
        if (!in_array($field, ['images_processed', 'images_accepted', 'images_rejected', 'total_cost_estimate'])) {
            return false;
        }
        
        $current = (float) ($this->attributes[$field] ?? 0);
        $this->attributes[$field] = $current + $amount;
        
        return $this->save();
    }
}
