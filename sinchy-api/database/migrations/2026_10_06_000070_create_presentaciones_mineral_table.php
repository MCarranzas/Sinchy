<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Presentacion del mineral: 'ORO FUNDIDO', 'ORO EN GRANO', ...
 *
 * Catalogo corto y estables; el nombre se copia igual a la liquidacion como
 * snapshot porque asi sale impreso en el PDF.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presentaciones_mineral', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100)->unique();

            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presentaciones_mineral');
    }
};
