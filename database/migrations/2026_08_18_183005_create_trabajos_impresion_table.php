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
        Schema::create('trabajos_impresion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agente_id')->constrained('agentes')->cascadeOnDelete();
            $table->foreignId('impresora_id')->nullable()->constrained('impresoras')->nullOnDelete();
            $table->string('job_id_externo');
            $table->string('target');
            $table->string('format')->nullable();
            $table->string('estado')->default('pendiente');
            $table->unsignedInteger('intentos')->default(0);
            $table->text('error_mensaje')->nullable();
            $table->unsignedInteger('duracion_ms')->nullable();
            $table->timestamps();

            $table->unique(['agente_id', 'job_id_externo']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trabajos_impresion');
    }
};
