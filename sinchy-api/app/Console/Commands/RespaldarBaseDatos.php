<?php

namespace App\Console\Commands;

use App\Support\Auditoria;
use App\Support\Respaldos;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/**
 * Respaldo diario de la base de datos con pg_dump (FASE 11.6).
 *
 * Se ejecuta por consola (programado en routes/console.php) o a mano. La
 * salida es un archivo comprimido en formato custom (`-Fc`), que es el que
 * admite restauracion selectiva y verificacion con `pg_restore --list`. El
 * nombre lleva la marca de tiempo para no pisar el respaldo del dia anterior.
 *
 * La contrasena NO se pasa por linea de comandos (quedaria en el listado de
 * procesos): viaja por la variable de entorno PGPASSWORD del hijo, que es
 * como lo espera la libreria cliente de PostgreSQL.
 *
 * Si pg_dump falla se devuelve codigo de salida distinto de cero para que el
 * programador de tareas pueda alertar: un respaldo que falla en silencio es
 * peor que no tener respaldo, porque nadie lo busca hasta que lo necesita.
 */
class RespaldarBaseDatos extends Command
{
    protected $signature = 'sinchiwayra:respaldar
        {--ruta= : Directorio destino (por defecto, config respaldo.directorio)}
        {--dias= : Dias de retencion (por defecto, config respaldo.dias_retencion)}
        {--sin-retener : No borra los respaldos vencidos}';

    protected $description = 'Genera un respaldo comprimido de la base de datos con pg_dump y purga los vencidos.';

    public function handle(): int
    {
        $directorio = (string) ($this->option('ruta') ?: config('respaldo.directorio'));
        $dias = $this->option('dias') !== null
            ? (int) $this->option('dias')
            : (int) config('respaldo.dias_retencion');

        File::ensureDirectoryExists($directorio);

        $archivo = $directorio.DIRECTORY_SEPARATOR.Respaldos::nombre();

        $conexion = (string) config('database.default');
        /** @var array<string, mixed> $config */
        $config = config('database.connections.'.$conexion);

        $proceso = new Process(
            [
                (string) config('respaldo.pg_dump'),
                '--host='.($config['host'] ?? '127.0.0.1'),
                '--port='.($config['port'] ?? '5432'),
                '--username='.($config['username'] ?? ''),
                '--format=custom',
                '--no-password',
                '--file='.$archivo,
                (string) ($config['database'] ?? ''),
            ],
            null,
            ['PGPASSWORD' => (string) ($config['password'] ?? '')],
        );

        $proceso->setTimeout(null);
        $proceso->run();

        if (! $proceso->isSuccessful() || ! is_file($archivo)) {
            $this->error('El respaldo fallo: '.trim($proceso->getErrorOutput() ?: 'sin detalle.'));

            Auditoria::registrar(Auditoria::RESPALDO, [
                'estado' => 'fallido',
                'detalle' => mb_substr(trim($proceso->getErrorOutput()), 0, 500),
            ]);

            return self::FAILURE;
        }

        $tamano = File::size($archivo);
        $this->info('Respaldo creado: '.$archivo.' ('.Respaldos::humano($tamano).')');

        Auditoria::registrar(Auditoria::RESPALDO, [
            'estado' => 'ok',
            'archivo' => basename($archivo),
            'bytes' => $tamano,
            'dias_retencion' => $dias,
        ]);

        if (! $this->option('sin-retener')) {
            $borrados = Respaldos::purgar($directorio, $dias);
            $this->info("Respaldos vencidos eliminados: {$borrados}.");
        }

        return self::SUCCESS;
    }
}
