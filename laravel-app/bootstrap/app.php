<?php

use App\Http\Middleware\LegacySessionBridge;
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
            'legacy.auth' => RequireLegacySession::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
