<?php

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
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['PHPSESSID']);
        // Append instead of prepend so LegacySessionBridge runs AFTER Laravel's
        // StartSession and VerifyCsrfToken — prevents PHP native session_start()
        // from interfering with Laravel's session handler and causing 419 errors.
        $middleware->web(append: [LegacySessionBridge::class]);
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

            return redirect('/auth/login?expired=1')
                ->withInput($request->only('username'));
        });
    })->create();
