<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Usuario extends Model
{
    use BelongsToTenant;

    protected $fillable = ['empresa_id', 'nombre', 'email', 'rol', 'password', 'es_super_admin'];

    protected $hidden = ['password'];

    protected $casts = [
        'password' => 'hashed',
        'es_super_admin' => 'boolean',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }
}
