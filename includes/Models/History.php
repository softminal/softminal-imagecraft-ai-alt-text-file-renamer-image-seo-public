<?php

namespace Softminal\ImageCraft\Models;

if (!defined('ABSPATH')) {
    exit;
}

class History extends Model
{
    protected $table = 'history';
    protected $fillable = [
        'attachment_id',
        'original_alt',
        'generated_alt',
        'user_id',
        'api_provider',
        'tone',
        'status',
        'tokens_used',
        'cost_estimate'
    ];

    /**
     * Get history for an attachment
     */
    public static function getForAttachment(int $attachmentId)
    {
        return static::where('attachment_id', $attachmentId)
            ->orderBy('created_at', 'DESC')
            ->get();
    }

    /**
     * Get recent history for user
     */
    public static function getRecent(?int $userId = null, int $limit = 10)
    {
        if ($userId === null) {
            $userId = get_current_user_id();
        }

        return static::where('user_id', $userId)
            ->orderBy('created_at', 'DESC')
            ->paginate($limit, 1);
    }
}
