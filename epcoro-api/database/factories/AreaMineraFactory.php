<?php

namespace Database\Factories;

use App\Models\AreaMinera;
use App\Models\Departamento;
use App\Models\Localidad;
use App\Models\Municipio;
use App\Models\Provincia;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AreaMinera>
 */
class AreaMineraFactory extends Factory
{
    protected $model = AreaMinera::class;

    /**
     * La cadena Dep > Pro > Mun > Loc se crea de UNA sola vez y en orden.
     *
     * No se puede anidar `Provincia::factory()->for($departamento)` aqui
     * dentro: si cada atributo creara su propia jerarquia, el area terminaria
     * con un municipio de un departamento distinto del que declara, y eso
     * precisamente es lo que la cascada del formulario esta disenada para
     * evitar. Como la definicion se evalua una sola vez por creacion, crear
     * la cadena aqui no duplica filas dentro de un mismo area.
     *
     * Efecto secundario conocido y aceptado: un `make()` de esta factory
     * tambien materializa las 4 filas de ubicacion.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $departamento = Departamento::factory()->create();
        $provincia = Provincia::factory()->create(['departamento_id' => $departamento]);
        $municipio = Municipio::factory()->create(['provincia_id' => $provincia]);
        $localidad = Localidad::factory()->create(['municipio_id' => $municipio]);

        return [
            'nombre_area_minera' => 'AREA MINERA '.strtoupper(fake()->city()),
            'titular_derecho_minero' => strtoupper(fake()->company()),
            'codigo_ajam_patente' => (string) fake()->unique()->numberBetween(1000000, 9999999),
            'departamento_id' => $departamento,
            'provincia_id' => $provincia,
            'municipio_id' => $municipio,
            'localidad_id' => $localidad,
            'activo' => true,
        ];
    }
}
