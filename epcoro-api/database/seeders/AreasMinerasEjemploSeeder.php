<?php

namespace Database\Seeders;

use App\Models\AreaMinera;
use App\Models\Departamento;
use App\Models\Localidad;
use App\Models\Municipio;
use App\Models\Provincia;
use Illuminate\Database\Seeder;

/**
 * Areas mineras de ejemplo (seccion 3.2 / 8.5).
 *
 * No hay catalogo administrable de areas, y un area no puede quedarse sin
 * procedencia (las cuatro FKs son NOT NULL): cada fila resuelve su cascada
 * Dep > Pro > Mun > Loc por NOMBRE contra la geografia ya sembrada, no por id
 * fijo, para que la semilla siga siendo valida en cualquier base.
 *
 * Idempotente por nombre de area (`updateOrCreate`). Datos de DEMO: en
 * produccion no se ejecuta.
 */
class AreasMinerasEjemploSeeder extends Seeder
{
    /**
     * [nombre_area, titular, codigo_ajam, departamento, provincia, municipio, localidad]
     *
     * @var list<array{string, string, string, string, string, string, string}>
     */
    private const AREAS = [
        ['AREA MINERA RIO CHOQUECOTA', 'COOPERATIVA MINERA RIO CHOQUECOTA', '2345678', 'LA PAZ', 'MURILLO', 'LA PAZ', 'LA PAZ'],
        ['AREA MINERA CERRO RICO', 'EMPRESA MINERA CERRO RICO S.A.', '3456789', 'COCHABAMBA', 'CERCADO', 'COCHABAMBA', 'COCHABAMBA'],
        ['AREA MINERA LAGO POOPO', 'COOPERATIVA MINERA LAGO POOPO LTDA', '4567890', 'ORURO', 'CERCADO', 'ORURO', 'ORURO'],
    ];

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command->warn('AreasMinerasEjemploSeeder: datos de DEMO; no se ejecuta en produccion.');

            return;
        }

        $filas = [];
        foreach (self::AREAS as [$nombre, $titular, $codigo, $dep, $prov, $mun, $loc]) {
            $departamento = Departamento::query()->where('nombre', $dep)->firstOrFail();
            $provincia = Provincia::query()
                ->where('departamento_id', $departamento->id)
                ->where('nombre', $prov)
                ->firstOrFail();
            $municipio = Municipio::query()
                ->where('provincia_id', $provincia->id)
                ->where('nombre', $mun)
                ->firstOrFail();
            $localidad = Localidad::query()
                ->where('municipio_id', $municipio->id)
                ->where('nombre', $loc)
                ->firstOrFail();

            $area = AreaMinera::query()->updateOrCreate(
                ['nombre_area_minera' => $nombre],
                [
                    'titular_derecho_minero' => $titular,
                    'codigo_ajam_patente' => $codigo,
                    'departamento_id' => $departamento->id,
                    'provincia_id' => $provincia->id,
                    'municipio_id' => $municipio->id,
                    'localidad_id' => $localidad->id,
                    'activo' => true,
                ],
            );

            $filas[] = [$area->id, $area->nombre_area_minera, $dep.' / '.$prov];
        }

        $this->command->newLine();
        $this->command->info('Areas mineras de ejemplo:');
        $this->command->table(['id', 'area', 'ubicacion'], $filas);
        $this->command->newLine();
    }
}
