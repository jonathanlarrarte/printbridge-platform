<?php

namespace App\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\HasApiTokens;

class Empresa extends Model implements AuthenticatableContract
{
    use Authenticatable, HasApiTokens;

    protected $fillable = ['nombre', 'codigo', 'plan', 'activo'];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function usuarios(): HasMany
    {
        return $this->hasMany(Usuario::class);
    }

    public function agentes(): HasMany
    {
        return $this->hasMany(Agente::class);
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(Evento::class);
    }
}
