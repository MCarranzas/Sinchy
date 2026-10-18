<?php

namespace Tests\Feature;

use App\Models\Liquidacion;
use App\Models\User;
use Database\Factories\UserFactory;
use Database\Seeders\ParametrosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Traza de auditoria de las acciones sensibles (FASE 11.5): login, intento
 * fallido, cambio de contrasena, transicion de estado, cambio de parametros
 * y exportaciones. Todas terminan en el canal `auditoria`.
 *
 * El canal real escribe en storage/logs/auditoria-YYYY-MM-DD.log (driver
 * daily). Para el test se cambia a un archivo temporal con driver `single`,
 * que no rota y se puede leer completo: se prueba el Auditoria de verdad, sin
 * mocks de la fachada Log, y el test verifica el contenido que quedo escrito.
 */
final class AuditoriaTest extends AuthTestCase
{
    use RefreshDatabase;

    private string $ruta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ParametrosSeeder::class);

        $this->ruta = sys_get_temp_dir().DIRECTORY_SEPARATOR.'sinchiwayra-auditoria-'.uniqid('', true).'.log';

        config([
            'logging.channels.auditoria' => [
                'driver' => 'single',
                'path' => $this->ruta,
                'level' => 'info',
                'replace_placeholders' => true,
            ],
        ]);
    }

    protected function tearDown(): void
    {
        if (is_file($this->ruta)) {
            @unlink($this->ruta);
        }

        parent::tearDown();
    }

    // ------------------------------------------------------------- sesion

    public function test_el_login_exitoso_queda_en_la_auditoria(): void
    {
        $usuario = User::factory()->conRol('tecnico')->create();

        $this->withHeaders($this->cabecerasStateful())
            ->postJson('/api/v1/auth/login', $this->credenciales($usuario))
            ->assertOk();

        $registro = $this->registro();

        $this->assertStringContainsString('INFO: login {', $registro);
        $this->assertStringContainsString('"usuario":"'.$usuario->username.'"', $registro);
        $this->assertStringContainsString('"usuario_id":'.$usuario->id, $registro);
    }

    public function test_el_login_fallido_queda_en_la_auditoria_sin_la_contrasena(): void
    {
        $usuario = User::factory()->conRol('tecnico')->create();

        $this->withHeaders($this->cabecerasStateful())
            ->postJson('/api/v1/auth/login', [
                'username' => $usuario->username,
                'password' => 'contrasena-incorrecta',
            ])
            ->assertUnprocessable();

        $registro = $this->registro();

        $this->assertStringContainsString('INFO: login_fallido {', $registro);
        $this->assertStringContainsString('"usuario":"'.$usuario->username.'"', $registro);

        // La contrasena, correcta o incorrecta, jamas puede terminar en el
        // log: es el dato que un atacante con acceso al servidor querria.
        $this->assertStringNotContainsString('contrasena-incorrecta', $registro);
    }

    public function test_el_cambio_de_contrasena_queda_en_la_auditoria(): void
    {
        $usuario = User::factory()->conRol('tecnico')->create();

        $this->withHeaders($this->cabecerasStateful())
            ->postJson('/api/v1/auth/login', $this->credenciales($usuario))
            ->assertOk();

        $this->withHeaders($this->cabecerasStateful())
            ->postJson('/api/v1/auth/change-password', [
                'password_actual' => UserFactory::PASSWORD,
                'password' => 'NuevaClave2026',
                'password_confirmation' => 'NuevaClave2026',
            ])
            ->assertOk();

        $registro = $this->registro();

        $this->assertStringContainsString('INFO: password_cambiada {', $registro);
        $this->assertStringNotContainsString('NuevaClave2026', $registro);
    }

    // --------------------------------------------------------- transicion

    public function test_la_transicion_de_estado_queda_en_la_auditoria(): void
    {
        $revisor = User::factory()->conRol('revisor')->create();
        $liquidacion = Liquidacion::factory()->create(['estado' => 'en_revision']);

        $this->actingAs($revisor, 'web')
            ->postJson('/api/v1/liquidaciones/'.$liquidacion->id.'/transiciones', [
                'estado_destino' => 'revisado',
            ])
            ->assertOk();

        $registro = $this->registro();

        $this->assertStringContainsString('INFO: transicion {', $registro);
        $this->assertStringContainsString('"estado_anterior":"en_revision"', $registro);
        $this->assertStringContainsString('"estado_nuevo":"revisado"', $registro);
        $this->assertStringContainsString('"liquidacion_id":'.$liquidacion->id, $registro);
    }

    // ------------------------------------------------------------- datos

    public function test_la_actualizacion_de_parametros_queda_en_la_auditoria(): void
    {
        $admin = User::factory()->conRol('admin')->create();

        $this->actingAs($admin, 'web')
            ->putJson('/api/v1/parametros', [
                'parametros' => ['ALICUOTA_APORTE' => '0.019'],
            ])
            ->assertOk();

        $registro = $this->registro();

        $this->assertStringContainsString('INFO: parametros_actualizados {', $registro);
        $this->assertStringContainsString('ALICUOTA_APORTE', $registro);
    }

    // -------------------------------------------------------- exportacion

    public function test_la_descarga_de_pdf_y_excel_queda_en_la_auditoria(): void
    {
        $tecnico = User::factory()->conRol('tecnico')->create();
        $liquidacion = Liquidacion::factory()->create(['usuario_registro_id' => $tecnico->id]);

        $this->withHeaders($this->cabecerasStateful())
            ->postJson('/api/v1/auth/login', $this->credenciales($tecnico))
            ->assertOk();

        $this->getJson('/api/v1/liquidaciones/'.$liquidacion->id.'/pdf')->assertOk();
        $this->getJson('/api/v1/liquidaciones/'.$liquidacion->id.'/excel')->assertOk();

        $registro = $this->registro();

        $this->assertStringContainsString('INFO: exportacion {', $registro);
        $this->assertStringContainsString('"formato":"pdf"', $registro);
        $this->assertStringContainsString('"formato":"xlsx"', $registro);
    }

    // ----------------------------------------------------------- utiles

    private function registro(): string
    {
        clearstatcache();

        return is_file($this->ruta) ? (string) file_get_contents($this->ruta) : '';
    }
}
