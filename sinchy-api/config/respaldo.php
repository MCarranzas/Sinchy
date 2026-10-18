<?php

/*
|--------------------------------------------------------------------------
| Respaldos de la base de datos (FASE 11.6)
|--------------------------------------------------------------------------
|
| El sistema maneja datos fiscales y de dinero: el respaldo no es un extra,
| es lo que hace recuperable un error humano o un fallo de disco. La copia se
| produce con `pg_dump` (formato comprimido, restaurable con `pg_restore`) y
| se guarda fuera de la base: un dump dentro del mismo servidor no protegeria
| de la caida del servidor, asi que en produccion `respaldos.directorio` debe
| apuntar a un volumen o destino externo y `pg_dump` venir del PATH o de la
| variable PG_DUMP_PATH.
|
*/

return [

    /*
    | Carpeta donde se dejan los respaldos. Por defecto dentro de storage,
    | que es escribible por la aplicacion. En produccion, apuntar a un
    | directorio fuera del arbol de la app (o a un montaje externo).
    */
    'directorio' => env('RESPALDO_DIRECTORIO', storage_path('app/backups')),

    /*
    | Cuantos dias se conservan. El comando borra los respaldos mas viejos
    | que esta ventana, para que el disco no se llene sin control. 30 dias
    | cubre el mes fiscal; subirlo si el negocio pide mas historia.
    */
    'dias_retencion' => (int) env('RESPALDO_DIAS_RETENCION', 30),

    /*
    | Ruta al binario de pg_dump. Vacio = resolverlo desde el PATH. Se puede
    | fijar la version exacta para que un upgrade de PostgreSQL no cambie el
    | formato del dump sin querer.
    */
    'pg_dump' => env('PG_DUMP_PATH', 'pg_dump'),

];
