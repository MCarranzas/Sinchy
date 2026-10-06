<?php

namespace App\Providers;

use App\Contracts\LectorParametros;
use App\Repositories\ParametroRepository;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * El servicio de calculo no habla con la base de datos: recibe el lector
     * de parametros por constructor (seccion 5.1 de instrucciones.txt). Este
     * es el unico punto donde el contrato se resuelve con la implementacion
     * real, para que los controladores de la FASE 6 puedan pedir el servicio
     * por tipo y no tengan que instanciarlo a mano.
     */
    public function register(): void
    {
        $this->app->bind(LectorParametros::class, ParametroRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
