<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Numeracion de documentos (secciones 4.4 y 5.3).
 *
 * Un correlativo por (tipo, ejercicio), guardado en la tabla `correlativos`
 * con clave compuesta (tipo, ejercicio). El contador vive en la base y se toma
 * con SELECT ... FOR UPDATE dentro de una transaccion: la fila es la garantia,
 * no la memoria del proceso. Dos peticiones simultaneas no pueden entregar el
 * mismo numero, y si dos transacciones intentan insertar la misma fila a la
 * vez, la primary key hace fallar a una de las dos - un error, nunca un
 * duplicado.
 *
 * El formato no esta inventado: sale del Excel oficial (seccion 1).
 *
 *     C8                  = EP-BCB-723       -> numero_documento
 *     FORM- + C8 + /2024  = FORM-EP-BCB-723/2024  -> codigo_formulario
 *
 * La seccion 4.4 del plan menciona 'L-2024-000723'; es el ejemplo anterior y
 * el que vale es el de la hoja, porque es el numero que se imprime.
 *
 * El numero de transaccion TAMBIEN puede llegar del usuario (viene del Excel
 * externo): en ese caso no se llama a `asignar()`, y el unico control es el
 * indice unico de `numero_documento`, que el Form Request de la FASE 6 traduce
 * a un 422.
 */
final class NumeradorDocumento
{
    /**
     * Prefijo del numero_documento por tipo de documento.
     *
     * @var array<string, string>
     */
    private const PREFIJOS = [
        'liquidacion' => 'EP-BCB-',
    ];

    /**
     * Entrega el siguiente correlativo y arma el par que se persiste.
     *
     * @return array{correlativo: int, numero_documento: string, codigo_formulario: string}
     *
     * @throws \InvalidArgumentException si el tipo no tiene prefijo asignado
     */
    public function asignar(string $tipo, int $ejercicio): array
    {
        self::prefijo($tipo);

        $correlativo = DB::transaction(fn (): int => $this->siguiente($tipo, $ejercicio));
        $numeroDocumento = self::numeroDocumento($tipo, $correlativo);

        return [
            'correlativo' => $correlativo,
            'numero_documento' => $numeroDocumento,
            'codigo_formulario' => self::codigoFormulario($numeroDocumento, $ejercicio),
        ];
    }

    /**
     * 'FORM-EP-BCB-723/2024'. Funcion pura: sirve tambien cuando el numero lo
     * trajo el usuario y no hubo correlativo que asignar.
     */
    public static function codigoFormulario(string $numeroDocumento, int $ejercicio): string
    {
        return 'FORM-'.$numeroDocumento.'/'.$ejercicio;
    }

    /**
     * Incrementa el contador DENTRO de la transaccion ya abierta.
     *
     * `ultimo` guarda el ultimo correlativo ENTREGADO (la migracion lo dice):
     * se incrementa antes de usarlo, nunca despues.
     */
    private function siguiente(string $tipo, int $ejercicio): int
    {
        $registro = DB::table('correlativos')
            ->where('tipo', $tipo)
            ->where('ejercicio', $ejercicio)
            ->lockForUpdate()
            ->first();

        if ($registro === null) {
            DB::table('correlativos')->insert([
                'tipo' => $tipo,
                'ejercicio' => $ejercicio,
                'ultimo' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return 1;
        }

        $ultimo = (int) $registro->ultimo + 1;

        DB::table('correlativos')
            ->where('tipo', $tipo)
            ->where('ejercicio', $ejercicio)
            ->update(['ultimo' => $ultimo, 'updated_at' => now()]);

        return $ultimo;
    }

    private static function numeroDocumento(string $tipo, int $correlativo): string
    {
        return self::prefijo($tipo).$correlativo;
    }

    /** @throws \InvalidArgumentException */
    private static function prefijo(string $tipo): string
    {
        $prefijo = self::PREFIJOS[$tipo] ?? null;

        if ($prefijo === null) {
            $tipos = implode(', ', array_keys(self::PREFIJOS));

            throw new \InvalidArgumentException(
                "Tipo de documento desconocido: \"{$tipo}\". Registrado: {$tipos}."
            );
        }

        return $prefijo;
    }
}
