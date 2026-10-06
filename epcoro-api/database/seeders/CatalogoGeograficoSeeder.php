<?php

namespace Database\Seeders;

use App\Models\Departamento;
use App\Models\Localidad;
use App\Models\Municipio;
use App\Models\Provincia;
use Illuminate\Database\Seeder;

/**
 * Jerarquia Dep > Pro > Mun > Loc de Bolivia (seccion 3.2).
 *
 * El arbol sale de database/catalogos/bolivia.php, que es la linea de base de
 * desarrollo: 9 departamentos, 112 provincias y 340 municipios con su codigo
 * INE. La fuente oficial de produccion la define el negocio (punto 0.9
 * abierto); cambiar la fuente es cambiar ese archivo, no el seeder.
 *
 * Las LOCALIDADES se derivan del municipio: en el formulario oficial la
 * localidad es la cabecera municipal, asi que por ahora cada municipio aporta
 * una localidad con el mismo codigo y nombre. Cuando el negocio entregue el
 * anexo de localidades se reemplaza ese bloque y el resto del seeder no se
 * toca.
 *
 * Idempotente: se puede re-ejecutar sin duplicar filas (updateOrCreate sobre
 * la clave de cada nivel).
 */
class CatalogoGeograficoSeeder extends Seeder
{
    public function run(): void
    {
        /** @var array<string, array<string, mixed>> $arbol */
        $arbol = require database_path('catalogos/bolivia.php');

        foreach ($arbol as $codigoDepartamento => $departamento) {
            $dep = Departamento::query()->updateOrCreate(
                ['codigo' => $codigoDepartamento],
                ['nombre' => $departamento['nombre']],
            );

            foreach ($departamento['provincias'] as $codigoProvincia => $provincia) {
                $prov = Provincia::query()->updateOrCreate(
                    ['departamento_id' => $dep->id, 'codigo' => $codigoProvincia],
                    ['nombre' => $provincia['nombre']],
                );

                foreach ($provincia['municipios'] as $codigoMunicipio => $nombreMunicipio) {
                    $mun = Municipio::query()->updateOrCreate(
                        ['provincia_id' => $prov->id, 'codigo' => $codigoMunicipio],
                        ['nombre' => $nombreMunicipio],
                    );

                    Localidad::query()->updateOrCreate(
                        ['municipio_id' => $mun->id, 'codigo' => $codigoMunicipio],
                        ['nombre' => $nombreMunicipio],
                    );
                }
            }
        }
    }
}
