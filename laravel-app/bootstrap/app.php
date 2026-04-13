<?php

use App\Http\Middleware\LegacySessionBridge;
use App\Http\Middleware\EnsureWhatsappFeatureEnabled;
use App\Http\Middleware\RequireAppPermission;
use App\Http\Middleware\RequireAppSession;
use App\Http\Middleware\ConsultasCors;
use App\Http\Middleware\RequireLegacyPermission;
use App\Http\Middleware\RequireLegacySession;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

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
        $middleware->web(prepend: [LegacySessionBridge::class]);
        $middleware->api(prepend: [LegacySessionBridge::class]);
        $middleware->alias([
            'app.auth' => RequireAppSession::class,
            'app.permission' => RequireAppPermission::class,
            'legacy.auth' => RequireLegacySession::class,
            'legacy.permission' => RequireLegacyPermission::class,
            'consultas.cors' => ConsultasCors::class,
            'whatsapp.feature' => EnsureWhatsappFeatureEnabled::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
