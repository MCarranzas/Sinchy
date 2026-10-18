<?php

namespace Database\Seeders;

use App\Models\PresentacionMineral;
use Illuminate\Database\Seeder;

/**
 * Presentaciones del mineral (seccion 3.2).
 *
 * ORO FUNDIDO es la de hoja.xlsx (celda C37) y por eso no se puede quitar:
 * es la que usa el caso de prueba ORO. ORO EN GRANO viene de la lista de
 * ejemplos de la seccion 3.2 de instrucciones.txt.
 *
 * NO se agregan mas presentaciones "por si acaso": es un catalogo que el
 * negocio completa, y una fila inventada en produccion es un dato falso en
 * un documento legal.
 */
class PresentacionesSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['ORO FUNDIDO', 'ORO EN GRANO'] as $nombre) {
            PresentacionMineral::query()->firstOrCreate(['nombre' => $nombre]);
        }
    }
}
