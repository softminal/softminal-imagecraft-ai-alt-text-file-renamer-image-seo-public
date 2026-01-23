<?php

namespace WpAiAltText\Includes\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Encryption service for API keys
 */
class Encryption
{
    private const CIPHER = 'AES-256-CBC';
    
    /**
     * Get encryption key from WordPress salts
     */
    private static function getEncryptionKey(): string
    {
        if (!defined('AUTH_KEY') || !defined('SECURE_AUTH_KEY')) {
            throw new \Exception('WordPress security keys not defined');
        }
        
        return hash('sha256', AUTH_KEY . SECURE_AUTH_KEY);
    }
    
    /**
     * Encrypt data
     */
    public static function encrypt(string $data): string
    {
        $key = self::getEncryptionKey();
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(self::CIPHER));
        
        if ($iv === false) {
            throw new \Exception('Failed to generate IV');
        }
        
        $encrypted = openssl_encrypt($data, self::CIPHER, $key, 0, $iv);
        
        if ($encrypted === false) {
            throw new \Exception('Encryption failed');
        }
        
        return base64_encode($encrypted . '::' . bin2hex($iv));
    }
    
    /**
     * Decrypt data
     */
    public static function decrypt(string $data): string
    {
        $key = self::getEncryptionKey();
        $decoded = base64_decode($data);
        
        if ($decoded === false) {
            throw new \Exception('Failed to decode encrypted data');
        }
        
        list($encrypted, $iv_hex) = explode('::', $decoded, 2);
        $iv = hex2bin($iv_hex);
        
        if ($iv === false) {
            throw new \Exception('Failed to decode IV');
        }
        
        $decrypted = openssl_decrypt($encrypted, self::CIPHER, $key, 0, $iv);
        
        if ($decrypted === false) {
            throw new \Exception('Decryption failed');
        }
        
        return $decrypted;
    }
}
