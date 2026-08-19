<?php

use App\Http\Controllers\AgenteIngestaController;
use App\Http\Controllers\Api\Admin\ApiKeyAdminController;
use App\Http\Controllers\Api\Admin\EmpresaAdminController;
use App\Http\Controllers\Api\Admin\ResumenAdminController;
use App\Http\Controllers\Api\AgenteController;
use App\Http\Controllers\Api\ApiKeyController;
use App\Http\Controllers\Api\EmpresaController;
use App\Http\Controllers\Api\EstadisticaController;
use App\Http\Controllers\Api\PruebaImpresionController;
use App\Http\Controllers\Api\TrabajoController;
use App\Http\Controllers\Api\WebhookController;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

// Healthcheck publico (seccion 11 del doc de arquitectura).
Route::get('/health', fn () => response()->json(['ok' => true]));

// Documento de auto-descubrimiento: el primer lugar donde alguien pega la
// URL base de la API sin saber nada mas todavia deberia encontrar como
// seguir, sin necesitar un token.
Route::get('/v1', fn () => response()->json([
    'plataforma' => config('app.name'),
    'documentacion' => url('/docs/api'),
    'guia_de_integracion' => url('/#/documentacion'),
    'crear_cuenta' => url('/#/signup'),
    'autenticacion' => 'Header "Authorization: Bearer <api_key>" -- generala en el dashboard (Empresa > API keys)',
]));

// Signup/login del dashboard: credenciales -> token de empresa (ver AuthController).
Route::post('/signup', [AuthController::class, 'signup']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

// Endpoints privados agente -> plataforma (seccion 6.1). No son parte de la
// API publica: se autentican con el token propio de la instalacion, no con
// un API key de empresa.
Route::post('/agente/registrar', [AgenteIngestaController::class, 'registrar']);

Route::middleware('agente.auth')->group(function () {
    Route::post('/agente/heartbeat', [AgenteIngestaController::class, 'heartbeat']);
    Route::post('/agente/eventos', [AgenteIngestaController::class, 'eventos']);
});

// API publica v1 (seccion 6), autenticada con Sanctum token personal por empresa.
Route::prefix('v1')->middleware(['auth:sanctum', 'empresa.activa'])->group(function () {
    Route::get('/agentes', [AgenteController::class, 'index']);
    Route::get('/agentes/{id}', [AgenteController::class, 'show']);
    Route::get('/agentes/{id}/impresoras', [AgenteController::class, 'impresoras']);
    Route::post('/agentes/{agenteId}/impresoras/{impresoraId}/prueba', [PruebaImpresionController::class, 'store']);

    Route::get('/trabajos', [TrabajoController::class, 'index']);
    Route::get('/trabajos/{id}', [TrabajoController::class, 'show']);

    Route::get('/webhooks', [WebhookController::class, 'index']);
    Route::post('/webhooks', [WebhookController::class, 'store']);
    Route::delete('/webhooks/{id}', [WebhookController::class, 'destroy']);
    Route::get('/webhooks/{id}/entregas', [WebhookController::class, 'entregas']);

    Route::get('/estadisticas/resumen', [EstadisticaController::class, 'resumen']);
    Route::get('/estadisticas/agente/{id}', [EstadisticaController::class, 'agente']);

    Route::get('/empresa', [EmpresaController::class, 'show']);

    Route::get('/api-keys', [ApiKeyController::class, 'index']);
    Route::post('/api-keys', [ApiKeyController::class, 'store']);
    Route::delete('/api-keys/{id}', [ApiKeyController::class, 'destroy']);
});

// Panel de super admin: cruza el limite de tenant a proposito (ver
// EmpresaAdminController). Requiere la ability 'super-admin' en el token,
// no solo estar autenticado.
Route::prefix('v1/admin')->middleware(['auth:sanctum', 'empresa.activa', 'super.admin'])->group(function () {
    Route::get('/resumen', [ResumenAdminController::class, 'show']);

    Route::get('/empresas', [EmpresaAdminController::class, 'index']);
    Route::post('/empresas', [EmpresaAdminController::class, 'store']);
    Route::get('/empresas/{id}', [EmpresaAdminController::class, 'show']);
    Route::patch('/empresas/{id}', [EmpresaAdminController::class, 'update']);

    Route::post('/empresas/{empresaId}/api-keys', [ApiKeyAdminController::class, 'store']);
    Route::delete('/empresas/{empresaId}/api-keys/{id}', [ApiKeyAdminController::class, 'destroy']);
});
