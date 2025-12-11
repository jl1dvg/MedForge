<?php

namespace Helpers;

use Helpers\JsonLogger;

class SecurityAuditLogger
{
    public static function log(string $action, array $context = []): void
    {
        $enrichedContext = array_filter([
            'user_id' => $_SESSION['user_id'] ?? null,
            'role_id' => $_SESSION['role_id'] ?? null,
            'ip' => $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'cli',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'request_uri' => $_SERVER['REQUEST_URI'] ?? null,
        ] + $context, static fn($value) => $value !== null && $value !== '');

        JsonLogger::log('security-audit', $action, null, $enrichedContext);
    }
}
