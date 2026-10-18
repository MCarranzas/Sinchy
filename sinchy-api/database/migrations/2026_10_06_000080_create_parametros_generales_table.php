<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Parametros del sistema: pares clave/valor con tipo y descripcion.
 *
 * La PK es `clave`, no un id: la tabla se lee siempre por nombre
 * (ParametroRepository) y asi no hace falta un indice aparte.
 *
 * Por la decision D1 (docs/reglas-negocio.md) AQUI NO EXISTE
 * ALICUOTA_APORTE_SOLIDARIO: la unica alícuota es ALICUOTA_APORTE = 0.018.
 * Tampoco existe la columna aplica_aporte_solidario en liquidaciones.
 *
 * Contenido inicial (seccion 3.3):
 *   TIPO_CAMBIO_DEFECTO, GRAMOS_POR_ONZA_TROY, ALICUOTA_APORTE,
 *   NOMBRE_COMPRADOR, NIM_COMPRADOR, NIT_COMPRADOR
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parametros_generales', function (Blueprint $table) {
            $table->string('clave', 50)->primary();
            $table->string('valor', 255);
            $table->string('tipo', 20); // decimal | entero | texto | fecha
            $table->text('descripcion')->nullable();

            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parametros_generales');
    }
};
