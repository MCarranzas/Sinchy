<?php

namespace Tests\Unit;

use App\Support\Respaldos;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * La logica de respaldos que no depende de pg_dump (FASE 11.6): nombre,
 * presentacion de tamano y purga por retencion. Se prueba aparte del comando
 * porque el comando necesita el cliente de PostgreSQL instalado y aqui lo
 * que importa es que la purga no borre de mas.
 */
final class RespaldosTest extends TestCase
{
    private string $directorio;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directorio = sys_get_temp_dir().DIRECTORY_SEPARATOR.'epcoro-respaldos-'.uniqid('', true);
        File::ensureDirectoryExists($this->directorio);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directorio);

        parent::tearDown();
    }

    public function test_el_nombre_sigue_el_patron_esperado(): void
    {
        $this->assertMatchesRegularExpression(
            '/^epcoro-\d{4}-\d{2}-\d{2}_\d{6}\.dump$/',
            Respaldos::nombre(),
        );

        $this->assertSame('epcoro-2024-12-02_023000.dump', Respaldos::nombre('2024-12-02_023000'));
    }

    public function test_la_purga_borra_los_vencidos_y_respeta_lo_demas(): void
    {
        $viejo = $this->directorio.DIRECTORY_SEPARATOR.'epcoro-2024-12-01_023000.dump';
        $reciente = $this->directorio.DIRECTORY_SEPARATOR.'epcoro-'.Carbon::now()->format('Y-m-d_His').'.dump';
        $ajeno = $this->directorio.DIRECTORY_SEPARATOR.'notas.txt';
        $parecido = $this->directorio.DIRECTORY_SEPARATOR.'copia-importante.dump';

        foreach ([$viejo, $reciente, $ajeno, $parecido] as $archivo) {
            File::put($archivo, 'x');
        }

        touch($viejo, Carbon::now()->subDays(40)->getTimestamp());

        $borrados = Respaldos::purgar($this->directorio, 30);

        $this->assertSame(1, $borrados);
        $this->assertFalse(File::exists($viejo));
        $this->assertTrue(File::exists($reciente));
        $this->assertTrue(File::exists($ajeno), 'no debe tocar archivos ajenos al patron');
        $this->assertTrue(File::exists($parecido), 'solo el patron epcoro-*.dump cuenta');
    }

    public function test_con_retencion_cero_no_borra_nada(): void
    {
        $viejo = $this->directorio.DIRECTORY_SEPARATOR.'epcoro-2020-01-01_000000.dump';
        File::put($viejo, 'x');
        touch($viejo, Carbon::now()->subYears(5)->getTimestamp());

        $this->assertSame(0, Respaldos::purgar($this->directorio, 0));
        $this->assertTrue(File::exists($viejo));
    }

    public function test_el_tamano_se_presenta_en_unidades_legibles(): void
    {
        $this->assertSame('512,0 B', Respaldos::humano(512));
        $this->assertSame('1,0 KB', Respaldos::humano(1024));
        $this->assertSame('1,5 MB', Respaldos::humano(1572864));
    }
}
