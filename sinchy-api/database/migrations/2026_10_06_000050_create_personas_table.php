<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Persona fisica o juridica. Se usa para VENDEDOR y para COMPRADOR
 * (seccion 3.2 de instrucciones.txt).
 *
 * `ci` y `nit` se guardan como texto y NUNCA como numero: son identificadores,
 * no cantidades. Validarlos como numeric provocaria notacion cientifica y
 * perdida de ceros a la izquierda (correccion C5).
 *
 * `nombre_completo` es el campo que se muestra en listados y reportes; se
 * mantiene como columna porque los reportes no deben concatenar en cada fila.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personas', function (Blueprint $table) {
            $table->id();

            $table->string('nombres', 100);
            $table->string('apellidos', 100);
            $table->string('nombre_completo', 200);

            $table->string('ci', 20)->nullable();
            $table->string('nit', 20)->nullable();
            $table->string('tipo_documento', 20)->default('CI');

            $table->string('email', 150)->nullable();
            $table->string('telefono', 30)->nullable();
            $table->string('direccion', 200)->nullable();

            $table->boolean('activo')->default(true);

            $table->timestampsTz();

            $table->index('ci');
            $table->index('nit');
            $table->index(['apellidos', 'nombres']);
            $table->index('activo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personas');
    }
};
