<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Proxies de confianza
    |--------------------------------------------------------------------------
    |
    | Detras de Vite (desarrollo) o Nginx (produccion) la IP real del cliente
    | llega en X-Forwarded-For; Laravel solo la usa si confia en el proxy.
    | En desarrollo con Vite se usa '*'; en produccion debe ser la IP del
    | proxy (la de Nginx), jamas '*'.
    |
    */

    'proxies' => env('TRUST_PROXIES'),

];
