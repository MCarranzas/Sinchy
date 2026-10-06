<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Departamento (primer nivel de la jerarquia Dep > Pro > Mun > Loc).
 *
 * La jerarquia completa existe para dos motivos: habilita los select en
 * cascada del frontend (seccion 6.1 de instrucciones.txt) y evita duplicar
 * nombres de municipios repetidos en los 9 departamentos. Ver seccion 3.2.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departamentos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 10)->unique();
            $table->string('nombre', 100)->unique();

            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departamentos');
    }
};
