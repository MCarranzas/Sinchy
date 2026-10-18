<?php

namespace App\Models;

use Database\Factories\ParametroGeneralFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Parametro del sistema, pares clave/valor. La PK es `clave`, no un id.
 *
 * Por la decision D1 (docs/reglas-negocio.md) NO existe
 * ALICUOTA_APORTE_SOLIDARIO: la unica alícuota es ALICUOTA_APORTE = 0.018.
 *
 * @property string $clave
 * @property string $valor
 * @property string $tipo
 * @property string|null $descripcion
 */
#[Fillable(['clave', 'valor', 'tipo', 'descripcion'])]
class ParametroGeneral extends Model
{
    /** El pluralizador ingles diria "parametro_generals". */
    protected $table = 'parametros_generales';

    /** @use HasFactory<ParametroGeneralFactory> */
    use HasFactory;

    protected $primaryKey = 'clave';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * El valor se devuelve como string siempre: es la forma en que llega
     * desde la base y castearlo a float introduciria el error de redondeo
     * que la regla T3 busca evitar. Quien necesita hacer aritmetica lo hace
     * con bcmath dentro del servicio de calculo.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'valor' => 'string',
        ];
    }

    /**
     * Valor convertido a decimal para calculo con bcmath.
     *
     * @throws InvalidArgumentException si el parametro no es numerico
     */
    public function valorDecimal(): string
    {
        if (! is_numeric($this->valor)) {
            throw new InvalidArgumentException("El parametro {$this->clave} no es numerico: {$this->valor}");
        }

        return (string) $this->valor;
    }
}
