<?php

use App\Http\Middleware\ApplyBrowserTimezone;
use App\Http\Middleware\LegacySessionBridge;
use App\Http\Middleware\MarkLegacyAliasUsage;
use App\Http\Middleware\EnsureWhatsappFeatureEnabled;
use App\Http\Middleware\RequireAppPermission;
use App\Http\Middleware\RequireAppSession;
use App\Http\Middleware\CiveExtensionAuth;
use App\Http\Middleware\ConsultasCors;
use App\Http\Middleware\RequireLegacyPermission;
use App\Http\Middleware\RequireLegacySession;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Session\TokenMismatchException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: '',
    )
    ->withEvents(false)
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['PHPSESSID', 'app_timezone']);
        // LegacySessionBridge runs after Laravel session; ApplyBrowserTimezone reads cookie.
        $middleware->web(append: [LegacySessionBridge::class, ApplyBrowserTimezone::class]);
        $middleware->api(append: [LegacySessionBridge::class]);
        $middleware->alias([
            'app.auth' => RequireAppSession::class,
            'app.permission' => RequireAppPermission::class,
            'legacy.auth' => RequireLegacySession::class,
            'legacy.permission' => RequireLegacyPermission::class,
            'legacy.alias' => MarkLegacyAliasUsage::class,
            'consultas.cors' => ConsultasCors::class,
            'cive.extension.auth' => CiveExtensionAuth::class,
            'whatsapp.feature' => EnsureWhatsappFeatureEnabled::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (TokenMismatchException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Sesión expirada'], 419);
            }

            // Avoid ->withInput() here: the session may be in an inconsistent
            // state when CSRF validation fails, causing a secondary exception
            // that would render the raw 419 page instead of this redirect.
            return redirect('/auth/login?expired=1');
        });
    })->create();
