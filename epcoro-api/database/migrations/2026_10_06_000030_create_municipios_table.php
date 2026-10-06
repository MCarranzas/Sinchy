<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Municipio (tercer nivel). `codigo` es el que se copia a
 * liquidaciones.codigo_municipio cuando se registra una venta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('municipios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provincia_id')->constrained('provincias');
            $table->string('codigo', 10);
            $table->string('nombre', 100);

            $table->timestampsTz();

            $table->unique(['provincia_id', 'codigo']);
            $table->index('provincia_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('municipios');
    }
};
