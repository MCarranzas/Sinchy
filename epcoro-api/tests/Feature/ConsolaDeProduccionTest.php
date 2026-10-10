<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Los dos comandos de operacion de la FASE 11: el respaldo (11.6) y la
 * revision del entorno antes de desplegar (11.4).
 *
 * El respaldo se prueba por su camino de FALLO de forma reproducible: apuntar
 * `respaldo.pg_dump` al propio binario de PHP hace que el proceso termine con
 * error sin crear archivo, igual que un pg_dump mal instalado. No se prueba el
 * exito aqui porque exigiria PostgreSQL y el cliente instalados en la maquina
 * de tests; la purga por retencion si se prueba (RespaldosTest) porque no
 * depende del cliente.
 */
final class ConsolaDeProduccionTest extends TestCase
{
    private string $directorio;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directorio = sys_get_temp_dir().DIRECTORY_SEPARATOR.'epcoro-comando-'.uniqid('', true);
        File::ensureDirectoryExists($this->directorio);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directorio);

        parent::tearDown();
    }

    // ------------------------------------------------------------ respaldo

    public function test_el_respaldo_falla_sin_crear_archivo_cuando_el_cliente_falla(): void
    {
        config(['respaldo.pg_dump' => PHP_BINARY]);

        $this->artisan('epcoro:respaldar', [
            '--ruta' => $this->directorio,
            '--sin-retener' => true,
        ])->assertFailed();

        $this->assertSame(
            [],
            File::glob($this->directorio.DIRECTORY_SEPARATOR.'epcoro-*.dump'),
            'un respaldo fallido no debe dejar un archivo a medias',
        );
    }

    // --------------------------------------------------------- produccion

    public function test_la_revision_de_produccion_pasa_con_un_entorno_correcto(): void
    {
        $this->entornoDeProduccion();

        $this->artisan('epcoro:revisar-produccion')->assertSuccessful();
    }

    public function test_la_revision_de_produccion_falla_con_debug_encendido(): void
    {
        $this->entornoDeProduccion();
        config(['app.debug' => true]);

        $this->artisan('epcoro:revisar-produccion')->assertFailed();
    }

    public function test_la_revision_de_produccion_falla_si_sanctum_sigue_en_localhost(): void
    {
        $this->entornoDeProduccion();
        config(['sanctum.stateful' => ['localhost', 'localhost:5173']]);

        $this->artisan('epcoro:revisar-produccion')->assertFailed();
    }

    public function test_la_revision_de_produccion_falla_si_la_base_no_tiene_contrasena(): void
    {
        $this->entornoDeProduccion();
        config(['database.connections.pgsql.password' => '']);

        $this->artisan('epcoro:revisar-produccion')->assertFailed();
    }

    /**
     * Deja la configuracion en un estado que cumple TODAS las verificaciones
     * criticas. Las de naturaleza de aviso (nivel de log) no bloquean.
     */
    private function entornoDeProduccion(): void
    {
        config([
            'app.env' => 'production',
            'app.debug' => false,
            'app.key' => 'base64:'.str_repeat('a', 32),
            'app.url' => 'https://epcoro.example.gob.bo',
            'app.timezone' => 'America/La_Paz',
            'session.secure' => true,
            'session.encrypt' => true,
            'database.connections.pgsql.password' => 'secreto',
            'sanctum.stateful' => ['epcoro.example.gob.bo'],
            'cors.allowed_origins' => ['https://epcoro.example.gob.bo'],
        ]);
    }
}
