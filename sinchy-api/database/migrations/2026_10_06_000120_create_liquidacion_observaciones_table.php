<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Observaciones del flujo (seccion 3.6): comentarios, solicitudes de
 * correccion y observaciones del revisor. Es la conversacion alrededor de la
 * liquidacion; el motivo formal de rechazo va aparte, en
 * liquidaciones.motivo_rechazo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('liquidacion_observaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('liquidacion_id')->constrained('liquidaciones')->cascadeOnDelete();
            $table->foreignId('usuario_id')->constrained('users');

            $table->string('tipo', 20); // comentario | solicitud | observacion
            $table->text('texto');

            // Solo created_at con valor por omision, igual que en el
            // historial: el modelo tiene $timestamps = false porque una
            // observacion se escribe una vez y no se edita. Sin el
            // useCurrent(), created_at quedaria en NULL y el indice
            // (liquidacion_id, created_at) dejaria de servir para ordenar.
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->nullable();

            $table->index(['liquidacion_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('liquidacion_observaciones');
    }
};
