<?php
namespace MyOrdersVault\Core;

// AES-256-GCM at-rest encryption for sensitive columns (OAuth tokens).
// Values are tagged with a "enc:v1:" prefix so decrypt() can tell an
// already-encrypted value apart from a legacy plaintext token that
// predates this class and pass it through unchanged.
class Crypto {
    private const PREFIX = 'enc:v1:';
    private const CIPHER = 'aes-256-gcm';

    private static ?string $key = null;

    private static function key(): string {
        if (self::$key !== null) {
            return self::$key;
        }

        $configPath = __DIR__ . '/../../../config/config.php';
        if (!file_exists($configPath)) {
            $configPath = __DIR__ . '/../../config/config.php';
        }
        $config = require $configPath;

        $encoded = $config['app']['encryption_key'] ?? null;
        if (!$encoded) {
            throw new \RuntimeException('config.php is missing app.encryption_key');
        }

        $key = base64_decode($encoded, true);
        if ($key === false || strlen($key) !== 32) {
            throw new \RuntimeException('app.encryption_key must be a base64-encoded 32-byte key');
        }

        return self::$key = $key;
    }

    public static function encrypt(?string $plaintext): ?string {
        if ($plaintext === null || $plaintext === '') {
            return $plaintext;
        }

        $iv = random_bytes(openssl_cipher_iv_length(self::CIPHER));
        $tag = '';
        $ciphertext = openssl_encrypt($plaintext, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($ciphertext === false) {
            throw new \RuntimeException('Encryption failed');
        }

        return self::PREFIX . base64_encode($iv . $tag . $ciphertext);
    }

    public static function decrypt(?string $value): ?string {
        if ($value === null || $value === '' || !str_starts_with($value, self::PREFIX)) {
            // Not our format — either empty or a legacy plaintext token.
            return $value;
        }

        $raw = base64_decode(substr($value, strlen(self::PREFIX)), true);
        $ivLen = openssl_cipher_iv_length(self::CIPHER);
        $iv = substr($raw, 0, $ivLen);
        $tag = substr($raw, $ivLen, 16);
        $ciphertext = substr($raw, $ivLen + 16);

        $plaintext = openssl_decrypt($ciphertext, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($plaintext === false) {
            throw new \RuntimeException('Decryption failed — wrong key or corrupted value');
        }

        return $plaintext;
    }

    public static function isEncrypted(?string $value): bool {
        return $value !== null && str_starts_with($value, self::PREFIX);
    }
}
