<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Vista de reportes (seccion 3.8): aterriza en una sola tabla lo que los
 * reportes necesitarian unir a mano en cada consulta (quien registro, con
 * que rol). Evita repetir 10 JOINs en cada reporte.
 *
 * El rol se resuelve con una subconsulta sobre model_has_roles porque los
 * roles viven en Spatie Permission y cada usuario tiene exactamente uno
 * (ver User::rol()). Se usa LEFT JOIN por si un usuario fuera dado de baja
 * del catálogo: el reporte historico no puede dejar de mostrarse.
 */
return new class extends Migration
{
    public function up(): void
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

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_liquidaciones_detalle');
    }
};
