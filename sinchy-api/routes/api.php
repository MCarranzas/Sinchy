<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BandejaController;
use App\Http\Controllers\CatalogoController;
use App\Http\Controllers\LiquidacionController;
use App\Http\Controllers\ParametrosController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\TransicionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas de la API
|--------------------------------------------------------------------------
|
| La autenticacion es por COOKIE DE SESION (Sanctum SPA), no por token.
| El navegador y la API comparten dominio de sesion gracias a
| `statefulApi()` en bootstrap/app.php.
|
| Consecuencia practica para curl y Postman: las peticiones tienen que
| declararse "stateful" enviando la cabecera Referer u Origin de un dominio
| autorizado en config/sanctum.php (SANCTUM_STATEFUL_DOMAINS). Sin esa
| cabecera Laravel no arranca la sesion y el login responde 419.
|
| Versionado (R5): los recursos nuevos viven bajo /api/v1. El grupo /auth
| historico se conserva como ALIAS deprecado: el password reset del cliente
| viejo se entiende, pero el codigo nuevo no debe construir URLs sobre el, y
| en produccion se podra quitar en cuanto el frontend viejo desaparezca.
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

Route::prefix('v1')->middleware('auth:sanctum')->name('v1.')->group(function () {
    // ------------------------------------------------------ sesion
    Route::post('/auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:'.config('autenticacion.throttle_login'))
        ->withoutMiddleware('auth:sanctum')
        ->name('auth.login');
    Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
    Route::get('/auth/me', [AuthController::class, 'me'])->name('auth.me');
    Route::post('/auth/change-password', [AuthController::class, 'changePassword'])
        ->name('auth.change-password');

    // ------------------------------------------------------ catalogos
    Route::get('/catalogos/departamentos', [CatalogoController::class, 'departamentos'])
        ->name('catalogos.departamentos');
    Route::get('/catalogos/provincias', [CatalogoController::class, 'provincias'])
        ->name('catalogos.provincias');
    Route::get('/catalogos/municipios', [CatalogoController::class, 'municipios'])
        ->name('catalogos.municipios');
    Route::get('/catalogos/localidades', [CatalogoController::class, 'localidades'])
        ->name('catalogos.localidades');
    Route::get('/catalogos/areas-mineras', [CatalogoController::class, 'areasMineras'])
        ->name('catalogos.areas-mineras');
    Route::get('/catalogos/personas', [CatalogoController::class, 'personas'])
        ->name('catalogos.personas');
    Route::post('/catalogos/personas', [CatalogoController::class, 'guardarPersona'])
        ->name('catalogos.personas.guardar');
    Route::get('/catalogos/presentaciones-mineral', [CatalogoController::class, 'presentacionesMineral'])
        ->name('catalogos.presentaciones-mineral');
    Route::get('/catalogos/compradores', [CatalogoController::class, 'compradores'])
        ->name('catalogos.compradores');
    Route::get('/cotizaciones', [CatalogoController::class, 'cotizaciones'])
        ->name('catalogos.cotizaciones');
    Route::get('/catalogos/tipo-cambio', [CatalogoController::class, 'tipoCambio'])
        ->name('catalogos.tipo-cambio');

    // ------------------------------------------------------ parametros
    Route::get('/parametros', [ParametrosController::class, 'index'])->name('parametros.index');
    Route::put('/parametros', [ParametrosController::class, 'update'])->name('parametros.update');

    // --------------------------------------------------- liquidaciones
    // `/bandeja` va antes de `/liquidaciones/{liquidacion}`: como es una
    // ruta fija, si se declarara despues Laravel la resolveria como un id.
    Route::get('/bandeja', [BandejaController::class, '__invoke'])->name('bandeja');
    Route::get('/liquidaciones', [LiquidacionController::class, 'index'])->name('liquidaciones.index');
    Route::post('/liquidaciones', [LiquidacionController::class, 'store'])->name('liquidaciones.store');
    Route::get('/liquidaciones/{liquidacion}', [LiquidacionController::class, 'show'])
        ->name('liquidaciones.show');
    Route::put('/liquidaciones/{liquidacion}', [LiquidacionController::class, 'update'])
        ->name('liquidaciones.update');
    Route::delete('/liquidaciones/{liquidacion}', [LiquidacionController::class, 'destroy'])
        ->name('liquidaciones.destroy');
    Route::get('/liquidaciones/{liquidacion}/pdf', [LiquidacionController::class, 'pdf'])
        ->name('liquidaciones.pdf');
    Route::get('/liquidaciones/{liquidacion}/excel', [LiquidacionController::class, 'excel'])
        ->name('liquidaciones.excel');
    Route::post('/liquidaciones/{liquidacion}/transiciones', [TransicionController::class, 'store'])
        ->name('liquidaciones.transiciones');
    Route::post('/liquidaciones/{liquidacion}/observaciones', [LiquidacionController::class, 'observar'])
        ->name('liquidaciones.observaciones');
    Route::get('/liquidaciones/{liquidacion}/historial', [LiquidacionController::class, 'historial'])
        ->name('liquidaciones.historial');

    // -------------------------------------------------------- reportes
    Route::get('/reportes/resumen', [ReporteController::class, 'resumen'])->name('reportes.resumen');
    Route::get('/reportes/liquidaciones', [ReporteController::class, 'liquidaciones'])
        ->name('reportes.liquidaciones');
    Route::get('/reportes/exportar', [ReporteController::class, 'exportar'])->name('reportes.exportar');
});

// Alias legacy deprecado (ver nota de cabecera).
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
