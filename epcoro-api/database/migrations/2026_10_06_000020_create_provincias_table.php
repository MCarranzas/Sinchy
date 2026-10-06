<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Provincia (segundo nivel). Un municipio no puede existir sin provincia, y
 * la provincia no existe sin su departamento: por eso las FKs son NOT NULL y
 * no se permite el borrado en cascada desde arriba.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provincias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('departamento_id')->constrained('departamentos');
            $table->string('codigo', 10);
            $table->string('nombre', 100);

            $table->timestampsTz();

            // El codigo solo es unico dentro de su departamento: dos
            // provincias distintas pueden repetirlo entre si.
            $table->unique(['departamento_id', 'codigo']);
            $table->index('departamento_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provincias');
    }
};
