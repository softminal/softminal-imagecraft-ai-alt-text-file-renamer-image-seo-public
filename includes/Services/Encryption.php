<?php

namespace SmartAltTextAi\Includes\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Encryption service for API keys
 */
class Encryption
{
    private const CIPHER = 'aes-256-cbc';

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
        // Check if OpenSSL is available
        if (!function_exists('openssl_encrypt')) {
            throw new \Exception('OpenSSL extension is not available. Please enable OpenSSL in your PHP configuration.');
        }

        // Check if cipher is available (case-insensitive check)
        $availableCiphers = openssl_get_cipher_methods();
        $cipherLower = strtolower(self::CIPHER);
        $cipherToUse = self::CIPHER; // Default fallback
        $cipherFound = false;

        foreach ($availableCiphers as $availableCipher) {
            if (strtolower($availableCipher) === $cipherLower) {
                $cipherFound = true;
                // Use the actual cipher name from OpenSSL (might be different case)
                $cipherToUse = $availableCipher;
                break;
            }
        }

        if (!$cipherFound) {
            throw new \Exception('Cipher ' . esc_html(self::CIPHER) . ' is not available. Available ciphers: ' . esc_html(implode(', ', array_slice($availableCiphers, 0, 10))) . '...');
        }

        try {
            $key = self::getEncryptionKey();
        } catch (\Exception $e) {
            throw new \Exception('Failed to get encryption key: ' . esc_html($e->getMessage()));
        }

        $ivLength = openssl_cipher_iv_length($cipherToUse);
        if ($ivLength === false) {
            throw new \Exception('Failed to get IV length for cipher: ' . esc_html($cipherToUse));
        }

        $iv = openssl_random_pseudo_bytes($ivLength);

        if ($iv === false) {
            throw new \Exception('Failed to generate IV. OpenSSL error: ' . esc_html(openssl_error_string() ?: ''));
        }

        $encrypted = openssl_encrypt($data, $cipherToUse, $key, 0, $iv);

        if ($encrypted === false) {
            $error = openssl_error_string();
            throw new \Exception('Encryption failed. OpenSSL error: ' . esc_html($error ?: 'Unknown error'));
        }

        if (empty($encrypted)) {
            throw new \Exception('Encryption returned empty string. Data length: ' . esc_html((string) strlen($data)) . ', Key length: ' . esc_html((string) strlen($key)));
        }

        $result = base64_encode($encrypted . '::' . bin2hex($iv));

        if (empty($result)) {
            throw new \Exception('Base64 encoding failed. Encrypted length: ' . esc_html((string) strlen($encrypted)));
        }

        return $result;
    }

    /**
     * Decrypt data
     */
    public static function decrypt(string $data): string
    {
        // Check if OpenSSL is available
        if (!function_exists('openssl_decrypt')) {
            throw new \Exception('OpenSSL extension is not available. Please enable OpenSSL in your PHP configuration.');
        }

        // Find the actual cipher name (case-insensitive)
        $availableCiphers = openssl_get_cipher_methods();
        $cipherLower = strtolower(self::CIPHER);
        $cipherToUse = self::CIPHER; // Default
        foreach ($availableCiphers as $availableCipher) {
            if (strtolower($availableCipher) === $cipherLower) {
                $cipherToUse = $availableCipher;
                break;
            }
        }

        $key = self::getEncryptionKey();
        $decoded = base64_decode($data, true);

        if ($decoded === false) {
            throw new \Exception('Failed to decode encrypted data');
        }

        if (strpos($decoded, '::') === false) {
            throw new \Exception('Invalid encrypted data format');
        }

        list($encrypted, $iv_hex) = explode('::', $decoded, 2);

        if (empty($encrypted) || empty($iv_hex)) {
            throw new \Exception('Corrupted encrypted data');
        }

        $iv = hex2bin($iv_hex);

        if ($iv === false) {
            throw new \Exception('Failed to decode IV');
        }

        $decrypted = openssl_decrypt($encrypted, $cipherToUse, $key, 0, $iv);

        if ($decrypted === false) {
            throw new \Exception('Decryption failed');
        }

        return $decrypted;
    }
}
