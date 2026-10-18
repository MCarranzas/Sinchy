<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Correlativos de numeracion (seccion 4.4).
 *
 * Un contador por (tipo, ejercicio) para asignar el proximo numero sin que
 * dos peticiones simultaneas se repitan el mismo. Se resuelve con
 * `SELECT ... FOR UPDATE` sobre esta fila dentro de la transaccion que crea
 * la liquidacion; la fila es la garantia, no la memoria del proceso.
 *
 * `ultimo` es el ultimo correlativo ENTREGADO, no el siguiente: el
 * numerador lo incrementa antes de usarlo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('correlativos', function (Blueprint $table) {
            $table->string('tipo', 30);
            $table->smallInteger('ejercicio');
            $table->unsignedBigInteger('ultimo')->default(0);

            $table->timestampsTz();

            $table->primary(['tipo', 'ejercicio']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('correlativos');
    }
};
