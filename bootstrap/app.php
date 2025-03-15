<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // APIルートグループ
        $middleware->api(prepend: [
            \App\Http\Middleware\LogUserActions::class,
        ]);

        // 認証ルートグループ
        $middleware->group('auth_routes', [
            \App\Http\Middleware\LogUserActions::class,
        ]);

        // 認証APIミドルウェアグループ
        $middleware->group('auth_api', [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            'auth:sanctum'
        ]);

        $middleware->alias([
            'verified' => \App\Http\Middleware\EnsureEmailIsVerified::class,
            'log.user.actions' => \App\Http\Middleware\LogUserActions::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
