<?php

namespace Modules\Usuarios\Support;

class SensitiveDataProtector
{
    private const CIPHER = 'aes-256-cbc';

    private bool $enabled;
    private ?string $key;

    public function __construct()
    {
        $enabledRaw = $_ENV['STORAGE_ENCRYPTION_ENABLED'] ?? getenv('STORAGE_ENCRYPTION_ENABLED') ?? '0';
        $this->enabled = filter_var($enabledRaw, FILTER_VALIDATE_BOOLEAN);
        $rawKey = $_ENV['STORAGE_ENCRYPTION_KEY'] ?? getenv('STORAGE_ENCRYPTION_KEY');
        $this->key = $rawKey ? substr(hash('sha256', $rawKey, true), 0, 32) : null;
    }

    public function encrypt(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!$this->canEncrypt()) {
            return $value;
        }

        $ivLength = openssl_cipher_iv_length(self::CIPHER);
        $iv = random_bytes($ivLength);
        $ciphertext = openssl_encrypt($value, self::CIPHER, $this->key, OPENSSL_RAW_DATA, $iv);

        if ($ciphertext === false) {
            return $value;
        }

        return base64_encode($iv . $ciphertext);
    }

    public function decrypt(?string $payload): ?string
    {
        if ($payload === null || $payload === '') {
            return null;
        }

        if (!$this->canEncrypt()) {
            return $payload;
        }

        $decoded = base64_decode($payload, true);
        if ($decoded === false) {
            return $payload;
        }

        $ivLength = openssl_cipher_iv_length(self::CIPHER);
        $iv = substr($decoded, 0, $ivLength);
        $ciphertext = substr($decoded, $ivLength);

        if ($iv === false || $ciphertext === false || strlen($iv) !== $ivLength) {
            return $payload;
        }

        $plaintext = openssl_decrypt($ciphertext, self::CIPHER, $this->key, OPENSSL_RAW_DATA, $iv);

        return $plaintext === false ? $payload : $plaintext;
    }

    public function mask(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $length = mb_strlen($value, 'UTF-8');
        if ($length <= 4) {
            return str_repeat('•', max(0, $length - 1)) . mb_substr($value, -1, null, 'UTF-8');
        }

        return str_repeat('•', $length - 4) . mb_substr($value, -4, null, 'UTF-8');
    }

    private function canEncrypt(): bool
    {
        return $this->enabled && !empty($this->key);
    }
}
