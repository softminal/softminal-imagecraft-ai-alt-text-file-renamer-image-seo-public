<?php

namespace Softminal\ImageCraft\Models;

use Softminal\ImageCraft\Services\Encryption;

if (!defined('ABSPATH')) {
    exit;
}

class ApiSetting extends Model
{
    protected $table = 'api_settings';
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
    public static function getActive(?int $userId = null)
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
            $decrypted = Encryption::decrypt($this->api_key_encrypted);
            if (empty($decrypted)) {
                return null;
            }
            return $decrypted;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Set API key (encrypted)
     */
    public function setApiKey(string $apiKey): void
    {
        if (empty($apiKey)) {
            throw new \Exception('API key cannot be empty');
        }

        try {
            $encrypted = Encryption::encrypt($apiKey);

            if (empty($encrypted)) {
                throw new \Exception('Encryption returned empty string');
            }

            // Extract IV from encrypted string for database storage
            // Format: base64(encrypted::hex_iv)
            // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Required to extract IV from encrypted API key
            $decoded = base64_decode($encrypted, true);
            if ($decoded !== false) {
                $parts = explode('::', $decoded, 2);
                if (isset($parts[1])) {
                    $ivHex = $parts[1];
                    // Store first 32 chars of IV hex (VARCHAR(32) limit)
                    $this->attributes['encryption_iv'] = substr($ivHex, 0, 32);
                }
            }

            // Explicitly set in attributes array to ensure it's saved
            $this->attributes['api_key_encrypted'] = $encrypted;
        } catch (\Exception $e) {
            throw $e;
        }
    }
}
