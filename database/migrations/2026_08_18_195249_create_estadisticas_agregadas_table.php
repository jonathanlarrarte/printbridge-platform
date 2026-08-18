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
        Schema::create('estadisticas_agregadas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            // null = snapshot de toda la empresa; con valor = snapshot de un agente puntual.
            $table->foreignId('agente_id')->nullable()->constrained('agentes')->cascadeOnDelete();
            $table->jsonb('datos');
            $table->timestamp('calculado_en')->useCurrent();

            $table->unique(['empresa_id', 'agente_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('estadisticas_agregadas');
    }
};
