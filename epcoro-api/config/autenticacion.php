<?php

/*
|--------------------------------------------------------------------------
| Parametros de autenticacion de EPCORO
|--------------------------------------------------------------------------
|
| Estaban metidos como numeros sueltos en el AuthController. Se mueven aqui
| para poder ajustarlos sin tocar logica: el numero de intentos y los minutos
| de bloqueo van a cambiar segun lo que decida el negocio, y probarlos con
| valores hardcodeados obliga a reescribir el controlador cada vez.
|
*/

return [

    /*
    | Numero de intentos fallidos seguidos antes de bloquear la cuenta.
    | El contador se reinicia en cuanto hay un login correcto.
    */
    'intentos_maximos' => (int) env('AUTH_INTENTOS_MAXIMOS', 5),

    /*
    | Minutos que dura el bloqueo por intentos fallidos.
    */
    'minutos_bloqueo' => (int) env('AUTH_MINUTOS_BLOQUEO', 15),

    /*
    | Ventana de rate limiting del endpoint de login, en el formato
    | "intentos,minutos". Evita el ataque por fuerza bruta distribuido, que
    | nunca llegaria al contador por usuario porque ataca muchos a la vez.
    */
    'throttle_login' => env('AUTH_THROTTLE_LOGIN', '6,1'),

    /*
    | Exigir que la contrasena sea distinta de la actual al cambiarla.
    | Sin esto un usuario "cambia" su contrasena escribiendo la misma.
    */
    'exigir_contrasena_distinta' => true,

];
