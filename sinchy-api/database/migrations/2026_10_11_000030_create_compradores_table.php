<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catalogo de compradores.
     *
     * Antes el comprador era un dato fijo que vivia solo en
     * parametros_generales (NOMBRE/NIM/NIT_COMPRADOR, celdas C11-C13). Con su
     * propia tabla el catalogo queda listo para usos posteriores (varios
     * compradores, gestion, reportes), y `liquidaciones` conserva el snapshot
     * (comprador_razon_social/nim/nit) mas la FK comprador_id.
     *
     * Los identificadores NIM/NIT van como TEXTO, nunca como numero: son
     * identificadores, no cantidades (correccion C5).
     */
    public function up(): void
    {
        Schema::create('compradores', function (Blueprint $table) {
            $table->id();
            $table->string('razon_social', 200);
            $table->string('nim', 30)->nullable();
            $table->string('nit', 30)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compradores');
    }
};
