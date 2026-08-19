<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;

#[Group('Company')]
class EmpresaController extends Controller
{
    /**
     * Get your company profile.
     *
     * The dashboard mainly uses this for `code`, which is what's needed to
     * register a new agent (`POST /agent/register`).
     */
    public function show(Request $request)
    {
        $empresa = $request->user();

        return response()->json(['data' => [
            'id' => $empresa->id,
            'name' => $empresa->nombre,
            /** Your client code -- what an agent needs to self-register (`POST /agent/register`). Shared by every agent across every branch of this company. */
            'code' => $empresa->codigo,
            'plan' => $empresa->plan,
            'active' => $empresa->activo,
            'created_at' => $empresa->created_at,
        ]]);
    }
}
