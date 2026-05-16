<?php

namespace App\Http\Middleware;

use App\Modules\Shared\Support\LegacySessionAuth;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LegacySessionBridge
{
    public function handle(Request $request, Closure $next): Response
    {
        if ((bool) config('auth_migration.accept_legacy_session', true)) {
            LegacySessionAuth::hydrateRequest($request);
        }

        return $next($request);
    }
}
