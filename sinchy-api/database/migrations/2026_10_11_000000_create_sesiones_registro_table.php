<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registro permanente de sesiones (bitacora de accesos).
 *
 * La tabla `sessions` de Laravel es operativa: se purga por inactividad, se
 * borra al cerrar sesion y `last_activity` cambia en cada peticion, asi que no
 * sirve como constancia de cuando entro alguien. Esta tabla es la bitacora:
 * una fila por cada login, con la fecha y hora de ingreso y la IP del equipo
 * cliente, y NO se borra nunca.
 *
 * `fecha_salida` queda en NULL mientras la sesion este abierta y se completa
 * al cerrarla (logout, o cambio de contrasena que invalida los demas equipos).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sesiones_registro', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');

            // Snapshot: si mañana renombran al usuario, el registro sigue
            // mostrando quien entro. Mismo criterio que el historial de estados.
            $table->string('usuario_nombre', 150);

            // Id de la sesion de Laravel, para poder cerrar la fila despues.
            // No es FK a proposito: `sessions` se purga y la fila desaparece,
            // pero el registro debe quedar.
            $table->string('session_id', 255)->nullable()->index();

            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->timestampTz('fecha_ingreso')->useCurrent();
            $table->timestampTz('fecha_salida')->nullable();

            // El listado de accesos se ordena y filtra por fecha de ingreso.
            $table->index('fecha_ingreso');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sesiones_registro');
    }
};
