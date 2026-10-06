<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla de usuarios propia de EPCORO.
     *
     * Reemplaza la de Laravel por dos razones:
     *  - el login pide "usuario", no correo, asi que la clave de acceso es
     *    `username` y el correo queda opcional;
     *  - hacen falta columnas de control de acceso (activo, intentos_fallidos,
     *    bloqueado_hasta, debe_cambiar_password) que Laravel no trae.
     *
     * No hay `rol_id`: el rol vive en `model_has_roles`, administrada por Spatie
     * Permission. Ver seccion 3.1 de la FASE 3 en instrucciones.txt.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            $table->string('username', 50)->unique();
            $table->string('email', 150)->nullable()->unique();
            $table->string('nombre_completo', 150);
            $table->string('password');

            $table->boolean('activo')->default(true);

            // Control de intentos fallidos. `bloqueado_hasta` con timestamptz y
            // no un booleto para poder bloquear N minutos y que expire solo.
            $table->smallInteger('intentos_fallidos')->default(0);
            $table->timestampTz('bloqueado_hasta')->nullable();
            $table->timestampTz('ultimo_acceso')->nullable();

            // Obliga a cambiar la clave inicial en el proximo login.
            $table->boolean('debe_cambiar_password')->default(false);

            $table->rememberToken();
            $table->timestampsTz();

            // El listado de usuarios se filtra por activo y ordena por nombre.
            $table->index('activo');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
