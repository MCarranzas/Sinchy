<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
| Respaldo diario de la base (FASE 11.6). Corre de madrugada, fuera del
| horario de captura, y sin solaparse: `withoutOverlapping` evita que un
| respaldo lento se encuentre con el del dia siguiente. El planificador
| necesita un cron que ejecute `php artisan schedule:run` cada minuto
| (ver README, seccion de respaldos).
*/
Schedule::command('sinchiwayra:respaldar')
    ->dailyAt('02:30')
    ->withoutOverlapping()
    ->onOneServer();
