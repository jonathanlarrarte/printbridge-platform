<?php

use App\Http\Controllers\Api\AgenteController;
use App\Http\Controllers\Api\EstadisticaController;
use App\Http\Controllers\Api\TrabajoController;
use App\Http\Controllers\Api\WebhookController;
use App\Http\Controllers\AgenteIngestaController;
use Illuminate\Support\Facades\Route;

// Healthcheck publico (seccion 11 del doc de arquitectura).
Route::get('/health', fn () => response()->json(['ok' => true]));

// Endpoints privados agente -> plataforma (seccion 6.1). No son parte de la
// API publica: se autentican con el token propio de la instalacion, no con
// un API key de empresa.
Route::post('/agente/registrar', [AgenteIngestaController::class, 'registrar']);

Route::middleware('agente.auth')->group(function () {
    Route::post('/agente/heartbeat', [AgenteIngestaController::class, 'heartbeat']);
    Route::post('/agente/eventos', [AgenteIngestaController::class, 'eventos']);
});

// API publica v1 (seccion 6), autenticada con Sanctum token personal por empresa.
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    Route::get('/agentes', [AgenteController::class, 'index']);
    Route::get('/agentes/{id}', [AgenteController::class, 'show']);
    Route::get('/agentes/{id}/impresoras', [AgenteController::class, 'impresoras']);

    Route::get('/trabajos', [TrabajoController::class, 'index']);
    Route::get('/trabajos/{id}', [TrabajoController::class, 'show']);

    Route::get('/webhooks', [WebhookController::class, 'index']);
    Route::post('/webhooks', [WebhookController::class, 'store']);
    Route::delete('/webhooks/{id}', [WebhookController::class, 'destroy']);
    Route::get('/webhooks/{id}/entregas', [WebhookController::class, 'entregas']);

    Route::get('/estadisticas/resumen', [EstadisticaController::class, 'resumen']);
    Route::get('/estadisticas/agente/{id}', [EstadisticaController::class, 'agente']);
});
