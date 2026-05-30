<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplyBrowserTimezone
{
    public function handle(Request $request, Closure $next): Response
    {
        $tz = trim((string) $request->cookie('app_timezone', ''));

        if ($tz !== '' && in_array($tz, \DateTimeZone::listIdentifiers(), true)) {
            config(['app.timezone' => $tz]);
            date_default_timezone_set($tz);
        }

        return $next($request);
    }
}
