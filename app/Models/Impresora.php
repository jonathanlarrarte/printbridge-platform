<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * No usa BelongsToTenant: esta tabla no tiene empresa_id propio, solo
 * agente_id. El aislamiento por tenant se hace atravesando la relación con
 * Agente (que sí tiene empresa_id) — ver App\Models\Concerns\BelongsToTenant.
 */
class Impresora extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'agente_id', 'alias', 'tipo', 'protocolo', 'nombre_sistema',
        'ip', 'puerto', 'estado_heartbeat', 'actualizado_en',
    ];

    protected $casts = [
        'actualizado_en' => 'datetime',
    ];

    public function agente(): BelongsTo
    {
        return $this->belongsTo(Agente::class);
    }

    public function trabajos(): HasMany
    {
        return $this->hasMany(TrabajoImpresion::class);
    }
}
