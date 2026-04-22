<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Modules\Shared\Support\LegacySessionAuth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Auth;

class UnifiedLogoutController
{
    public function logout(Request $request): JsonResponse|RedirectResponse
    {
        Auth::guard('web')->logout();
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

        $sessionCookieName = (string) config('session.cookie', 'laravel-session');
        $domains = $this->cookieDomains();

        foreach ($domains as $domain) {
            $response = $this->expireCookie($response, 'PHPSESSID', $domain);
            $response = $this->expireCookie($response, $sessionCookieName, $domain);
            $response = $this->expireCookie($response, 'XSRF-TOKEN', $domain);
            $response = $this->expireCookie($response, 'remember_web_' . sha1('Illuminate\Auth\Recaller'), $domain);
        }

        return $response;
    }

    /**
     * @return array<int, string|null>
     */
    private function cookieDomains(): array
    {
        $configured = trim((string) config('session.domain', ''));
        $domains = [null];

        if ($configured !== '' && strcasecmp($configured, 'null') !== 0) {
            $domains[] = $configured;

            if (!str_starts_with($configured, '.')) {
                $domains[] = '.' . $configured;
            }
        }

        return array_values(array_unique($domains, SORT_REGULAR));
    }

    private function expireCookie(JsonResponse|RedirectResponse $response, string $name, ?string $domain): JsonResponse|RedirectResponse
    {
        /** @var HttpResponse $response */
        return $response->withoutCookie($name, '/', $domain);
    }
}
