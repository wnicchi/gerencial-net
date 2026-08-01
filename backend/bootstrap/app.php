<?php

use App\Support\RegistroError;
use Illuminate\Database\QueryException;
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
        // Alias para verificar que el usuario sea Administrador (NIVEL = 1)
        // Se usa en las rutas del grupo /api/admin
        $middleware->alias([
            'admin' => \App\Http\Middleware\EsAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // Log centralizado de errores SQL: toda QueryException no atrapada
        // se guarda en log_errores_sql (fecha, terminal, usuario, módulo,
        // comando SQL y detalle). No interrumpe el manejo normal del error.
        $exceptions->report(function (QueryException $e): void {
            RegistroError::registrar($e);
        });
    })->create();
