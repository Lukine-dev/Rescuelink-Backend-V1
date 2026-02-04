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
      ->withMiddleware(function (Middleware $middleware): void {

        /**
         * Global middleware (runs BEFORE auth & JWT)
         */
        $middleware->append(\App\Http\Middleware\CorsMiddleware::class);

        /**
         * Route middleware aliases
         */
        $middleware->alias([
            'verified' => \App\Http\Middleware\EnsureEmailIsVerified::class,
            'role'     => \App\Http\Middleware\CheckRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Leave CORS handling to middleware only
    })
    ->create();

