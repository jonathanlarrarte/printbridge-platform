<?php

namespace App\Support;

/**
 * Catalogo de tipo_evento (seccion 8.1 del doc), en ingles porque viaja
 * por la API publica: se valida en POST /agent/events, se filtra en
 * suscripciones de webhook, y es lo que un integrador ve en el payload
 * entregado. Fuente unica para no repetir la lista en 4 archivos distintos.
 */
class EventType
{
    public const JOB_CREATED = 'job.created';

    public const JOB_PRINTING = 'job.printing';

    public const JOB_PRINTED = 'job.printed';

    public const JOB_FAILED = 'job.failed';

    public const AGENT_ONLINE = 'agent.online';

    public const AGENT_OFFLINE = 'agent.offline';

    /** Los que puede reportar el agente directamente via POST /agent/events. */
    public const JOB_EVENTS = [
        self::JOB_CREATED,
        self::JOB_PRINTING,
        self::JOB_PRINTED,
        self::JOB_FAILED,
    ];

    /** Catalogo completo disponible para suscripcion de webhooks. */
    public const ALL = [
        self::AGENT_ONLINE,
        self::AGENT_OFFLINE,
        self::JOB_CREATED,
        self::JOB_PRINTING,
        self::JOB_PRINTED,
        self::JOB_FAILED,
    ];
}
