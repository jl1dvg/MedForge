<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Modules\Shared\Support\LegacySessionAuth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UnifiedLogoutController
{
    public function logout(Request $request): JsonResponse|RedirectResponse
    {
        LegacySessionAuth::destroySession($request);

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        $response = $request->expectsJson()
            ? response()->json([
                'success' => true,
                'message' => 'Sesión cerrada',
            ])
            : redirect('/auth/login?logged_out=1');

        $domain = config('session.domain');
        $sessionCookieName = (string) config('session.cookie', 'laravel-session');

        return $response
            ->withoutCookie('PHPSESSID', '/', $domain)
            ->withoutCookie($sessionCookieName, '/', $domain)
            ->withoutCookie('XSRF-TOKEN', '/', $domain);
    }
}
