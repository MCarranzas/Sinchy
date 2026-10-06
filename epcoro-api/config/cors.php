<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | El frontend (epcoro-web) corre en un servidor de desarrollo distinto al
    | de la API, asi que el navegador envia peticiones de origen cruzado. Sin
    | esta configuracion el navegador bloquea la respuesta aunque la API
    | responda correctamente.
    |
    | En DESARROLLO solo se permite Vite (5173). En PRODUCCION se usa el
    | dominio real de la SPA. Nunca usar '*' con credenciales: el navegador
    | lo rechaza cuando Sanctum envia cookies de sesion.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter(array_map('trim', explode(
        ',',
        (string) env('CORS_ALLOWED_ORIGINS', 'http://localhost:5173'),
    )))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
