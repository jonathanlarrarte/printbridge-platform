<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bitácora inmutable: solo INSERT, nunca UPDATE.
 */
class Evento extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $fillable = ['empresa_id', 'agente_id', 'trabajo_id', 'tipo_evento', 'payload', 'creado_en'];

    protected $casts = [
        'payload' => 'array',
        'creado_en' => 'datetime',
    ];

    public function agente(): BelongsTo
    {
        return $this->belongsTo(Agente::class);
    }

    public function trabajo(): BelongsTo
    {
        return $this->belongsTo(TrabajoImpresion::class, 'trabajo_id');
    }
}
