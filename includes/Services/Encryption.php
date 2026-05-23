<?php

namespace Softminal\ImageCraft\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Encryption service for API keys
 */
class Encryption
{
    private const CIPHER = 'aes-256-cbc';

    /** @var string|null Cached correctly-cased cipher name from openssl_get_cipher_methods(). */
    private static ?string $resolvedCipher = null;

    /**
     * Resolve the correctly-cased OpenSSL cipher name, cached for the request lifetime.
     * Replaces the per-call scan of 200+ entries in encrypt() and decrypt().
     *
     * @throws \Exception If the required cipher is not available.
     */
    private static function resolveCipher(): string
    {
        if (self::$resolvedCipher !== null) {
            return self::$resolvedCipher;
        }

        if (!function_exists('openssl_encrypt')) {
            throw new \Exception('OpenSSL extension is not available. Please enable OpenSSL in your PHP configuration.');
        }

        $available = openssl_get_cipher_methods();
        $target = strtolower(self::CIPHER);

        foreach ($available as $c) {
            if (strtolower($c) === $target) {
                self::$resolvedCipher = $c;
                return $c;
            }
        }

        throw new \Exception('Cipher ' . esc_html(self::CIPHER) . ' is not available. Available ciphers: ' . esc_html(implode(', ', array_slice($available, 0, 10))) . '...');
    }

    /**
     * Get encryption key from WordPress salts
     */
    private static function getEncryptionKey(): string
    {
        if (!defined('AUTH_KEY') || !defined('SECURE_AUTH_KEY')) {
            throw new \Exception('WordPress security keys not defined');
        }

        // Reject default/placeholder WordPress salts — they provide no security
        $placeholder = 'put your unique phrase here';
        if (AUTH_KEY === $placeholder || SECURE_AUTH_KEY === $placeholder) {
            throw new \Exception(
                'WordPress security salts are still set to their default values. '
                . 'Please generate unique salts at https://api.wordpress.org/secret-key/1.1/salt/ '
                . 'and add them to your wp-config.php.'
            );
        }

        return hash('sha256', AUTH_KEY . SECURE_AUTH_KEY);
    }

    /**
     * Encrypt data
     */
    public static function encrypt(string $data): string
    {
        $cipherToUse = self::resolveCipher();

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

        // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Required for safe storage of encrypted API key data
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
        $cipherToUse = self::resolveCipher();

        $key = self::getEncryptionKey();
        // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Required for decrypting stored API key data
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
