<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Candado de los mensajes de validacion en espanol (lang/es/validation.php).
 *
 * Sin ese archivo, Laravel cae al locale de respaldo (en) y las reglas que no
 * escriben su mensaje a mano (required, regex, exists, decimal...) devuelven
 * texto en ingles. El sintoma es silencioso: la API sigue respondiendo 422 y
 * los tests que solo miran la ESTRUCTURA del error siguen verdes. Por eso se
 * fija aqui el texto exacto, incluido el nombre legible del campo que resuelve
 * el bloque `attributes`.
 */
class MensajesDeValidacionTest extends TestCase
{
    public function test_la_api_esta_configurada_en_espanol(): void
    {
        $this->assertSame('es', config('app.locale'));
    }

    public function test_una_regla_generica_usa_el_nombre_legible_del_campo(): void
    {
        $validador = Validator::make(
            ['fecha_transaccion' => ''],
            ['fecha_transaccion' => ['required']],
        );

        $this->assertSame(
            'El campo fecha de transaccion es obligatorio.',
            $validador->errors()->first('fecha_transaccion'),
        );
    }

    public function test_un_formato_invalido_tambien_sale_en_espanol(): void
    {
        $validador = Validator::make(
            ['vendedor_ci' => '4.530.001'],
            ['vendedor_ci' => ['regex:/^\d{1,10}$/']],
        );

        $this->assertSame(
            'El formato del campo CI del vendedor no es valido.',
            $validador->errors()->first('vendedor_ci'),
        );
    }

    public function test_un_campo_sin_nombre_legible_se_humaniza(): void
    {
        // Laravel no deja el snake_case crudo: reemplaza el guion bajo por un
        // espacio. El bloque `attributes` existe para los casos en que esa
        // humanizacion no alcanza (siglas como CI/NIM/NIT, textos largos).
        $validador = Validator::make(
            ['campo_desconocido' => ''],
            ['campo_desconocido' => ['required']],
        );

        $this->assertSame(
            'El campo campo desconocido es obligatorio.',
            $validador->errors()->first('campo_desconocido'),
        );
    }
}
