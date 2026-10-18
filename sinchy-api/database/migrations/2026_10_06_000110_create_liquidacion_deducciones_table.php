<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Deducciones extensibles de una liquidacion (seccion 3.7).
 *
 * Reemplazan las columnas H (ALIQUOTA/OTROS) e I (DESCUENTO) del Excel, que
 * hoy estan vacias. Se crean desde el principio aunque hoy solo se use el
 * aporte del 1.8 %, porque agregar una tabla despues obliga a migrar
 * liquidaciones ya aprobadas.
 *
 * `alicuota` es NULL cuando el renglon no es porcentual (un descuento fijo).
 * `base_monto` es el monto sobre el que se aplico: permite reproducir el
 * calculo de la fila sin leer ninguna otra tabla.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('liquidacion_deducciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('liquidacion_id')->constrained('liquidaciones')->cascadeOnDelete();

            $table->string('concepto', 100);
            $table->string('tipo', 20); // alicuota | descuento

            $table->decimal('alicuota', 7, 6)->nullable();
            $table->decimal('base_monto', 16, 2);
            $table->decimal('monto', 16, 2);

            $table->boolean('obligatorio')->default(false);
            $table->smallInteger('orden')->default(0);

            $table->timestampsTz();

            $table->index(['liquidacion_id', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('liquidacion_deducciones');
    }
};
