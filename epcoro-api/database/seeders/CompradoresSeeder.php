<?php

namespace Database\Seeders;

use App\Models\Comprador;
use App\Models\Liquidacion;
use Illuminate\Database\Seeder;

/**
 * Catalogo de compradores: la unica fila de hoy es la fija de la hoja
 * (EPCORO, celdas C11-C13), la misma que sigue existiendo en
 * parametros_generales por compatibilidad.
 *
 * Ademas reapunta `liquidaciones.comprador_id` de las filas ya existentes
 * cuyo snapshot coincide con una razon social del catalogo.
 */
class CompradoresSeeder extends Seeder
{
    public function run(): void
    {
        $comprador = Comprador::query()->updateOrCreate(
            ['razon_social' => 'EMPRESA ESTATAL DE PRODUCCION Y COMERCIALIZACION DEL ORO - EPCORO'],
            [
                'nim' => '02-2388-05',
                'nit' => '1006965023',
                'activo' => true,
            ],
        );

        Liquidacion::query()
            ->whereNull('comprador_id')
            ->where('comprador_razon_social', $comprador->razon_social)
            ->update(['comprador_id' => $comprador->id]);
    }
}
