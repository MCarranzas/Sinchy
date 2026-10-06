<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Area minera.
 *
 * Al elegirla en el formulario se autocompletan los cuatro datos de
 * procedencia (seccion 3.2), asi que las cuatro FKs son NOT NULL: si el
 * catalogo de areas mineras guardara un area sin procedencia, el formulario
 * le fallaria al tecnico en el momento de guardar la liquidacion, porque
 * liquidaciones departamento_id / provincia_id / municipio_id / localidad_id
 * son NOT NULL.
 *
 * El titular y el codigo AJAM/patente se copian a la liquidacion como
 * snapshot, igual que los nombres geograficos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('areas_mineras', function (Blueprint $table) {
            $table->id();

            $table->string('nombre_area_minera', 200);
            $table->string('titular_derecho_minero', 200);
            $table->string('codigo_ajam_patente', 50)->nullable();

            $table->foreignId('departamento_id')->constrained('departamentos');
            $table->foreignId('provincia_id')->constrained('provincias');
            $table->foreignId('municipio_id')->constrained('municipios');
            $table->foreignId('localidad_id')->constrained('localidades');

            $table->boolean('activo')->default(true);

            $table->timestampsTz();

            $table->index('nombre_area_minera');
            $table->index('activo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('areas_mineras');
    }
};
