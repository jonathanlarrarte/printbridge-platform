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
        Schema::create('impresoras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agente_id')->constrained('agentes')->cascadeOnDelete();
            $table->string('alias');
            $table->string('tipo')->nullable();
            $table->string('protocolo')->nullable();
            $table->string('nombre_sistema')->nullable();
            $table->string('ip')->nullable();
            $table->integer('puerto')->nullable();
            $table->string('estado_heartbeat')->default('offline');
            $table->timestamp('actualizado_en')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['agente_id', 'alias']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('impresoras');
    }
};
