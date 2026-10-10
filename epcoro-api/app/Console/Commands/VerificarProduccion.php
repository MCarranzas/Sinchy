<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Revision previa a produccion (FASE 11.4).
 *
 * Recorre las variables que, mal puestas, no rompen el sistema en
 * desarrollo pero si lo exponen o lo dejan inservible en produccion: un
 * APP_DEBUG=true que filtra la traza y las credenciales, una APP_KEY vacia
 * que invalida sesiones, un CORS o Sanctum que siguen apuntando a localhost,
 * una base sin contrasena. Se ejecuta sobre el .env real:
 *
 *     php artisan epcoro:revisar-produccion
 *
 * Devuelve codigo distinto de cero si hay algun fallo critico, para poder
 * encadenarlo en un despliegue que no debe continuar con el entorno mal
 * configurado.
 */
class VerificarProduccion extends Command
{
    protected $signature = 'epcoro:revisar-produccion
        {--estricto : Falla tambien con los avisos, no solo con los criticos}';

    protected $description = 'Verifica que el entorno cumpla los requisitos minimos de produccion.';

    /**
     * @var array<int, array{check: string, estado: string, detalle: string}>
     */
    private array $resultados = [];

    private bool $hayFallo = false;

    public function handle(): int
    {
        $this->revisar('APP_ENV', config('app.env') === 'production', (string) config('app.env'), 'debe ser "production"');
        $this->revisar('APP_DEBUG', config('app.debug') === false, config('app.debug') ? 'true' : 'false', 'debe ser false');
        $this->revisar('APP_KEY', is_string(config('app.key')) && config('app.key') !== '', config('app.key') !== null && config('app.key') !== '' ? 'presente' : 'vacia', 'generar con php artisan key:generate');
        $this->revisar('APP_URL', str_starts_with((string) config('app.url'), 'https://'), (string) config('app.url'), 'debe ser https:// en produccion');
        $this->revisar('APP_TIMEZONE', config('app.timezone') === 'America/La_Paz', (string) config('app.timezone'), 'las liquidaciones se registran en hora de Bolivia');

        $conexion = (string) config('database.default');
        /** @var array<string, mixed> $db */
        $db = config('database.connections.'.$conexion);
        $this->revisar(
            'DB_PASSWORD',
            ($db['password'] ?? '') !== '',
            ($db['password'] ?? '') !== '' ? 'presente' : 'vacia',
            'la base no puede ir sin contrasena en produccion',
        );

        $this->revisar('SESSION_SECURE_COOKIE', config('session.secure') === true, config('session.secure') ? 'true' : 'false', 'la cookie de sesion debe viajar solo por HTTPS');
        $this->revisar('SESSION_ENCRYPT', config('session.encrypt') === true, config('session.encrypt') ? 'true' : 'false', 'recomendado cifrar la sesion', aviso: true);

        $nivel = $this->nivelDeLog();
        $this->revisar('LOG_LEVEL', $nivel !== null && $nivel !== 'debug', $nivel ?? 'desconocido', 'en produccion, no dejar "debug"', aviso: true);

        $dominios = array_filter(array_map('trim', (array) config('sanctum.stateful', [])));
        $conLocalhost = array_filter($dominios, fn (string $d): bool => str_contains($d, 'localhost') || str_contains($d, '127.0.0.1'));
        $this->revisar('SANCTUM_STATEFUL_DOMAINS', $conLocalhost === [], $conLocalhost === [] ? count($dominios).' dominio(s)' : implode(',', $conLocalhost), 'no debe incluir localhost/127.0.0.1');

        $origenes = array_filter(array_map('trim', (array) config('cors.allowed_origins', [])));
        $origenLocal = array_filter($origenes, fn (string $o): bool => str_contains($o, 'localhost') || str_contains($o, '127.0.0.1'));
        $this->revisar('CORS_ALLOWED_ORIGINS', $origenLocal === [], $origenLocal === [] ? count($origenes).' origen(es)' : implode(',', $origenLocal), 'no debe permitir localhost/127.0.0.1');

        $this->table(['Verificacion', 'Estado', 'Valor', 'Regla'], $this->resultados);

        if ($this->hayFallo) {
            $this->error('Hay verificaciones criticas en rojo: NO desplegar asi.');

            return self::FAILURE;
        }

        if ($this->option('estricto') && $this->hayAviso()) {
            $this->warn('Hay avisos pendientes (modo estricto).');

            return self::FAILURE;
        }

        $this->info('Entorno apto para produccion.');

        return self::SUCCESS;
    }

    private function revisar(string $check, bool $ok, string $valor, string $regla, bool $aviso = false): void
    {
        $estado = $ok ? 'OK' : ($aviso ? 'AVISO' : 'FALLO');

        if (! $ok && ! $aviso) {
            $this->hayFallo = true;
        }

        $this->resultados[] = [
            'check' => $check,
            'estado' => $estado,
            'detalle' => $valor,
            'regla_legible' => $ok ? '' : $regla,
        ];
    }

    private function hayAviso(): bool
    {
        foreach ($this->resultados as $fila) {
            if ($fila['estado'] === 'AVISO') {
                return true;
            }
        }

        return false;
    }

    /**
     * Nivel efectivo del canal de log por defecto. Si es un "stack" (el caso
     * habitual) el nivel vive en los canales hijos, no en el stack.
     */
    private function nivelDeLog(): ?string
    {
        $config = (array) config('logging.channels.'.(string) config('logging.default'));

        if (isset($config['channels']) && is_array($config['channels'])) {
            foreach ($config['channels'] as $hijo) {
                $nivel = config('logging.channels.'.$hijo.'.level');

                if (is_string($nivel) && $nivel !== '') {
                    return $nivel;
                }
            }

            return null;
        }

        $nivel = $config['level'] ?? null;

        return is_string($nivel) && $nivel !== '' ? $nivel : null;
    }
}
