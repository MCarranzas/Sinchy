<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Historial de estados: la bitacora (seccion 3.5). Sin esto no se puede
 * reconstruir quien hizo que ni cuando, y en un sistema de aprobacion de
 * dinero eso es requisito.
 *
 * La escritura de la fila va DENTRO de la misma transaccion que el cambio de
 * estado: si el historial falla, la transicion tampoco se aplica.
 *
 * `estado_anterior` es NULL solo en la transicion inicial (NULL -> 'creado').
 * `usuario_nombre` es snapshot: si mañana renombran al usuario, el historial
 * sigue mostrando quien aperto el boton.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('liquidacion_estado_historial', function (Blueprint $table) {
            $table->id();
            $table->foreignId('liquidacion_id')->constrained('liquidaciones')->cascadeOnDelete();

            // Creadas como varchar y convertidas despues al tipo nativo, igual
            // que en liquidaciones.
            $table->string('estado_anterior', 20)->nullable();
            $table->string('estado_nuevo', 20);

            $table->foreignId('usuario_id')->constrained('users');
            $table->string('usuario_nombre', 150);
            $table->text('comentario')->nullable();

            $table->timestampTz('created_at')->useCurrent();

            $table->index('estado_nuevo');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE liquidacion_estado_historial '
                .'ALTER COLUMN estado_anterior TYPE liquidacion_estado USING estado_anterior::liquidacion_estado');
            DB::statement('ALTER TABLE liquidacion_estado_historial '
                .'ALTER COLUMN estado_nuevo TYPE liquidacion_estado USING estado_nuevo::liquidacion_estado');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('liquidacion_estado_historial');
    }
};
