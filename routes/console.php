<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Seccion 9 del doc: metricas pre-calculadas, nunca en tiempo real.
Schedule::command('app:calcular-estadisticas')->everyFiveMinutes();

// El agente manda heartbeat cada 15-30s; sin esto un agente caido se queda
// "online" para siempre en la plataforma.
Schedule::command('app:detectar-agentes-offline')->everyMinute();
