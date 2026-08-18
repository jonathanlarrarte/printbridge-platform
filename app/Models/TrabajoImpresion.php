<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * No usa BelongsToTenant: solo tiene agente_id/impresora_id, no empresa_id
 * propio. Se escopa por empresa atravesando Agente.
 */
class TrabajoImpresion extends Model
{
    protected $table = 'trabajos_impresion';

    protected $fillable = [
        'agente_id', 'impresora_id', 'job_id_externo', 'target', 'format',
        'estado', 'intentos', 'error_mensaje', 'duracion_ms',
    ];

    public function agente(): BelongsTo
    {
        return $this->belongsTo(Agente::class);
    }

    public function impresora(): BelongsTo
    {
        return $this->belongsTo(Impresora::class);
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(Evento::class, 'trabajo_id');
    }
}
