<?php

namespace App\Models\Concerns;

use App\Models\Empresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Global scope por empresa_id. Solo aplica cuando hay una Empresa autenticada
 * vía Sanctum en el request actual (API pública) — no tiene efecto para el
 * worker de colas ni para los endpoints de agente, que filtran/asignan
 * empresa_id explícitamente a través de la relación con Agente.
 */
trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('empresa', function (Builder $builder) {
            if (Auth::check() && Auth::user() instanceof Empresa) {
                $builder->where($builder->getModel()->getTable().'.empresa_id', Auth::id());
            }
        });

        static::creating(function ($model) {
            if (empty($model->empresa_id) && Auth::check() && Auth::user() instanceof Empresa) {
                $model->empresa_id = Auth::id();
            }
        });
    }
}
