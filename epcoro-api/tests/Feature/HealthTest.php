<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_devuelve_ok_cuando_la_base_responde(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertOk();
        $response->assertJsonPath('status', 'ok');
        $response->assertJsonPath('checks.database', 'ok');
        $response->assertJsonPath('app', 'EPCORO');
    }

    public function test_health_usa_la_zona_horaria_de_bolivia(): void
    {
        // Una liquidacion creada cerca de medianoche debe quedar con la fecha
        // que ve el usuario en La Paz, no en UTC.
        $this->assertSame('America/La_Paz', config('app.timezone'));

        $response = $this->getJson('/api/health');

        $response->assertOk();
        $this->assertSame(
            'America/La_Paz',
            $response->json('timezone')
        );
    }

    public function test_health_no_expone_datos_de_conexion(): void
    {
        $response = $this->getJson('/api/health');

        $contenido = $response->getContent();

        $this->assertStringNotContainsString('epcoro_secret', $contenido);
        $this->assertStringNotContainsString(config('app.key'), $contenido);
    }
}
