<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Auth\Events\Failed;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;

/**
 * Tests del endpoint de login, del bloqueo de cuenta y de las rutas protegidas.
 */
class AuthTestTest extends AuthTestCase
{
    public function test_login_correcto_devuelve_el_usuario_con_rol_y_permisos(): void
    {
        $usuario = User::factory()->conRol('tecnico')->create();

        $respuesta = $this->withHeaders($this->cabecerasStateful())
            ->postJson('/api/auth/login', $this->credenciales($usuario));

        $respuesta->assertOk()
            ->assertJsonPath('message', 'Sesion iniciada correctamente.')
            ->assertJsonPath('usuario.username', $usuario->username)
            ->assertJsonPath('usuario.rol', 'tecnico')
            ->assertJsonPath('usuario.activo', true);

        $this->assertAuthenticatedAs($usuario->fresh(), 'web');
    }

    public function test_login_registra_el_ultimo_acceso_y_limpia_los_intentos(): void
    {
        $usuario = User::factory()->conRol('tecnico')->create([
            'intentos_fallidos' => 3,
        ]);

        $this->withHeaders($this->cabecerasStateful())
            ->postJson('/api/auth/login', $this->credenciales($usuario))
            ->assertOk();

        $usuario->refresh();

        $this->assertSame(0, $usuario->intentos_fallidos);
        $this->assertNotNull($usuario->ultimo_acceso);
    }

    public function test_login_con_contrasena_incorrecta_devuelve_422(): void
    {
        $usuario = User::factory()->conRol('tecnico')->create();

        $respuesta = $this->withHeaders($this->cabecerasStateful())
            ->postJson('/api/auth/login', [
                'username' => $usuario->username,
                'password' => 'contrasena-equivocada',
            ]);

        $respuesta->assertStatus(422)
            ->assertJsonValidationErrors('username');

        $this->assertGuest();
    }

    public function test_el_mensaje_de_error_no_revela_si_el_usuario_existe(): void
    {
        $usuario = User::factory()->conRol('tecnico')->create();

        $conUsuarioExistente = $this->withHeaders($this->cabecerasStateful())
            ->postJson('/api/auth/login', [
                'username' => $usuario->username,
                'password' => 'equivocada',
            ])
            ->json('errors.username.0');

        $sinUsuario = $this->withHeaders($this->cabecerasStateful())
            ->postJson('/api/auth/login', [
                'username' => 'no-existe-este-usuario',
                'password' => 'equivocada',
            ])
            ->json('errors.username.0');

        $this->assertSame($conUsuarioExistente, $sinUsuario);
    }

    public function test_usuario_inactivo_no_puede_entrar(): void
    {
        $usuario = User::factory()->conRol('tecnico')->inactivo()->create();

        $respuesta = $this->withHeaders($this->cabecerasStateful())
            ->postJson('/api/auth/login', $this->credenciales($usuario));

        $respuesta->assertStatus(422);

        $this->assertGuest();
    }

    public function test_usuario_bloqueado_recibe_429_con_los_minutos_restantes(): void
    {
        $usuario = User::factory()->conRol('tecnico')
            ->bloqueadoHasta(now()->addMinutes(12))
            ->create();

        $respuesta = $this->withHeaders($this->cabecerasStateful())
            ->postJson('/api/auth/login', $this->credenciales($usuario));

        $respuesta->assertStatus(429)
            ->assertJsonPath('minutos_restantes', 12);

        $this->assertGuest();
    }

    public function test_un_bloqueo_ya_vencido_no_impide_entrar(): void
    {
        $usuario = User::factory()->conRol('tecnico')->bloqueoExpirado()->create();

        $this->withHeaders($this->cabecerasStateful())
            ->postJson('/api/auth/login', $this->credenciales($usuario))
            ->assertOk();

        $this->assertAuthenticatedAs($usuario->fresh(), 'web');
    }

    public function test_se_bloquea_la_cuenta_al_superar_los_intentos_maximos(): void
    {
        $maximos = (int) config('autenticacion.intentos_maximos');
        $usuario = User::factory()->conRol('tecnico')->create();

        for ($intento = 1; $intento < $maximos; $intento++) {
            $this->withHeaders($this->cabecerasStateful())
                ->postJson('/api/auth/login', [
                    'username' => $usuario->username,
                    'password' => 'equivocada',
                ])
                ->assertStatus(422);

            $this->assertFalse(
                $usuario->fresh()->estaBloqueado(),
                "No deberia estar bloqueado tras {$intento} intento(s).",
            );
        }

        $this->withHeaders($this->cabecerasStateful())
            ->postJson('/api/auth/login', [
                'username' => $usuario->username,
                'password' => 'equivocada',
            ])
            ->assertStatus(422);

        $usuario->refresh();

        $this->assertTrue($usuario->estaBloqueado());
        $this->assertSame($maximos, $usuario->intentos_fallidos);

        // Y con la contrasena CORRECTA ya no entra, porque el bloqueo se
        // comprueba antes de validar la credencial.
        $this->withHeaders($this->cabecerasStateful())
            ->postJson('/api/auth/login', $this->credenciales($usuario))
            ->assertStatus(429);

        $this->assertGuest();
    }

    public function test_el_bloqueo_no_afecta_a_otros_usuarios(): void
    {
        $bloqueado = User::factory()->conRol('tecnico')
            ->bloqueadoHasta(now()->addMinutes(10))
            ->create();

        $otro = User::factory()->conRol('tecnico')->create();

        $this->withHeaders($this->cabecerasStateful())
            ->postJson('/api/auth/login', $this->credenciales($bloqueado))
            ->assertStatus(429);

        $this->withHeaders($this->cabecerasStateful())
            ->postJson('/api/auth/login', $this->credenciales($otro))
            ->assertOk();

        $this->assertAuthenticatedAs($otro->fresh(), 'web');
    }

    public function test_login_exige_usuario_y_contrasena(): void
    {
        $this->withHeaders($this->cabecerasStateful())
            ->postJson('/api/auth/login', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['username', 'password']);
    }

    public function test_ruta_protegida_sin_sesion_devuelve_401(): void
    {
        $this->getJson('/api/auth/me')->assertStatus(401);
    }

    public function test_me_devuelve_el_usuario_autenticado(): void
    {
        $usuario = User::factory()->conRol('gerente')->create();

        $respuesta = $this->actingAs($usuario, 'web')
            ->getJson('/api/auth/me');

        $respuesta->assertOk()
            ->assertJsonPath('usuario.username', $usuario->username)
            ->assertJsonPath('usuario.rol', 'gerente');

        $respuesta->assertJsonPath('usuario.permisos', fn ($permisos): bool => in_array(
            'liquidaciones.aprobar',
            $permisos,
            true,
        ));
    }

    public function test_logout_invalida_la_sesion(): void
    {
        $usuario = User::factory()->conRol('tecnico')->create();

        $this->actingAs($usuario, 'web')
            ->withHeaders($this->cabecerasStateful())
            ->postJson('/api/auth/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Sesion cerrada correctamente.');

        // NO se usa assertGuest() a proposito: el middleware auth:sanctum cambia
        // el guard por defecto a 'sanctum', y assertGuest() consultaria ese, que
        // todavia tiene al usuario cacheado en esta misma peticion. Lo que
        // importa es que el guard de sesion quedo vacio y que /me ya responde
        // 401 en la peticion siguiente.
        $this->assertFalse(Auth::guard('web')->check());

        // forgetGuards() descarta los guards cacheados para que la peticion
        // siguiente los resuelva desde cero. Sin esto, el RequestGuard de
        // Sanctum devolveria el usuario que cacheo durante el logout y /me
        // responderia 200. En una peticion real cada request tiene su propio
        // contenedor, asi que el cacheo no existe y /me responde 401.
        Auth::forgetGuards();

        $this->getJson('/api/auth/me')->assertStatus(401);
    }

    public function test_cambio_de_contrasena_exige_la_actual_correcta(): void
    {
        $usuario = User::factory()->conRol('tecnico')->create();

        $this->actingAs($usuario, 'web')
            ->withHeaders($this->cabecerasStateful())
            ->postJson('/api/auth/change-password', [
                'password_actual' => 'no-es-la-actual',
                'password' => 'NuevaClave123',
                'password_confirmation' => 'NuevaClave123',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('password_actual');
    }

    public function test_cambio_de_contrasena_no_permite_repetir_la_misma(): void
    {
        $usuario = User::factory()->conRol('tecnico')->create();

        $this->actingAs($usuario, 'web')
            ->withHeaders($this->cabecerasStateful())
            ->postJson('/api/auth/change-password', [
                'password_actual' => UserFactory::PASSWORD,
                'password' => UserFactory::PASSWORD,
                'password_confirmation' => UserFactory::PASSWORD,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }

    public function test_cambio_de_contrasena_exige_confirmacion_coincidente(): void
    {
        $usuario = User::factory()->conRol('tecnico')->create();

        $this->actingAs($usuario, 'web')
            ->withHeaders($this->cabecerasStateful())
            ->postJson('/api/auth/change-password', [
                'password_actual' => UserFactory::PASSWORD,
                'password' => 'NuevaClave123',
                'password_confirmation' => 'OtraClave999',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }

    public function test_cambio_de_contrasena_actualiza_el_hash_y_limpia_el_flag(): void
    {
        $usuario = User::factory()->conRol('tecnico')->create([
            'debe_cambiar_password' => true,
        ]);

        $this->actingAs($usuario, 'web')
            ->withHeaders($this->cabecerasStateful())
            ->postJson('/api/auth/change-password', [
                'password_actual' => UserFactory::PASSWORD,
                'password' => 'NuevaClave123',
                'password_confirmation' => 'NuevaClave123',
            ])
            ->assertOk();

        $usuario->refresh();

        $this->assertFalse($usuario->debe_cambiar_password);
        $this->assertTrue(
            Hash::check('NuevaClave123', $usuario->password),
        );
    }

    public function test_el_cambio_de_contrasena_cierra_las_demas_sesiones(): void
    {
        // phpunit.xml deja SESSION_DRIVER=array para que los tests sean rapidos.
        // Ese driver no persiste filas y no permite enumerar sesiones, asi que
        // con el la invalidacion no se puede comprobar. Esta prueba usa el
        // driver real de produccion; forgetInstance obliga a que el
        // SessionManager se reconstruya leyendo la config nueva.
        config(['session.driver' => 'database']);
        $this->app->forgetInstance('session');

        $usuario = User::factory()->conRol('tecnico')->create();

        // Se simulan dos "otros equipos" con sesion abierta antes del cambio.
        foreach (['sesion-equipo-1', 'sesion-equipo-2'] as $id) {
            DB::table('sessions')->insert([
                'id' => $id,
                'user_id' => $usuario->id,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'otro-navegador',
                'payload' => 'x',
                'last_activity' => now()->timestamp,
            ]);
        }

        $this->assertSame(
            2,
            DB::table('sessions')->where('user_id', $usuario->id)->count(),
            'Las dos sesiones ajenas deberian existir antes del cambio.',
        );

        $this->actingAs($usuario, 'web')
            ->withHeaders($this->cabecerasStateful())
            ->postJson('/api/auth/change-password', [
                'password_actual' => UserFactory::PASSWORD,
                'password' => 'NuevaClave123',
                'password_confirmation' => 'NuevaClave123',
            ])
            ->assertOk();

        $restantes = DB::table('sessions')
            ->where('user_id', $usuario->id)
            ->pluck('id')
            ->all();

        $this->assertNotContains('sesion-equipo-1', $restantes);
        $this->assertNotContains('sesion-equipo-2', $restantes);

        // Solo debe quedar la sesion actual, ya regenerada: si regenerate()
        // dejara la fila anterior, el conteo seria mayor que 1.
        $this->assertCount(1, $restantes);
    }

    public function test_cambio_de_contrasena_sin_sesion_devuelve_401(): void
    {
        $this->postJson('/api/auth/change-password', [
            'password_actual' => UserFactory::PASSWORD,
            'password' => 'NuevaClave123',
            'password_confirmation' => 'NuevaClave123',
        ])->assertStatus(401);
    }

    public function test_se_emite_el_evento_failed_de_laravel_en_un_intento_fallido(): void
    {
        Event::fake([Failed::class]);

        $usuario = User::factory()->conRol('tecnico')->create();

        $this->withHeaders($this->cabecerasStateful())
            ->postJson('/api/auth/login', [
                'username' => $usuario->username,
                'password' => 'equivocada',
            ])
            ->assertStatus(422);

        Event::assertDispatched(Failed::class);
    }
}
