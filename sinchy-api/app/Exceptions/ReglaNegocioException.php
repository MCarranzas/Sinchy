<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Base de las violaciones de regla de negocio.
 *
 * La FASE 6 las atrapa en el manejador de excepciones y las traduce a 422:
 * es el cliente el que mando un dato o un estado que la maquina de estados no
 * acepta, no un fallo del servidor.
 *
 * ParametroInvalidoException NO hereda de esta: una tabla de parametros mal
 * cargada es un error de configuracion, y un error de configuracion es 500.
 */
class ReglaNegocioException extends RuntimeException {}
