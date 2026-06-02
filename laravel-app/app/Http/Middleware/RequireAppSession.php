<?php

namespace App\Http\Middleware;

use App\Modules\Shared\Support\LegacySessionAuth;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RequireAppSession
{
    public function handle(Request $request, Closure $next): Response|RedirectResponse
    {
        $wasAuthenticated = Auth::check();

        if ((bool) config('auth_migration.accept_legacy_session', true)) {
            LegacySessionAuth::bootstrapLaravelAuth($request);
        }

        if (Auth::check()) {
            if (!$wasAuthenticated) {
                // The Laravel session expired but the legacy bridge silently
                // re-authenticated via PHPSESSID. Force re-login so the user
                // sees "session expired", gets a fresh CSRF token, and the
                // new session is fully initialized (sigcenter_password, etc.).
                if (!$request->expectsJson() && $request->hasSession()) {
                    $request->session()->put('url.intended', $request->fullUrl());
                }
                Auth::logout();

                if ($request->expectsJson()) {
                    return response()->json(['error' => 'Sesión expirada'], 401);
                }

                return redirect('/auth/login?expired=1');
            }

            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['error' => 'Sesión expirada'], 401);
        }

        if ($request->hasSession()) {
            $request->session()->put('url.intended', $request->fullUrl());
        }

        return redirect('/auth/login?auth_required=1');
    }
}
