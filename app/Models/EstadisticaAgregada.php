<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Snapshot pre-calculado (seccion 9 del doc): un job programado
 * (CalcularEstadisticas) escribe aca cada 5 min, y los endpoints de
 * estadisticas leen de esta tabla en vez de agregar en tiempo real sobre
 * eventos/trabajos_impresion.
 */
class EstadisticaAgregada extends Model
{
    use BelongsToTenant;

    protected $table = 'estadisticas_agregadas';

    public $timestamps = false;

    protected $fillable = ['empresa_id', 'agente_id', 'datos', 'calculado_en'];

    protected $casts = [
        'datos' => 'array',
        'calculado_en' => 'datetime',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function agente(): BelongsTo
    {
        return $this->belongsTo(Agente::class);
    }
}
