<?php

use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas de la API
|--------------------------------------------------------------------------
|
| La autenticacion es por COOKIA DE SESION (Sanctum SPA), no por token.
| El navegador y la API comparten dominio de sesion gracias a
| `statefulApi()` en bootstrap/app.php.
|
| Consecuencia practica para curl y Postman: las peticiones tienen que
| declararse "stateful" enviando la cabecera Referer u Origin de un dominio
| autorizado en config/sanctum.php (SANCTUM_STATEFUL_DOMAINS). Sin esa
| cabecera Laravel no arranca la sesion y el login responde 419.
|
*/

Route::get('/health', function (Request $request) {
    $checks = [];

    try {
        DB::connection()->getPdo();
        $checks['database'] = 'ok';
    } catch (Throwable $e) {
        $checks['database'] = 'error: '.$e->getMessage();
    }

    $estado = $checks['database'] === 'ok' ? 'ok' : 'degraded';

    return response()->json([
        'status' => $estado,
        'app' => config('app.name'),
        'environment' => app()->environment(),
        'php' => PHP_VERSION,
        'laravel' => app()->version(),
        'timezone' => config('app.timezone'),
        'checks' => $checks,
        'timestamp' => now()->toIso8601String(),
    ], $estado === 'ok' ? 200 : 503);
})->name('health');

Route::prefix('auth')->name('auth.')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:'.config('autenticacion.throttle_login'))
        ->name('login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('/me', [AuthController::class, 'me'])->name('me');
        Route::post('/change-password', [AuthController::class, 'changePassword'])
            ->name('change-password');
    });
});
