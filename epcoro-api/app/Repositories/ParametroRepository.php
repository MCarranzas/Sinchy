<?php

namespace App\Repositories;

use App\Contracts\LectorParametros;
use App\Models\ParametroGeneral;

/**
 * Lectura de `parametros_generales` (seccion 5.2).
 *
 * Sin cache y sin memoria entre llamadas: cada consulta ve la tabla como esta
 * en ese momento. Es deliberado -dos lecturas por calculo contra una tabla de
 * media docena de filas- y evita la clase de bug mas dificil de cazar en un
 * sistema de dinero: un alicuota vieja servida desde cache despues de que un
 * administrador la corrigiera.
 *
 * Quien necesite mas (por ejemplo el formulario que muestra el tipo de cambio
 * sugerido) usa `valor()` con la clave que le falte: el contrato es un par
 * clave/valor y nada mas.
 */
final class ParametroRepository implements LectorParametros
{
    public function valor(string $clave): ?string
    {
        /** @var ParametroGeneral|null $parametro */
        $parametro = ParametroGeneral::query()->find($clave);

        return $parametro?->valor;
    }
}
