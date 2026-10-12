<?php

/*
|--------------------------------------------------------------------------
| Mensajes de autenticacion (espanol)
|--------------------------------------------------------------------------
|
| El login de EPCORO escribe sus propios mensajes (LoginRequest), pero estas
| claves son las que usa el framework en otros puntos: guardias `auth`,
| confirmacion de contrasena, throttling, etc.
|
*/

return [

    'failed' => 'Estas credenciales no coinciden con nuestros registros.',
    'password' => 'La contrasena proporcionada es incorrecta.',
    'throttle' => 'Demasiados intentos de acceso. Por favor, intente de nuevo en :seconds segundos.',

];
