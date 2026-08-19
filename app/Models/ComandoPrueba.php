<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Canal de comandos plataforma -> agente (unico caso donde la plataforma
 * "empuja" algo, en el sentido logico -- en la practica sigue siendo el
 * agente el que inicia la conexion, seccion 2 del doc: el heartbeat que ya
 * manda cada 15-30s trae los comandos pendientes en la respuesta). El
 * agente los mete en su cola local con encolar() y de ahi en mas es un
 * trabajo de impresion mas: mismos reintentos, mismo reporte de estado.
 */
class ComandoPrueba extends Model
{
    protected $table = 'comandos_prueba';

    public $timestamps = false;

    protected $fillable = ['agente_id', 'impresora_id', 'job_id_externo', 'target', 'format', 'data', 'estado', 'creado_en', 'entregado_en'];

    protected $casts = [
        'data' => 'array',
        'creado_en' => 'datetime',
        'entregado_en' => 'datetime',
    ];

    public function agente(): BelongsTo
    {
        return $this->belongsTo(Agente::class);
    }

    public function impresora(): BelongsTo
    {
        return $this->belongsTo(Impresora::class);
    }
}
