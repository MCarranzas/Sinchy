<?php

namespace App\Contracts;

/**
 * Lectura de `parametros_generales` (seccion 5.2), sin dependencia de Eloquent.
 *
 * Existe como contrato -y no como clase concreta- por una sola razon: el
 * servicio de calculo tiene que leer GRAMOS_POR_ONZA_TROY y ALICUOTA_APORTE
 * de la base, pero sus tests unitarios no pueden levantar la aplicacion. El
 * servicio depende de este contrato y los tests le pasan un lector en memoria
 * de tres lineas.
 *
 * `valor()` devuelve el texto crudo tal cual llega de la columna varchar: el
 * casteo a numero y el control de rango los hace quien los usa, porque no es
 * lo mismo "no existe" (falta sembrar el seeder) que "existe pero es basura"
 * (hay que decir cual de los dos paso).
 */
interface LectorParametros
{
    /** Valor crudo del parametro, o null si la clave no existe. */
    public function valor(string $clave): ?string;
}
