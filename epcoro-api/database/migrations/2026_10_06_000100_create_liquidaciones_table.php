<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla principal: liquidaciones. Diseno DENSO, una columna por campo del
 * Excel (seccion 3.4 de instrucciones.txt).
 *
 * Dos reglas que atraviesan toda la tabla:
 *
 *  1. SNAPSHOT. Ademas de la FK se guarda el texto (nombre del municipio,
 *     del area minera, del vendedor...). La FK da integridad; el snapshot
 *     congela lo que se imprimio ese dia. Si mañana corrigen el nombre de un
 *     municipio, los PDF historicos no cambian.
 *
 *  2. CALCULADOS PERSISTIDOS. Los montos se guardan y los reportes NO los
 *     recalculan: el Excel oficial es la referencia y un recalculo posterior
 *     daria otro centavo (regla T3, seccion 4.3).
 *
 * El ENUM se crea con DB::statement porque `$table->enum()` en PostgreSQL
 * genera varchar + CHECK y el proyecto quiere el tipo nativo (4.2): el motor
 * invalida valores invalidos aunque alguien se salte la aplicacion.
 * `DROP TYPE IF EXISTS` va primero porque `php artisan migrate:fresh` borra
 * las tablas pero NO los tipos; sin ese drop la segunda corrida fallaria.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP TYPE IF EXISTS liquidacion_estado');
        DB::statement(<<<'SQL'
            CREATE TYPE liquidacion_estado AS ENUM (
                'creado', 'en_revision', 'revisado', 'aprobado',
                'rechazado', 'en_correccion', 'anulado', 'eliminado'
            )
        SQL);

        Schema::create('liquidaciones', function (Blueprint $table) {
            // ------------------------------------------------ IDENTIFICACION
            $table->id();
            $table->string('numero_documento', 50)->unique();
            $table->string('codigo_formulario', 60)->nullable();
            $table->unsignedBigInteger('correlativo')->nullable()->unique();
            $table->smallInteger('ejercicio');
            $table->date('fecha_transaccion');
            $table->timestampTz('fecha_registro')->useCurrent();
            $table->foreignId('usuario_registro_id')->constrained('users');

            // ---------------------------------------------------------------- ESTADO
            // La columna se crea como varchar y a continuacion se cambia al
            // tipo nativo: el Blueprint no sabe declarar tipos de usuario.
            $table->string('estado', 20)->default('creado');
            $table->timestampTz('fecha_revision')->nullable();
            $table->foreignId('usuario_revision_id')->nullable()->constrained('users');
            $table->timestampTz('fecha_aprobacion')->nullable();
            $table->foreignId('usuario_aprobacion_id')->nullable()->constrained('users');
            $table->timestampTz('fecha_rechazo')->nullable();
            $table->foreignId('usuario_rechazo_id')->nullable()->constrained('users');
            $table->text('motivo_rechazo')->nullable();
            $table->timestampTz('fecha_anulacion')->nullable();
            $table->foreignId('usuario_anulacion_id')->nullable()->constrained('users');
            $table->text('motivo_anulacion')->nullable();
            $table->text('observaciones')->nullable();

            // ---------------------------------------- 1. INFORMACION DE TRANSACCION
            $table->decimal('tipo_cambio', 12, 4);
            $table->foreignId('cotizacion_id')->nullable()->constrained('cotizaciones_quincenales');
            $table->decimal('cotizacion_usd_oz_t', 12, 4);

            // ------------------------------------------------------- 1.1 COMPRADOR
            $table->foreignId('comprador_persona_id')->nullable()->constrained('personas');
            $table->string('comprador_razon_social', 200);
            $table->string('comprador_nim', 30)->nullable();
            $table->string('comprador_nit', 30)->nullable();

            // --------------------------------------------- APORTES (celdas C19-C20)
            // D1: UNA sola deduccion, 1.8 %, gobernada por este interruptor.
            // No existe aplica_aporte_solidario ni aporte_solidario_bs.
            $table->boolean('aplica_caja_salud')->default(false);

            // ------------------------------------------------------- 1.2 VENDEDOR
            $table->foreignId('vendedor_persona_id')->constrained('personas');
            $table->string('vendedor_nombre', 200);
            $table->string('vendedor_ci', 30);

            // ----------------------------------- 1.3 PROCEDENCIA DEL MINERAL (snapshot)
            $table->foreignId('area_minera_id')->constrained('areas_mineras');
            $table->string('nombre_area_minera', 200);
            $table->string('titular_derecho_minero', 200);
            $table->string('codigo_ajam_patente', 50)->nullable();
            $table->foreignId('departamento_id')->constrained('departamentos');
            $table->string('departamento_nombre', 100);
            $table->foreignId('provincia_id')->constrained('provincias');
            $table->string('provincia_nombre', 100);
            $table->foreignId('municipio_id')->constrained('municipios');
            $table->string('municipio_nombre', 100);
            $table->string('codigo_municipio', 20)->nullable();
            $table->foreignId('localidad_id')->constrained('localidades');
            $table->string('localidad_nombre', 100);

            // -------------------------------------------- 2. INFORMACION TECNICA
            $table->foreignId('presentacion_mineral_id')->constrained('presentaciones_mineral');
            $table->string('presentacion_nombre', 100);
            $table->decimal('peso_gramos', 14, 2);
            // 7 digitos en total, 6 despues de la coma: 0.930200 entra, 93.02 no.
            $table->decimal('ley_pagable', 7, 6);

            // ------------------------------------------------ CALCULADOS (regla T3)
            $table->decimal('gramos_por_onza_troy', 12, 6);
            $table->decimal('peso_fino_gramos', 14, 4);
            $table->decimal('valor_usd_gramo', 12, 6);
            $table->decimal('valor_bruto_bs', 16, 2);
            $table->decimal('aporte_caja_salud_bs', 16, 2)->default(0);
            $table->decimal('total_deducciones_bs', 16, 2)->default(0);
            $table->decimal('valor_neto_bs', 16, 2);

            $table->timestampsTz();

            // ------------------------------------------------------------ Indices
            $table->index('estado');
            $table->index(['municipio_id', 'fecha_transaccion']);
            $table->index(['area_minera_id', 'fecha_transaccion']);
        });

        // Estado en formato nativo. USING castea lo que se creo como varchar.
        // El default se saca y se vuelve a poner porque PostgreSQL no castea
        // solo el valor por omision al cambiar el tipo de la columna.
        DB::statement('ALTER TABLE liquidaciones ALTER COLUMN estado DROP DEFAULT');
        DB::statement('ALTER TABLE liquidaciones ALTER COLUMN estado TYPE liquidacion_estado USING estado::liquidacion_estado');
        DB::statement("ALTER TABLE liquidaciones ALTER COLUMN estado SET DEFAULT 'creado'::liquidacion_estado");

        // CHECKs que el Blueprint no expresa: la ley es fraccion (trampa T1) y
        // el peso no puede ser cero ni negativo.
        DB::statement('ALTER TABLE liquidaciones ADD CONSTRAINT liquidaciones_peso_positivo CHECK (peso_gramos > 0)');
        DB::statement('ALTER TABLE liquidaciones ADD CONSTRAINT liquidaciones_ley_valida CHECK (ley_pagable > 0 AND ley_pagable <= 1)');

        // Indices con orden descendente: los dos primeros alimentan las
        // listas "mas recientes primero".
        DB::statement('CREATE INDEX liquidaciones_fecha_transaccion_idx ON liquidaciones (fecha_transaccion DESC)');
        DB::statement('CREATE INDEX liquidaciones_fecha_registro_idx ON liquidaciones (fecha_registro DESC)');
        DB::statement('CREATE INDEX liquidaciones_usuario_registro_fecha_idx ON liquidaciones (usuario_registro_id, fecha_registro DESC)');
    }

    public function down(): void
    {
        Schema::dropIfExists('liquidaciones');

        DB::statement('DROP TYPE IF EXISTS liquidacion_estado');
    }
};
