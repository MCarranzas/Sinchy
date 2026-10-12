<?php

namespace Database\Seeders;

use App\Models\ParametroGeneral;
use Illuminate\Database\Seeder;

/**
 * Parametros iniciales (seccion 3.3).
 *
 * Dos reglas que este seeder tiene que respetar siempre:
 *
 *  1. NO existe ALICUOTA_APORTE_SOLIDARIO. La decision D1 (2026-10-05)
 *     establecio que hay UNA sola deduccion, la del 1.8 %, y que la diferencia
 *     con el nombre del negocio era solo de nomenclatura. Sembrar ese parametro
 *     reintroduciria el bug que la correccion C4 elimino.
 *
 *  2. Los datos fijos del comprador (celdas C11-C13 de hoja.xlsx) NO se
 *     siembran aqui: viven en la tabla `compradores` (modelo Comprador,
 *     seeder CompradoresSeeder) desde 2026-10-12. Sus NIM/NIT van como TEXTO,
 *     nunca como numero: 1006965023 es un NIT, no una cantidad, y guardarlo
 *     como entero invita a notacion cientifica y a perder ceros a la
 *     izquierda (correccion C5).
 *
 * Idempotente: updateOrCreate sobre la clave.
 */
class ParametrosSeeder extends Seeder
{
    /**
     * clave => [valor, tipo, descripcion].
     *
     * @var array<string, array{0: string, 1: string, 2: string}>
     */
    private const PARAMETROS = [
        'GRAMOS_POR_ONZA_TROY' => [
            '31.1035',
            'decimal',
            'Divisor de la formula del valor bruto. Regla T2: es parametro, no constante del codigo.',
        ],
        'ALICUOTA_APORTE' => [
            '0.018',
            'decimal',
            'Aporte de caja de salud 1,8 %, unica deduccion (decision D1). Se aplica solo si aplica_caja_salud es true.',
        ],
    ];

    public function run(): void
    {
        foreach (self::PARAMETROS as $clave => [$valor, $tipo, $descripcion]) {
            ParametroGeneral::query()->updateOrCreate(
                ['clave' => $clave],
                ['valor' => $valor, 'tipo' => $tipo, 'descripcion' => $descripcion],
            );
        }
    }
}
