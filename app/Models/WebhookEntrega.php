<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * No usa BelongsToTenant: solo tiene webhook_id/evento_id, no empresa_id
 * propio. Se escopa por empresa atravesando WebhookConfigurado.
 */
class WebhookEntrega extends Model
{
    protected $table = 'webhook_entregas';

    public $timestamps = false;

    protected $fillable = ['webhook_id', 'evento_id', 'intento', 'status_http', 'respuesta_resumen', 'entregado_en'];

    protected $casts = [
        'entregado_en' => 'datetime',
    ];

    public function webhook(): BelongsTo
    {
        return $this->belongsTo(WebhookConfigurado::class, 'webhook_id');
    }

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Evento::class, 'evento_id');
    }
}
