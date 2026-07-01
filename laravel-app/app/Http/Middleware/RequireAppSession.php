<?php

namespace App\Http\Middleware;

use App\Modules\ControlCenter\Services\OperationalStateResolver;
use App\Modules\Shared\Support\LegacyPermissionResolver;
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

    public function __construct(private readonly OperationalStateResolver $stateResolver)
    {
    }

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

        $operationalState = $this->stateResolver->resolve();
        $state = $operationalState['state'] ?? OperationalStateResolver::PRODUCTION;

        if ($state === OperationalStateResolver::SUSPENDED && !$this->isAllowlisted($request)) {
            return $this->blockedResponse($request, 'Instancia suspendida. Contacta al equipo MedForge.', 423);
        }

        if ($state === OperationalStateResolver::MAINTENANCE
            && !$this->isAllowlisted($request)
            && !$this->isInternalMaintenanceUser($request)) {
            return $this->blockedResponse($request, 'Instancia en mantenimiento. Acceso temporalmente limitado.', 423);
        }

        if ($state === OperationalStateResolver::READONLY
            && !in_array($request->method(), self::SAFE_METHODS, true)
            && !$this->isAllowlisted($request)) {
            $message = $operationalState['reason'] ?: 'Instancia en modo solo lectura.';

            return $this->blockedResponse($request, $message, 423);
        }

        if (!in_array($request->method(), self::SAFE_METHODS, true) && ReadOnlyMode::isActive()) {
            $message = ReadOnlyMode::message();

            return $this->blockedResponse($request, $message, 423);
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

    private function isAllowlisted(Request $request): bool
    {
        $path = trim($request->path(), '/');

        foreach ((array) config('control_center.allowlist', []) as $allowedPath) {
            if ($path === trim((string) $allowedPath, '/')) {
                return true;
            }
        }

        return false;
    }

    private function isInternalMaintenanceUser(Request $request): bool
    {
        return LegacyPermissionResolver::canAny($request, (array) config('control_center.maintenance_permissions', []));
    }

    private function blockedResponse(Request $request, string $message, int $status): Response|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['error' => $message], $status);
        }

        return back()->with('error', $message);
    }
}
