<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;

/**
 * Utilidades de respaldo (FASE 11.6).
 *
 * La parte con logica propia (que archivos se consideran vencidos, como se
 * presenta el tamano) vive aqui y no dentro del comando, para poder probarla
 * sin depender de que `pg_dump` este instalado en la maquina.
 *
 * nomenclaturaDelRespaldo() centraliza el patron de nombre: la purga solo
 * toca los archivos que siguen ese patron, nunca un archivo ajeno que alguien
 * haya dejado en la carpeta de respaldos.
 */
final class Respaldos
{
    public const PATRON = 'sinchiwayra-*.dump';

    public static function nombre(?string $marcaDeTiempo = null): string
    {
        return 'sinchiwayra-'.($marcaDeTiempo ?? Carbon::now()->format('Y-m-d_His')).'.dump';
    }

    /**
     * Elimina los respaldos mas viejos que la ventana de retencion y
     * devuelve cuantos borro. Con $dias <= 0 no borra nada (retencion
     * infinita a proposito).
     */
    public static function purgar(string $directorio, int $dias): int
    {
        if ($dias <= 0 || ! is_dir($directorio)) {
            return 0;
        }

        $limite = Carbon::now()->subDays($dias);
        $borrados = 0;

        foreach (File::glob($directorio.DIRECTORY_SEPARATOR.self::PATRON) as $archivo) {
            if (Carbon::createFromTimestamp(File::lastModified($archivo))->lt($limite)) {
                File::delete($archivo);
                $borrados++;
            }
        }

        return $borrados;
    }

    /**
     * Tamano legible para el mensaje de consola.
     */
    public static function humano(int $bytes): string
    {
        $unidades = ['B', 'KB', 'MB', 'GB'];
        $indice = 0;
        $valor = (float) $bytes;

        while ($valor >= 1024 && $indice < count($unidades) - 1) {
            $valor /= 1024;
            $indice++;
        }

        return number_format($valor, 1, ',', '.').' '.$unidades[$indice];
    }
}
