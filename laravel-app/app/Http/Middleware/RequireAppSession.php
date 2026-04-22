<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RequireAppSession
{
    public function handle(Request $request, Closure $next): Response|RedirectResponse
    {
        if (Auth::check()) {
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
