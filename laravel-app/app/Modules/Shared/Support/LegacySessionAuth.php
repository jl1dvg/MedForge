<?php

namespace App\Modules\Shared\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LegacySessionAuth
{
    private const ATTR_SESSION = '_legacy_session_data';
    private const ATTR_SESSION_ID = '_legacy_session_id';
    private const ATTR_USER_ID = '_legacy_user_id';

    public static function isAuthenticated(Request $request): bool
    {
        return self::userId($request) !== null;
    }

    public static function userId(Request $request): ?int
    {
        $authId = Auth::id();
        if (is_numeric($authId)) {
            $normalized = (int) $authId;
            if ($normalized > 0) {
                return $normalized;
            }
        }

        return self::legacyUserId($request);
    }

    public static function legacyUserId(Request $request): ?int
    {
        self::hydrateRequest($request);
        $cached = $request->attributes->get(self::ATTR_USER_ID);
        if (!is_int($cached)) {
            return null;
        }

        return $cached > 0 ? $cached : null;
    }

    /**
     * @return array<string, mixed>
     */
    public static function readSession(Request $request): array
    {
        self::hydrateRequest($request);
        $session = $request->attributes->get(self::ATTR_SESSION, []);

        return is_array($session) ? $session : [];
    }

    public static function hydrateRequest(Request $request): void
    {
        if ($request->attributes->has(self::ATTR_SESSION)) {
            return;
        }

        $sessionId = self::resolveSessionId($request);
        $session = $sessionId !== '' ? self::readBySessionId($sessionId) : [];
        $userId = self::extractUserId($session);

        $request->attributes->set(self::ATTR_SESSION_ID, $sessionId);
        $request->attributes->set(self::ATTR_SESSION, $session);
        $request->attributes->set(self::ATTR_USER_ID, $userId);
    }

    public static function sessionId(Request $request): string
    {
        self::hydrateRequest($request);
        $sessionId = $request->attributes->get(self::ATTR_SESSION_ID, '');

        return is_string($sessionId) ? $sessionId : '';
    }

    public static function destroySession(Request $request): bool
    {
        $sessionId = self::sessionId($request);
        if ($sessionId === '') {
            return false;
        }

        $destroyed = self::destroyBySessionId($sessionId);
        $request->attributes->set(self::ATTR_SESSION, []);
        $request->attributes->set(self::ATTR_USER_ID, null);

        return $destroyed;
    }

    public static function bootstrapLaravelAuth(Request $request): bool
    {
        if (Auth::check()) {
            return true;
        }

        $userId = self::legacyUserId($request);
        if ($userId === null) {
            return false;
        }

        $user = User::query()->find($userId);
        if (!$user instanceof User) {
            return false;
        }

        Auth::login($user);

        return Auth::check();
    }

    /**
     * @param array<string, mixed> $sessionData
     */
    public static function writeCompatibilitySession(array $sessionData, ?string $sessionId = null): string
    {
        $originalName = session_name();
        $originalId = session_id();
        $wasActive = session_status() === PHP_SESSION_ACTIVE;

        if ($wasActive) {
            @session_write_close();
        }

        session_name('PHPSESSID');
        if (self::isValidSessionId($sessionId)) {
            session_id((string) $sessionId);
        } else {
            session_id('');
        }

        $started = @session_start();
        $activeSessionId = $started ? session_id() : '';

        if ($started) {
            $existing = is_array($_SESSION ?? null) ? $_SESSION : [];
            $_SESSION = array_merge($existing, $sessionData);
            @session_write_close();
        }

        if ($originalName !== '') {
            @session_name($originalName);
        }

        @session_id($originalId);

        return is_string($activeSessionId) ? $activeSessionId : '';
    }

    private static function resolveSessionId(Request $request): string
    {
        $sessionId = trim((string) $request->cookie('PHPSESSID', ''));
        if ($sessionId === '') {
            $sessionId = self::extractCookieValue((string) $request->headers->get('cookie', ''), 'PHPSESSID');
        }

        if (!self::isValidSessionId($sessionId)) {
            return '';
        }

        return $sessionId;
    }

    private static function extractCookieValue(string $cookieHeader, string $name): string
    {
        if ($cookieHeader === '') {
            return '';
        }

        foreach (explode(';', $cookieHeader) as $pair) {
            [$key, $value] = array_pad(explode('=', trim($pair), 2), 2, '');
            if ($key !== $name) {
                continue;
            }

            return trim(urldecode($value));
        }

        return '';
    }

    /**
     * @return array<string, mixed>
     */
    private static function readBySessionId(string $sessionId): array
    {
        $originalName = session_name();
        $originalId = session_id();
        $wasActive = session_status() === PHP_SESSION_ACTIVE;

        if ($wasActive) {
            @session_write_close();
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

    private static function destroyBySessionId(string $sessionId): bool
    {
        $originalName = session_name();
        $originalId = session_id();
        $wasActive = session_status() === PHP_SESSION_ACTIVE;

        if ($wasActive) {
            @session_write_close();
        }

        session_name('PHPSESSID');
        session_id($sessionId);

        $started = @session_start();
        if ($started) {
            $_SESSION = [];
            @session_destroy();
        }

        if ($originalName !== '') {
            @session_name($originalName);
        }

        if ($originalId !== '') {
            @session_id($originalId);
        }

        return $started;
    }

    /**
     * @param array<string, mixed> $session
     */
    private static function extractUserId(array $session): ?int
    {
        $raw = $session['user_id'] ?? null;
        if (!is_numeric($raw)) {
            return null;
        }

        $userId = (int) $raw;

        return $userId > 0 ? $userId : null;
    }

    private static function isValidSessionId(?string $sessionId): bool
    {
        if (!is_string($sessionId)) {
            return false;
        }

        return preg_match('/^[A-Za-z0-9,-]{8,128}$/', $sessionId) === 1;
    }
}
