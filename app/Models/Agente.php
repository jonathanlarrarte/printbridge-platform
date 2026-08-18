<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Agente extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $fillable = [
        'empresa_id', 'instalacion_id', 'nombre_descriptivo', 'token_hash',
        'estado', 'ultimo_heartbeat', 'version_agente', 'creado_en',
    ];

    protected $hidden = ['token_hash'];

    protected $casts = [
        'ultimo_heartbeat' => 'datetime',
        'creado_en' => 'datetime',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function impresoras(): HasMany
    {
        return $this->hasMany(Impresora::class);
    }

    public function trabajos(): HasMany
    {
        return $this->hasMany(TrabajoImpresion::class);
    }
}
