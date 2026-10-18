<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Historial de cotizaciones del oro (USD por onza troy), por quincena.
 *
 * Es solo para alimentar el formulario con una sugerencia y para reportes:
 * cada liquidacion guarda una COPIA del valor usado en
 * liquidaciones.cotizacion_usd_oz_t, de modo que un cambio posterior en esta
 * tabla no reescribe el pasado.
 *
 * `quincena` es 1..6 segun la seccion 3.3. No se agrega CHECK para no
 * fijar un rango que el negocio todavia no confirmo (0.5 abierto).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cotizaciones_quincenales', function (Blueprint $table) {
            $table->id();

            $table->smallInteger('ejercicio');
            $table->smallInteger('quincena');
            $table->date('fecha_inicio');
            $table->date('fecha_fin');

            $table->decimal('valor_usd_oz_t', 12, 4);
            $table->string('fuente', 100)->nullable();

            $table->timestampsTz();

            $table->unique(['ejercicio', 'quincena']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cotizaciones_quincenales');
    }
};
