<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RequireOwnerAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $ownerEmail = config('medforge-owner.email');

        if (!$ownerEmail || !Auth::check() || Auth::user()->email !== $ownerEmail) {
            abort(403, 'Acceso restringido al propietario de la plataforma.');
        }

        return $next($request);
    }
}
