<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // GET /v1/jobs filtra por estado y por rango de fechas (from/to) sin
        // ningun indice que los cubra -- bien mientras la tabla es chica,
        // pero se vuelve un full scan en cuanto una empresa acumula miles de
        // trabajos. agente_id/impresora_id ya estan indexados (FK), por eso
        // no se repiten aca.
        Schema::table('trabajos_impresion', function (Blueprint $table) {
            $table->index('estado');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trabajos_impresion', function (Blueprint $table) {
            $table->dropIndex(['estado']);
            $table->dropIndex(['created_at']);
        });
    }
};
