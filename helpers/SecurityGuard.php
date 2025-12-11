<?php

namespace Helpers;

class SecurityGuard
{
    public static function enforceRequestSecurity(): void
    {
        if (PHP_SAPI === 'cli') {
            return;
        }

        self::verifyTls();
        self::verifyStorageEncryption();
        self::publishRetentionPolicy();
    }

    private static function verifyTls(): void
    {
        $requireTlsRaw = $_ENV['SECURITY_REQUIRE_TLS'] ?? getenv('SECURITY_REQUIRE_TLS') ?? '0';
        $requireTls = filter_var($requireTlsRaw, FILTER_VALIDATE_BOOLEAN);
        $isSecure = self::isSecureRequest();

        SecurityAuditLogger::log('tls_verification', [
            'tls_required' => $requireTls,
            'tls_detected' => $isSecure,
        ]);

        if ($requireTls && !$isSecure) {
            http_response_code(403);
            echo 'Se requiere una conexión segura (HTTPS).';
            exit;
        }
    }

    private static function verifyStorageEncryption(): void
    {
        $encryptionEnabledRaw = $_ENV['STORAGE_ENCRYPTION_ENABLED'] ?? getenv('STORAGE_ENCRYPTION_ENABLED') ?? '0';
        $encryptionEnabled = filter_var($encryptionEnabledRaw, FILTER_VALIDATE_BOOLEAN);
        $encryptionKey = $_ENV['STORAGE_ENCRYPTION_KEY'] ?? getenv('STORAGE_ENCRYPTION_KEY');

        SecurityAuditLogger::log('storage_encryption_check', [
            'storage_encryption_enabled' => $encryptionEnabled,
            'has_encryption_key' => !empty($encryptionKey),
        ]);
    }

    private static function publishRetentionPolicy(): void
    {
        $retentionRaw = $_ENV['DATA_RETENTION_DAYS'] ?? getenv('DATA_RETENTION_DAYS');
        $retentionDays = is_numeric($retentionRaw) ? max(1, (int) $retentionRaw) : 365;

        SecurityAuditLogger::log('data_retention_policy', [
            'retention_days' => $retentionDays,
        ]);

        if (!headers_sent()) {
            header('X-Data-Retention-Days: ' . $retentionDays);
        }
    }

    private static function isSecureRequest(): bool
    {
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }

        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
            return true;
        }

        if (!empty($_SERVER['HTTP_X_FORWARDED_SCHEME']) && strtolower($_SERVER['HTTP_X_FORWARDED_SCHEME']) === 'https') {
            return true;
        }

        if (!empty($_SERVER['HTTP_X_FORWARDED_PORT']) && (int) $_SERVER['HTTP_X_FORWARDED_PORT'] === 443) {
            return true;
        }

        return isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443;
    }
}
