<?php

use App\Http\Middleware\AgregaCabecerasDeSeguridad;
use App\Http\Middleware\VerificaRol;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Sanctum en modo SPA: las peticiones que llegan desde el frontend
        // declarado en SANCTUM_STATEFUL_DOMAINS usan la sesión por cookie y
        // pasan por la verificación CSRF, como cualquier formulario web.
        $middleware->statefulApi();

        $middleware->api(append: [
            AgregaCabecerasDeSeguridad::class,
        ]);

        $middleware->web(append: [
            AgregaCabecerasDeSeguridad::class,
        ]);

        $middleware->alias([
            'rol' => VerificaRol::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request): bool => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
