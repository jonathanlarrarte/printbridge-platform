<?php

namespace App\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
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

    /**
     * "mi-empresa" -> "mi-empresa-x7q2" si ya existe. Usado tanto por el
     * signup self-service como por el alta manual del super admin, para no
     * duplicar la logica de unicidad en dos lugares.
     */
    public static function generarCodigoUnico(string $nombre): string
    {
        $base = Str::slug($nombre) ?: 'empresa';
        $codigo = $base;
        $intentos = 0;

        while (static::where('codigo', $codigo)->exists()) {
            $codigo = $base.'-'.Str::lower(Str::random(4));

            if (++$intentos > 20) {
                throw new \RuntimeException('No se pudo generar un codigo de empresa unico.');
            }
        }

        return $codigo;
    }
}
