<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\ExcludeRouteFromDocs;

/**
 * GET /v1 -- documento de auto-descubrimiento (ver routes/api.php). Excluido
 * de /docs/api y de la referencia Docusaurus: es metadata sobre la
 * documentacion, no un endpoint que un integrador vaya a llamar en su
 * integracion.
 */
class ApiRootController extends Controller
{
    #[ExcludeRouteFromDocs]
    public function __invoke()
    {
        return response()->json([
            'platform' => config('app.name'),
            'documentation' => url('/developers'),
            'integration_guide' => url('/#/documentacion'),
            'sign_up' => url('/#/signup'),
            'authentication' => 'Header "Authorization: Bearer <api_key>" -- generate one in the dashboard (Company > API keys)',
        ]);
    }
}
