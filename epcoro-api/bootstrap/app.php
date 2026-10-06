<?php

use App\Exceptions\ParametroInvalidoException;
use App\Exceptions\ReglaNegocioException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Violacion de regla de negocio (maquina de estados, calculo, D2):
        // el cliente mando algo que el negocio no acepta, asi que 422 y no 500.
        // Sin este mapeo la excepcion subiria como error interno y el frontend
        // no distinguiria "aprobar algo en creado" de un fallo del servidor.
        $exceptions->render(function (ReglaNegocioException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        });

        // parametros_generales mal cargado: es configuracion, no un error del
        // cliente. Se devuelve el mensaje porque la unica forma de arreglarlo
        // es leerlo, pero jamas un 422: nadie debe "corregir su peticion".
        $exceptions->render(function (ParametroInvalidoException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        });
    })->create();
