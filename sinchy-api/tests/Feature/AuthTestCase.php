<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Factories\UserFactory;
use Database\Seeders\RolPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Base de los tests de autenticacion.
 *
 * Setea la cabecera Referer para que la peticion sea STATEFUL. Sanctum solo
 * arranca la sesion si el Referer coincide con config('sanctum.stateful'); sin
 * esto, los endpoints que tocan $request->session() fallan con
 * "Session store not set on request".
 *
 * El CSRF no se prueba a proposito: Laravel lo desactiva en los tests
 * unitarios (VerifyCsrfToken::runningUnitTests), asi que aqui no se puede
 * comprobar. El CSRF de verdad se verifica contra el servidor, en pasos.txt.
 */
abstract class AuthTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Los roles deben existir antes de asignarlos.
        $this->seed(RolPermisosSeeder::class);
    }

    /**
     * @return array<string, string>
     */
    protected function cabecerasStateful(): array
    {
        return [
            'Accept' => 'application/json',
            'Referer' => 'http://localhost:5173/',
        ];
    }

    protected function credenciales(User $usuario): array
    {
        return [
            'username' => $usuario->username,
            'password' => UserFactory::PASSWORD,
        ];
    }
}
