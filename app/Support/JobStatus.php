<?php

namespace App\Support;

/**
 * Traduccion en el borde de la API publica (decision explicita: internals
 * en espanol, superficie publica en ingles -- ver seccion de integracion
 * POS del doc). trabajos_impresion.estado sigue en espanol en la base y en
 * toda la logica interna (ProcesarEventoAgente, CalcularEstadisticas) para
 * no tocar decenas de call sites por un cambio que es puramente de cara
 * afuera; esta clase es el UNICO lugar que sabe convertir en cada
 * direccion, usada por TrabajoResource (DB -> API) y TrabajoController
 * (API -> DB, para el filtro ?status=).
 */
class JobStatus
{
    private const MAPA = [
        'pendiente' => 'pending',
        'en_cola' => 'queued',
        'imprimiendo' => 'printing',
        'impreso' => 'printed',
        'fallo_definitivo' => 'failed',
    ];

    public static function toApi(?string $estadoInterno): ?string
    {
        if ($estadoInterno === null) {
            return null;
        }

        return self::MAPA[$estadoInterno] ?? $estadoInterno;
    }

    public static function toInternal(?string $estadoApi): ?string
    {
        if ($estadoApi === null) {
            return null;
        }

        $invertido = array_flip(self::MAPA);

        return $invertido[$estadoApi] ?? $estadoApi;
    }

    /** @return string[] valores validos que la API acepta (para reglas de validacion) */
    public static function valoresApi(): array
    {
        return array_values(self::MAPA);
    }
}
