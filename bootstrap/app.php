<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: '',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'agente.auth' => \App\Http\Middleware\AutenticarAgente::class,
        ]);

        // API pura, sin pantalla de login: evita que Laravel intente
        // redirigir a route('login') (inexistente) para requests sin
        // Accept: application/json.
        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Esta plataforma no tiene login de sesion web: las rutas v1/* y
        // agente/* deben responder JSON 401, nunca redirigir a una ruta
        // "login" inexistente.
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            if ($request->is('v1/*') || $request->is('agente/*')) {
                return response()->json(['error' => 'no autenticado'], 401);
            }
        });
    })->create();
