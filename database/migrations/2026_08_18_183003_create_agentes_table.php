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
        Schema::create('agentes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('instalacion_id')->unique();
            $table->string('nombre_descriptivo')->nullable();
            $table->string('token_hash');
            $table->string('estado')->default('offline');
            $table->timestamp('ultimo_heartbeat')->nullable();
            $table->string('version_agente')->nullable();
            $table->timestamp('creado_en')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agentes');
    }
};
