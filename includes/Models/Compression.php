<?php

namespace Softminal\ImageCraft\Models;

if (!defined('ABSPATH')) {
    exit;
}

class Compression extends Model
{
    protected $table = 'compressions';
    protected $fillable = [
        'attachment_id',
        'user_id',
        'original_size',
        'compressed_size',
        'saved_bytes',
        'saved_percent',
        'method',
        'backup_path',
        'status',
        'error_message',
    ];

    /**
     * Invalidate model cache (public wrapper for bumpCacheVersion).
     */
    public static function invalidateCache(): void
    {
        static::bumpCacheVersion();
    }

    /**
     * Get the most recent compression record for an attachment, or null.
     */
    public static function getForAttachment(int $attachmentId)
    {
        return static::where('attachment_id', $attachmentId)
            ->orderBy('created_at', 'DESC')
            ->first();
    }
}
