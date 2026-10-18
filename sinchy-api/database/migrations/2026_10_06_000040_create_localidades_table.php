<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Localidad (cuarto nivel): caserio, comunidad o centro minero dentro de un
 * municipio. Es el ultimo eslabon del cascado Dep > Pro > Mun > Loc.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('localidades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('municipio_id')->constrained('municipios');
            $table->string('codigo', 10);
            $table->string('nombre', 100);

            $table->timestampsTz();

            $table->unique(['municipio_id', 'codigo']);
            $table->index('municipio_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('localidades');
    }
};
