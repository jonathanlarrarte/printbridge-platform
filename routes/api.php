<?php

use App\Http\Controllers\AgenteIngestaController;
use App\Http\Controllers\Api\Admin\ApiKeyAdminController;
use App\Http\Controllers\Api\Admin\EmpresaAdminController;
use App\Http\Controllers\Api\Admin\ResumenAdminController;
use App\Http\Controllers\Api\AgenteController;
use App\Http\Controllers\Api\ApiKeyController;
use App\Http\Controllers\Api\ApiRootController;
use App\Http\Controllers\Api\EmpresaController;
use App\Http\Controllers\Api\EstadisticaController;
use App\Http\Controllers\Api\PruebaImpresionController;
use App\Http\Controllers\Api\TrabajoController;
use App\Http\Controllers\Api\WebhookController;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

// Publicly surfaced API: routes and JSON stay in English (integrator-
// facing), while everything internal (controllers, models, DB columns)
// stays in Spanish -- deliberate boundary, see app/Support/JobStatus.php.

// Healthcheck publico (seccion 11 del doc de arquitectura).
Route::get('/health', fn () => response()->json(['ok' => true]));

// Documento de auto-descubrimiento: el primer lugar donde alguien pega la
// URL base de la API sin saber nada mas todavia deberia encontrar como
// seguir, sin necesitar un token.
Route::get('/v1', ApiRootController::class);

// Signup/login del dashboard: credenciales -> token de empresa (ver AuthController).
Route::post('/signup', [AuthController::class, 'signup']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

// Endpoints privados agente -> plataforma (seccion 6.1). No son parte de la
// API publica: se autentican con el token propio de la instalacion, no con
// un API key de empresa.
Route::post('/agent/register', [AgenteIngestaController::class, 'registrar']);

Route::middleware('agente.auth')->group(function () {
    Route::post('/agent/heartbeat', [AgenteIngestaController::class, 'heartbeat']);
    Route::post('/agent/events', [AgenteIngestaController::class, 'eventos']);
});

// API publica v1 (seccion 6), autenticada con Sanctum token personal por empresa.
Route::prefix('v1')->middleware(['auth:sanctum', 'empresa.activa'])->group(function () {
    Route::get('/agents', [AgenteController::class, 'index']);
    Route::get('/agents/{id}', [AgenteController::class, 'show']);
    Route::get('/agents/{id}/printers', [AgenteController::class, 'impresoras']);
    Route::delete('/agents/{id}', [AgenteController::class, 'destroy']);
    Route::post('/agents/{agenteId}/printers/{impresoraId}/test-print', [PruebaImpresionController::class, 'store']);

    Route::get('/jobs', [TrabajoController::class, 'index']);
    Route::get('/jobs/{id}', [TrabajoController::class, 'show']);

    Route::get('/webhooks', [WebhookController::class, 'index']);
    Route::post('/webhooks', [WebhookController::class, 'store']);
    Route::delete('/webhooks/{id}', [WebhookController::class, 'destroy']);
    Route::get('/webhooks/{id}/deliveries', [WebhookController::class, 'entregas']);

    Route::get('/stats/summary', [EstadisticaController::class, 'resumen']);
    Route::get('/stats/agents/{id}', [EstadisticaController::class, 'agente']);

    Route::get('/company', [EmpresaController::class, 'show']);

    Route::get('/api-keys', [ApiKeyController::class, 'index']);
    Route::post('/api-keys', [ApiKeyController::class, 'store']);
    Route::delete('/api-keys/{id}', [ApiKeyController::class, 'destroy']);
});

// Panel de super admin: cruza el limite de tenant a proposito (ver
// EmpresaAdminController). Requiere la ability 'super-admin' en el token,
// no solo estar autenticado.
Route::prefix('v1/admin')->middleware(['auth:sanctum', 'empresa.activa', 'super.admin'])->group(function () {
    Route::get('/summary', [ResumenAdminController::class, 'show']);

    Route::get('/companies', [EmpresaAdminController::class, 'index']);
    Route::post('/companies', [EmpresaAdminController::class, 'store']);
    Route::get('/companies/{id}', [EmpresaAdminController::class, 'show']);
    Route::patch('/companies/{id}', [EmpresaAdminController::class, 'update']);

    Route::post('/companies/{empresaId}/api-keys', [ApiKeyAdminController::class, 'store']);
    Route::delete('/companies/{empresaId}/api-keys/{id}', [ApiKeyAdminController::class, 'destroy']);
});
