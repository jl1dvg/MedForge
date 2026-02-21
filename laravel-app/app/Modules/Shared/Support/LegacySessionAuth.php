<?php

namespace App\Modules\Shared\Support;

use Illuminate\Http\Request;

class LegacySessionAuth
{
    public static function isAuthenticated(Request $request): bool
    {
        return self::userId($request) !== null;
    }

    public static function userId(Request $request): ?int
    {
        $session = self::readSession($request);
        $raw = $session['user_id'] ?? null;
        if (!is_numeric($raw)) {
            return null;
        }

        $userId = (int) $raw;
        return $userId > 0 ? $userId : null;
    }

    /**
     * @return array<string, mixed>
     */
    public static function readSession(Request $request): array
    {
        $sessionId = (string) $request->cookie('PHPSESSID', '');
        if ($sessionId === '') {
            return [];
        }

        $originalName = session_name();
        $originalId = session_id();
        $wasActive = session_status() === PHP_SESSION_ACTIVE;

        if ($wasActive) {
            session_write_close();
        }

        session_name('PHPSESSID');
        session_id($sessionId);

        $started = @session_start(['read_and_close' => true]);
        $data = $started && is_array($_SESSION ?? null) ? $_SESSION : [];
        $_SESSION = [];

        if ($originalName !== '') {
            @session_name($originalName);
        }
        if ($originalId !== '') {
            @session_id($originalId);
        }

        return is_array($data) ? $data : [];
    }
}

