<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El comprador pasa de apuntar a `personas` a apuntar a su propia tabla
     * `compradores`. Los tres snapshots (razon_social, nim, nit) se conservan:
     * el PDF historico sigue imprimiendo lo que se capturo ese dia.
     *
     * El backfill de `comprador_id` para las liquidaciones existentes lo hace
     * CompradoresSeeder (que corre despues de las migraciones): aqui no hay
     * datos todavia si la base es nueva.
     *
     * La vista `v_liquidaciones_detalle` hace `SELECT l.*`: PostgreSQL la
     * tiene que ver sin la columna que vamos a eliminar, asi que se recrea en
     * la misma transaccion (abajo). Su definicion vive en la migracion
     * 2026_10_06_000150; aqui solo se repite para dejarla igual.
     */
    public function up(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_liquidaciones_detalle');

        Schema::table('liquidaciones', function (Blueprint $table) {
            $table->foreignId('comprador_id')->nullable()->after('cotizacion_usd_oz_t')->constrained('compradores');
        });

        Schema::table('liquidaciones', function (Blueprint $table) {
            $table->dropForeign(['comprador_persona_id']);
            $table->dropColumn('comprador_persona_id');
        });

        $this->recrearVista();
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_liquidaciones_detalle');

        Schema::table('liquidaciones', function (Blueprint $table) {
            $table->foreignId('comprador_persona_id')->nullable()->constrained('personas');
        });

        Schema::table('liquidaciones', function (Blueprint $table) {
            $table->dropForeign(['comprador_id']);
            $table->dropColumn('comprador_id');
        });

        $this->recrearVista();
    }

    private function recrearVista(): void
    {
        DB::statement(<<<'SQL'
            CREATE VIEW v_liquidaciones_detalle AS
            SELECT
                l.*,
                u.nombre_completo AS registrado_por,
                u.username        AS registrado_username,
                (SELECT r.name
                   FROM roles r
                   JOIN model_has_roles mhr ON mhr.role_id = r.id
                  WHERE mhr.model_type = 'App\Models\User'
                    AND mhr.model_id = l.usuario_registro_id
                  LIMIT 1)        AS rol_registro
            FROM liquidaciones l
            LEFT JOIN users u ON u.id = l.usuario_registro_id
        SQL);
    }
};
