<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WebhookConfigurado extends Model
{
    use BelongsToTenant;

    protected $table = 'webhooks_configurados';

    public $timestamps = false;

    protected $fillable = ['empresa_id', 'url', 'eventos_suscritos', 'secreto', 'activo', 'creado_en'];

    protected $casts = [
        'eventos_suscritos' => 'array',
        'activo' => 'boolean',
        'creado_en' => 'datetime',
    ];

    protected $hidden = ['secreto'];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function entregas(): HasMany
    {
        return $this->hasMany(WebhookEntrega::class, 'webhook_id');
    }
}
