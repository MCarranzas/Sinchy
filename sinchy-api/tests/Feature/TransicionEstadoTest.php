<?php

namespace Tests\Feature;

use App\Enums\EstadoLiquidacion;
use App\Exceptions\TransicionInvalidaException;
use App\Models\Liquidacion;
use App\Models\User;
use App\Services\TransicionEstadoService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * La maquina de estados y su bitacora (secciones 5.5 y 3.5).
 *
 * La matriz completa, 8 estados por 8: 11 pares permitidos se aplican y
 * escriben su fila de historial; los 53 restantes se rechazan sin dejar ni
 * un rastro. Cada caso es la tabla de docs/reglas-negocio.md, no una
 * opinion de este archivo.
 *
 * El servicio no recibe los permisos: eso es la Policy y ya tiene su test
 * (LiquidacionPolicyTest). Aqui solo se prueba que el salto es posible y que
 * queda registrado.
 */
final class TransicionEstadoTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Transiciones validas: origen => destinos (seccion 5.5).
     *
     * @var array<string, array<string>>
     */
    private const VALIDAS = [
        'creado' => ['en_revision', 'anulado', 'eliminado'],
        'en_revision' => ['revisado', 'rechazado', 'anulado', 'en_correccion'],
        'revisado' => ['aprobado', 'rechazado', 'anulado', 'en_revision'],
        'rechazado' => [],
        'en_correccion' => ['en_revision', 'anulado'],
        'aprobado' => [],
        'anulado' => [],
        'eliminado' => [],
    ];

    /**
     * Las columnas de auditoria que cada destino debe escribir, con su estado
     * de origen. `revisado` y `en_correccion` no tienen par fecha/usuario en
     * el esquema: su unico rastro es el historial.
     *
     * @var array<string, array{origen: string, fecha: string, usuario: string}>
     */
    private const COLUMNAS = [
        'en_revision' => ['origen' => 'creado', 'fecha' => 'fecha_revision', 'usuario' => 'usuario_revision_id'],
        'aprobado' => ['origen' => 'revisado', 'fecha' => 'fecha_aprobacion', 'usuario' => 'usuario_aprobacion_id'],
        'rechazado' => ['origen' => 'en_revision', 'fecha' => 'fecha_rechazo', 'usuario' => 'usuario_rechazo_id'],
        'anulado' => ['origen' => 'en_revision', 'fecha' => 'fecha_anulacion', 'usuario' => 'usuario_anulacion_id'],
    ];

    public function test_los_once_pares_validos_se_aplican_y_quedan_en_el_historial(): void
    {
        $usuario = User::factory()->create();
        $servicio = new TransicionEstadoService;
        $aplicados = 0;

        foreach (self::VALIDAS as $origen => $destinos) {
            foreach ($destinos as $destino) {
                $liquidacion = Liquidacion::factory()->create(['estado' => $origen]);

                $resultado = $servicio->ejecutar(
                    $liquidacion,
                    EstadoLiquidacion::from($destino),
                    $usuario,
                    'pasa a '.$destino,
                    'motivo de prueba',
                );

                $this->assertSame(
                    $destino,
                    $resultado->estado->value,
                    "{$origen} -> {$destino} debio aplicarse",
                );

                $historial = $resultado->historial()->sole();
                $this->assertSame($origen, $historial->estado_anterior?->value);
                $this->assertSame($destino, $historial->estado_nuevo->value);
                $this->assertSame($usuario->id, $historial->usuario_id);
                $this->assertSame($usuario->nombre_completo, $historial->usuario_nombre);
                $this->assertSame('pasa a '.$destino, $historial->comentario);

                $aplicados++;
            }
        }

        $this->assertSame(13, $aplicados, 'la matriz de la seccion 5.5 tiene 13 transiciones');
    }

    public function test_los_cincuenta_y_tres_pares_no_permitidos_no_escriben_nada(): void
    {
        $usuario = User::factory()->create();
        $servicio = new TransicionEstadoService;
        $rechazados = 0;

        foreach (self::VALIDAS as $origen => $permitidos) {
            // Una sola liquidacion por origen: los intentos fallidos no la
            // modifican, asi que puede reutilizarse.
            $liquidacion = Liquidacion::factory()->create(['estado' => $origen]);

            foreach (array_keys(self::VALIDAS) as $destino) {
                if (in_array($destino, $permitidos, true)) {
                    continue;
                }

                try {
                    $servicio->ejecutar($liquidacion, EstadoLiquidacion::from($destino), $usuario);
                    $this->fail("{$origen} -> {$destino} debio ser rechazada");
                } catch (TransicionInvalidaException $e) {
                    $this->assertSame(
                        "Transicion no permitida: de \"{$origen}\" a \"{$destino}\".",
                        $e->getMessage(),
                    );
                }

                $rechazados++;
            }

            $liquidacion->refresh();

            $this->assertSame(
                $origen,
                $liquidacion->estado->value,
                "un rechazo no debe mover el estado de {$origen}",
            );
            $this->assertSame(
                0,
                $liquidacion->historial()->count(),
                "{$origen}: ni un intento fallido debe dejar fila de historial",
            );
        }

        $this->assertSame(51, $rechazados, 'la matriz 8x8 tiene 64 pares, 13 validos y 51 no');
    }

    /**
     * D2: aprobado no tiene salida. No es que el controlador lo impida, es
     * que la maquina de estados no lo permite, ni siquiera anulando.
     */
    public function test_desde_aprobado_no_hay_ninguna_salida_ni_la_anulacion(): void
    {
        $aprobada = Liquidacion::factory()->aprobada()->create();
        $fechaAprobacionEnBase = DB::table('liquidaciones')
            ->where('id', $aprobada->id)
            ->value('fecha_aprobacion');

        foreach (array_keys(self::VALIDAS) as $destino) {
            try {
                (new TransicionEstadoService)->ejecutar(
                    $aprobada,
                    EstadoLiquidacion::from($destino),
                    User::factory()->create(),
                );
                $this->fail("aprobado -> {$destino} debio ser rechazada");
            } catch (TransicionInvalidaException) {
                // D2: ninguna salida, ni una.
            }
        }

        $aprobada->refresh();

        $this->assertSame('aprobado', $aprobada->estado->value);
        $this->assertSame(
            $fechaAprobacionEnBase,
            DB::table('liquidaciones')->where('id', $aprobada->id)->value('fecha_aprobacion'),
            'un rechazo no debe tocar la fecha de aprobacion',
        );
        $this->assertSame(0, $aprobada->historial()->count());
    }

    /**
     * 'eliminado' solo se alcanza desde 'creado' (seccion 5.6): es un borrado
     * logico, no una etapa del flujo. Ningun otro estado puede llegar a el,
     * y desde el ya no hay salida.
     */
    public function test_eliminado_solo_se_alcanza_desde_creado(): void
    {
        $usuario = User::factory()->create();
        $servicio = new TransicionEstadoService;

        $eliminada = $servicio->ejecutar(
            Liquidacion::factory()->create(['estado' => 'creado']),
            EstadoLiquidacion::Eliminado,
            $usuario,
        );

        $this->assertSame('eliminado', $eliminada->estado->value);
        $this->assertSame('eliminado', $eliminada->historial()->sole()->estado_nuevo->value);

        foreach (array_keys(self::VALIDAS) as $origen) {
            if ($origen === 'creado') {
                continue;
            }

            try {
                $servicio->ejecutar(
                    Liquidacion::factory()->create(['estado' => $origen]),
                    EstadoLiquidacion::Eliminado,
                    $usuario,
                );
                $this->fail("{$origen} -> eliminado debio ser rechazada");
            } catch (TransicionInvalidaException) {
                // Solo 'creado' puede llegar a 'eliminado'.
            }
        }
    }

    /**
     * Cada destino con par fecha/usuario deja las cuatro columnas de las
     * otras vacias: no se pisan entre estados.
     */
    public function test_cada_destino_escribe_sus_columnas_de_auditoria_y_las_demas_no(): void
    {
        $usuario = User::factory()->create();
        $servicio = new TransicionEstadoService;

        foreach (self::COLUMNAS as $destino => $columnas) {
            $liquidacion = Liquidacion::factory()->create(['estado' => $columnas['origen']]);

            $resultado = $servicio->ejecutar(
                $liquidacion,
                EstadoLiquidacion::from($destino),
                $usuario,
                null,
                'motivo de prueba',
            );

            $this->assertNotNull($resultado->{$columnas['fecha']}, "{$destino}: {$columnas['fecha']} debia quedar");
            $this->assertSame($usuario->id, $resultado->{$columnas['usuario']}, "{$destino}: {$columnas['usuario']}");

            foreach (self::COLUMNAS as $otroDestino => $otrasColumnas) {
                if ($otroDestino === $destino) {
                    continue;
                }

                $this->assertNull(
                    $resultado->{$otrasColumnas['fecha']},
                    "{$destino} no debe tocar {$otrasColumnas['fecha']}",
                );
                $this->assertNull(
                    $resultado->{$otrasColumnas['usuario']},
                    "{$destino} no debe tocar {$otrasColumnas['usuario']}",
                );
            }
        }
    }

    /**
     * El gerente devuelve al revisor (`revisado` -> `en_revision`) con una
     * observacion, y eso NO reescribe `fecha_revision` ni
     * `usuario_revision_id`: la re-entrada a revision deja rastro en el
     * historial, la fecha del primer envio queda intacta.
     */
    public function test_devolver_al_revisor_no_reescribe_las_columnas_de_revision(): void
    {
        $usuario = User::factory()->create();
        $servicio = new TransicionEstadoService;

        // Flujo real: creado -> en_revision (escribe fecha_revision) ->
        // revisado (revisa el revisor) -> en_revision (devolver del gerente).
        $envio = $servicio->ejecutar(
            Liquidacion::factory()->create(['estado' => 'creado']),
            EstadoLiquidacion::EnRevision,
            $usuario,
        );

        $fechaOriginal = $envio->fresh()->fecha_revision;
        $usuarioOriginal = $envio->fresh()->usuario_revision_id;

        $revisado = $servicio->ejecutar($envio->fresh(), EstadoLiquidacion::Revisado, $usuario);

        $devolvida = $servicio->ejecutar(
            $revisado->fresh(),
            EstadoLiquidacion::EnRevision,
            $usuario,
            'Revisar el sello del laboratorio.',
        );

        $this->assertSame('en_revision', $devolvida->estado->value);
        $this->assertEquals($fechaOriginal, $devolvida->fecha_revision);
        $this->assertSame($usuarioOriginal, $devolvida->usuario_revision_id);
        $this->assertSame(
            'Revisar el sello del laboratorio.',
            $devolvida->historial()->latest('id')->value('comentario'),
        );
    }

    public function test_el_rechazo_y_la_anulacion_guardan_el_motivo(): void
    {
        $usuario = User::factory()->create();
        $servicio = new TransicionEstadoService;

        $rechazada = $servicio->ejecutar(
            Liquidacion::factory()->create(['estado' => 'en_revision']),
            EstadoLiquidacion::Rechazado,
            $usuario,
            'falta la firma del vendedor',
            'datos del comprador incompletos',
        );

        $this->assertSame('datos del comprador incompletos', $rechazada->motivo_rechazo);
        $this->assertSame('falta la firma del vendedor', $rechazada->historial()->sole()->comentario);

        $anulada = $servicio->ejecutar(
            Liquidacion::factory()->create(['estado' => 'en_revision']),
            EstadoLiquidacion::Anulado,
            $usuario,
            null,
            'captura duplicada',
        );

        $this->assertSame('captura duplicada', $anulada->motivo_anulacion);
    }

    /**
     * Los estados sin columnas de auditoria solo dejan rastro en el
     * historial: la fila no gana fechas ni usuarios inventados. `revisado`
     * es el unico caso vivo (en_correccion quedo como legacy del enum);
     * `rechazado` en cambio SI escribe su par fecha/usuario y el motivo.
     */
    public function test_revisado_no_escribe_columnas_y_rechazado_si(): void
    {
        $usuario = User::factory()->create();
        $servicio = new TransicionEstadoService;

        $revisado = $servicio->ejecutar(
            Liquidacion::factory()->create(['estado' => 'en_revision']),
            EstadoLiquidacion::Revisado,
            $usuario,
        );

        $this->assertNull($revisado->fecha_revision);
        $this->assertNull($revisado->fecha_aprobacion);
        $this->assertNull($revisado->fecha_rechazo);
        $this->assertNull($revisado->fecha_anulacion);
        $this->assertNotNull($revisado->historial()->sole());

        $rechazada = $servicio->ejecutar(
            Liquidacion::factory()->create(['estado' => 'en_revision']),
            EstadoLiquidacion::Rechazado,
            $usuario,
            'rechazo',
            'La cota del peso no coincide con la pesada.',
        );

        $this->assertNotNull($rechazada->fecha_rechazo);
        $this->assertSame($usuario->id, $rechazada->usuario_rechazo_id);
        $this->assertSame('La cota del peso no coincide con la pesada.', $rechazada->motivo_rechazo);
        $this->assertNotNull($rechazada->historial()->sole());
    }

    /**
     * Una rechazada es terminal: sin correccion ni anulacion. El rechazo
     * no abre ningun camino posterior, decision del negocio del 2026-10-12.
     */
    public function test_desde_rechazado_no_hay_salida_ni_hacia_correccion_ni_anulacion(): void
    {
        $rechazada = Liquidacion::factory()->create(['estado' => 'rechazado']);

        foreach (array_keys(self::VALIDAS) as $destino) {
            try {
                (new TransicionEstadoService)->ejecutar(
                    $rechazada,
                    EstadoLiquidacion::from($destino),
                    User::factory()->create(),
                    null,
                    'motivo de prueba',
                );
                $this->fail("rechazado -> {$destino} debio ser rechazada");
            } catch (TransicionInvalidaException) {
                // Rechazado sin salida, como aprobado.
            }
        }

        $rechazada->refresh();

        $this->assertSame('rechazado', $rechazada->estado->value);
        $this->assertSame(0, $rechazada->historial()->count());
    }

    /**
     * La fila inicial NULL -> creado (seccion 3.5): sin ella la bitacora
     * arranca a medias y no se sabe quien abrio el expediente.
     */
    public function test_registrar_inicio_escribe_la_primera_fila_con_estado_anterior_nulo(): void
    {
        $usuario = User::factory()->create();
        $liquidacion = Liquidacion::factory()->create(['estado' => 'creado']);

        (new TransicionEstadoService)->registrarInicio(
            $liquidacion,
            $usuario,
            'captura inicial',
        );

        $historial = $liquidacion->historial()->sole();
        $this->assertNull($historial->estado_anterior);
        $this->assertSame('creado', $historial->estado_nuevo->value);
        $this->assertSame($usuario->id, $historial->usuario_id);
        $this->assertSame('captura inicial', $historial->comentario);
    }

    /**
     * Tercera capa de defensa: el ENUM de PostgreSQL. Si el servicio dejara
     * de validar, la base rechazaria el valor inventado antes de escribirlo.
     */
    public function test_la_base_rechaza_un_estado_que_no_existe(): void
    {
        $liquidacion = Liquidacion::factory()->create(['estado' => 'creado']);

        $this->expectException(QueryException::class);

        DB::table('liquidaciones')
            ->where('id', $liquidacion->id)
            ->update(['estado' => 'inventado']);
    }
}
