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
        // La unicidad global de job_id_externo solo era segura porque el
        // unico emisor era la propia plataforma (UUIDs generados por ella
        // para los test-print). Ahora que el nuevo endpoint de trabajos
        // remotos deja que CADA empresa elija su propio external_job_id,
        // dos empresas distintas eligiendo el mismo valor (ej. "order-1")
        // chocarian entre si. La escopamos por agente, igual que ya hace
        // trabajos_impresion.
        Schema::table('comandos_prueba', function (Blueprint $table) {
            $table->dropUnique('comandos_prueba_job_id_externo_unique');
            $table->unique(['agente_id', 'job_id_externo']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('comandos_prueba', function (Blueprint $table) {
            $table->dropUnique(['agente_id', 'job_id_externo']);
            $table->unique('job_id_externo');
        });
    }
};
