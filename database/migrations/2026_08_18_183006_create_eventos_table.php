<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('eventos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('agente_id')->constrained('agentes')->cascadeOnDelete();
            $table->foreignId('trabajo_id')->nullable()->constrained('trabajos_impresion')->cascadeOnDelete();
            $table->string('tipo_evento');
            $table->jsonb('payload');
            $table->timestamp('creado_en')->useCurrent();
        });

        // Idempotencia: un mismo trabajo no puede reportar el mismo tipo_evento
        // dos veces (un reintento de red del agente en /agente/eventos no debe
        // duplicar filas). Solo aplica cuando ya se resolvió el trabajo_id.
        DB::statement(
            'CREATE UNIQUE INDEX eventos_trabajo_tipo_unique ON eventos (trabajo_id, tipo_evento) WHERE trabajo_id IS NOT NULL'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('eventos');
    }
};
