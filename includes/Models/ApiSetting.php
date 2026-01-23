<?php

namespace WpAiAltText\Includes\Models;

if (!defined('ABSPATH')) {
    exit;
}

class ApiSetting extends Model
{
    protected $table = 'alttext_api_settings';
    protected $fillable = [
        'user_id',
        'api_provider',
        'api_key_encrypted',
        'encryption_iv',
        'is_active'
    ];
    
    /**
     * Get active API setting for current user
     */
    public static function getActive(int $userId = null)
    {
        if ($userId === null) {
            $userId = get_current_user_id();
        }
        
        return static::where('user_id', $userId)
            ->andWhere('is_active', 1)
            ->first();
    }
    
    /**
     * Get API key (decrypted)
     */
    public function getApiKey(): ?string
    {
        if (empty($this->api_key_encrypted)) {
            return null;
        }
        
        try {
            return \WpAiAltText\Includes\Services\Encryption::decrypt($this->api_key_encrypted);
        } catch (\Exception $e) {
            error_log('Failed to decrypt API key: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Set API key (encrypted)
     */
    public function setApiKey(string $apiKey): void
    {
        $encrypted = \WpAiAltText\Includes\Services\Encryption::encrypt($apiKey);
        $this->api_key_encrypted = $encrypted;
    }
}
