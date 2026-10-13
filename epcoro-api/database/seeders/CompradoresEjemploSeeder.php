<?php

namespace Database\Seeders;

use App\Models\Comprador;
use Illuminate\Database\Seeder;

/**
 * Compradores de ejemplo, ADEMAS del fijo de la hoja (EPCORO) que crea
 * CompradoresSeeder.
 *
 * El catalogo ya admite varias filas (`razon_social`, `nim`, `nit`, `activo`);
 * esto deja mas de una para probar el catalogo de captura y los reportes.
 *
 * Idempotente por razon social (`updateOrCreate`). Datos de DEMO: en produccion
 * no se ejecuta.
 */
class CompradoresEjemploSeeder extends Seeder
{
    /**
     * [razon_social, nim, nit]
     *
     * @var list<array{string, string, string}>
     */
    private const COMPRADORES = [
        ['COOPERATIVA MINERA AURIFERA DEL SUR LTDA', '02-3344-77', '1029384756'],
        ['COMERCIALIZADORA DE MINERALES ANDES S.R.L.', '03-5566-88', '2039184756'],
    ];

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command->warn('CompradoresEjemploSeeder: datos de DEMO; no se ejecuta en produccion.');

            return;
        }

        $filas = [];
        foreach (self::COMPRADORES as [$razonSocial, $nim, $nit]) {
            $comprador = Comprador::query()->updateOrCreate(
                ['razon_social' => $razonSocial],
                ['nim' => $nim, 'nit' => $nit, 'activo' => true],
            );

            $filas[] = [$comprador->id, $comprador->razon_social, $comprador->nim];
        }

        $this->command->newLine();
        $this->command->info('Compradores de ejemplo:');
        $this->command->table(['id', 'razon_social', 'nim'], $filas);
        $this->command->newLine();
    }
}
