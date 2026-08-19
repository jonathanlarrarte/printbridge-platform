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
        Schema::create('comandos_prueba', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agente_id')->constrained('agentes')->cascadeOnDelete();
            $table->foreignId('impresora_id')->constrained('impresoras')->cascadeOnDelete();
            $table->string('job_id_externo')->unique();
            $table->string('target');
            $table->string('format');
            $table->jsonb('data');
            $table->string('estado')->default('pendiente');
            $table->timestamp('creado_en')->useCurrent();
            $table->timestamp('entregado_en')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('comandos_prueba');
    }
};
