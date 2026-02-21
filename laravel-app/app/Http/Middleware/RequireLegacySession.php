<?php

namespace App\Http\Middleware;

use App\Modules\Shared\Support\LegacySessionAuth;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireLegacySession
{
    public function handle(Request $request, Closure $next): Response|RedirectResponse
    {
        if (LegacySessionAuth::isAuthenticated($request)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['error' => 'Sesión expirada'], 401);
        }

        return redirect('/auth/login?auth_required=1');
    }
}
