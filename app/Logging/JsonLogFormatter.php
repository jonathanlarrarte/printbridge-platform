<?php

namespace App\Logging;

use Illuminate\Log\Logger;
use Monolog\Formatter\JsonFormatter;

/**
 * Logs estructurados en JSON (seccion 11 del doc), una linea por evento,
 * para poder filtrarlos/buscarlos a medida que crece el volumen.
 */
class JsonLogFormatter
{
    public function __invoke(Logger $logger): void
    {
        foreach ($logger->getLogger()->getHandlers() as $handler) {
            $handler->setFormatter(new JsonFormatter);
        }
    }
}
