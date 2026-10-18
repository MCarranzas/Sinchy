<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega dos columnas a las tablas de Spatie Permission.
 *
 * La seccion 3.1 de instrucciones.txt define permisos como
 * (id, nombre, slug, modulo) y roles como (id, nombre, slug, descripcion), y
 * Spatie solo trae id, name, guard_name y timestamps.
 *
 * Se agregan en vez de crear tablas propias para no duplicar el RBAC. `module`
 * sirve para agrupar permisos en la pantalla de administracion; `descripcion`
 * es el texto que se le muestra al usuario cuando se le asigna un rol.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->string('module', 50)->default('general')->after('name');
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->string('descripcion', 200)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->dropColumn('module');
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('descripcion');
        });
    }
};
