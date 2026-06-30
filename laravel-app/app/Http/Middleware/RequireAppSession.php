<?php

namespace App\Http\Middleware;

use App\Modules\Shared\Support\LegacySessionAuth;
use App\Modules\Shared\Support\ReadOnlyMode;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RequireAppSession
{
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    public function handle(Request $request, Closure $next): Response|RedirectResponse
    {
        if ((bool) config('auth_migration.accept_legacy_session', true)) {
            LegacySessionAuth::bootstrapLaravelAuth($request);
        }

        if (!Auth::check()) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Sesión expirada'], 401);
            }

            if ($request->hasSession()) {
                $request->session()->put('url.intended', $request->fullUrl());
            }

            return redirect('/auth/login?' . (self::hadExistingSession($request) ? 'expired=1' : 'auth_required=1'));
        }

        if (!in_array($request->method(), self::SAFE_METHODS, true) && ReadOnlyMode::isActive()) {
            $message = ReadOnlyMode::message();

            if ($request->expectsJson()) {
                return response()->json(['error' => $message], 423);
            }

            return back()->with('error', $message);
        }

        return $next($request);
    }

    /**
     * Distinguishes "the session expired" from "never logged in" purely by
     * cookie presence, without touching Auth::check() ordering — a previous
     * attempt to detect expiry via Auth::check() transitions (see git history
     * for RequireAppSession) caused false-positive logouts on active sessions.
     */
    private static function hadExistingSession(Request $request): bool
    {
        return $request->cookies->has(config('session.cookie'))
            || $request->cookies->has('PHPSESSID');
    }
}
