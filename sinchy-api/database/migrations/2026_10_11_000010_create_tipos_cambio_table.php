<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tipo de cambio oficial Bs/$, uno por dia.
 *
 * El tipo de cambio deja de capturarse por liquidacion y pasa a ser un dato
 * diario: se registra una vez por fecha y todas las liquidaciones de ese dia
 * toman el mismo valor. La liquidacion guarda igual una COPIA en su columna
 * tipo_cambio, de modo que corregir un valor aqui no reescribe el pasado.
 *
 * `fecha` es UNIQUE porque solo puede haber un tipo de cambio por dia; ese
 * mismo indice sirve para resolver el valor por fecha.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipos_cambio', function (Blueprint $table) {
            $table->id();
            $table->date('fecha')->unique();
            $table->decimal('valor', 12, 4);
            $table->string('fuente', 100)->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tipos_cambio');
    }
};
