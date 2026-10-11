<?php

namespace Tests\Feature;

use App\Models\AreaMinera;
use App\Models\CotizacionQuincenal;
use App\Models\Departamento;
use App\Models\Liquidacion;
use App\Models\LiquidacionDeduccion;
use App\Models\LiquidacionEstadoHistorial;
use App\Models\LiquidacionObservacion;
use App\Models\Localidad;
use App\Models\Municipio;
use App\Models\ParametroGeneral;
use App\Models\Persona;
use App\Models\PresentacionMineral;
use App\Models\Provincia;
use App\Models\TipoCambio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Cada modelo de negocio apunta a SU tabla y esa tabla existe.
 *
 * El pluralizador de Laravel es ingles: "localidad" termina en
 * "localidads" y "liquidacion" en "liquidacions". Sin este test, el error
 * solo aparece cuando un factory intenta INSERTar, a mitad de un test que
 * no tiene nada que ver con el nombre de la tabla.
 */
class TablasDeModelosTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private const MODELOS = [
        Departamento::class => 'departamentos',
        Provincia::class => 'provincias',
        Municipio::class => 'municipios',
        Localidad::class => 'localidades',
        Persona::class => 'personas',
        AreaMinera::class => 'areas_mineras',
        PresentacionMineral::class => 'presentaciones_mineral',
        ParametroGeneral::class => 'parametros_generales',
        CotizacionQuincenal::class => 'cotizaciones_quincenales',
        TipoCambio::class => 'tipos_cambio',
        Liquidacion::class => 'liquidaciones',
        LiquidacionDeduccion::class => 'liquidacion_deducciones',
        LiquidacionObservacion::class => 'liquidacion_observaciones',
        LiquidacionEstadoHistorial::class => 'liquidacion_estado_historial',
    ];

    public function test_cada_modelo_declara_la_tabla_que_las_migraciones_crearon(): void
    {
        foreach (self::MODELOS as $modelo => $tabla) {
            $this->assertSame($tabla, (new $modelo)->getTable(), $modelo);
            $this->assertTrue(Schema::hasTable($tabla), 'No existe la tabla '.$tabla);
        }
    }
}
