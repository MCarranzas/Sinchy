<?php

namespace Database\Seeders;

use App\Models\Persona;
use Illuminate\Database\Seeder;

/**
 * Vendedores de ejemplo para la captura (seccion 8.4).
 *
 * El vendedor no tiene catalogo administrable todavia: ademas del alta rapida
 * del formulario, esta semilla deja un juego de personas listo para probar el
 * buscador (por nombre, CI o NIT) sin teclearlas una a una.
 *
 * Idempotente por CI (`firstOrCreate`): re-ejecutarla no duplica y, si la
 * persona ya existe, NO se pisan sus datos: un CI real no se sobreescribe por
 * una semilla de DEMO. Por eso son datos de DEMO y no se ejecutan en produccion.
 */
class VendedoresEjemploSeeder extends Seeder
{
    /**
     * [nombres, apellidos, ci, nit]
     *
     * @var list<array{string, string, string, string|null}>
     */
    private const VENDEDORES = [
        ['Juan Carlos', 'Mamani Quispe', '8321456', '4983215'],
        ['Maria Fernanda', 'Choque Apaza', '9104782', null],
        ['Pedro Antonio', 'Condori Flores', '7456321', '6712458'],
        ['Ana Lucia', 'Vargas Rojas', '10234567', '8123456'],
        ['Roberto Carlos', 'Cruz Villarroel', '6987451', null],
        ['Silvia Roxana', 'Fernandez Gutierrez', '8452197', '7325498'],
        ['Luis Alberto', 'Huanca Copa', '5874123', null],
        ['Carmen Rosa', 'Ticona Mamani', '9632587', '5541236'],
        ['Jorge Andres', 'Salazar Paredes', '7123698', null],
        ['Daniela Alejandra', 'Rios Mercado', '10987654', '9987654'],
        ['Marcelo Ivan', 'Rodriguez Loayza', '8451236', '6235789'],
        ['Patricia Gimena', 'Aguilar Soria', '9876543', null],
    ];

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command->warn('VendedoresEjemploSeeder: datos de DEMO; no se ejecuta en produccion.');

            return;
        }

        $filas = [];
        foreach (self::VENDEDORES as [$nombres, $apellidos, $ci, $nit]) {
            $persona = Persona::query()->firstOrCreate(
                ['ci' => $ci],
                [
                    'nombres' => $nombres,
                    'apellidos' => $apellidos,
                    'nombre_completo' => $nombres.' '.$apellidos,
                    'nit' => $nit,
                    'tipo_documento' => 'CI',
                    'activo' => true,
                ],
            );

            $filas[] = [$persona->id, $persona->nombre_completo, $persona->ci];
        }

        $this->command->newLine();
        $this->command->info('Vendedores de ejemplo:');
        $this->command->table(['id', 'nombre', 'ci'], $filas);
        $this->command->newLine();
    }
}
