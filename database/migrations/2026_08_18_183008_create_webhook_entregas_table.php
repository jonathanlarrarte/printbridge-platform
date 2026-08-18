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
        Schema::create('webhook_entregas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webhook_id')->constrained('webhooks_configurados')->cascadeOnDelete();
            $table->foreignId('evento_id')->constrained('eventos')->cascadeOnDelete();
            $table->unsignedInteger('intento');
            $table->unsignedSmallInteger('status_http')->nullable();
            $table->text('respuesta_resumen')->nullable();
            $table->timestamp('entregado_en')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_entregas');
    }
};
