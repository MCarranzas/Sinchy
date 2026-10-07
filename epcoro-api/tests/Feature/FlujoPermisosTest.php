<?php

namespace Tests\Feature;

use App\Models\Liquidacion;
use App\Models\User;
use Database\Seeders\ParametrosSeeder;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * FASE 9.7: la matriz ROL x TRANSICION x PROPIEDAD, mas lo que solo puede
 * comprobar el backend (9.5 y 9.6).
 *
 * Los tres ejes son los que la aplicacion tiene que responder siempre:
 *
 *  - ROL: los cuatro de RolPermisosSeeder (tecnico, revisor, gerente, admin).
 *  - TRANSICION: el destino pedido, que puede ser valido en la maquina de
 *    estados o no (el enum es la primera capa, 422).
 *  - PROPIEDAD: propia o ajena, que es lo que la Policy corta con 403 y lo
 *    que separa aprobar de las demas (D2/4.2).
 *
 * Ademas quedan cubiertos tres cortes que no son una transicion puntual:
 * el orden 422 -> 403 de la edicion (9.6), los tres conteos de la bandeja
 * (9.5) y la paginacion por `page`, que el backend leia como `pagina` y
 * devolvia siempre la pagina 1.
 */
class FlujoPermisosTest extends AuthTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // La edicion recalcula, y el recalculo necesita los totales del
        // formulario (gramos por onza y alicuota) de parametros_generales.
        $this->seed(ParametrosSeeder::class);
    }

    // ------------------------------------------------------------ 9.7

    /**
     * 20 filas: 4 roles x los destinos que el negocio les asigna, con la
     * propiedad cruzada a proposito (siempre hay al menos un caso propio y
     * uno ajeno por rol).
     *
     * @return array<string, array{string, string, bool, string, array<string, mixed>, int}>
     */
    public static function matriz(): array
    {
        $motivo = ['motivo' => 'La cota del peso no coincide con la pesada.'];

        return [
            // ------------------------------------------------ tecnico
            'tecnico envia la suya a revision' => ['tecnico', 'creado', true, 'en_revision', [], 200],
            'tecnico no envia ajena a revision' => ['tecnico', 'creado', false, 'en_revision', [], 403],
            'tecnico borra la suya' => ['tecnico', 'creado', true, 'eliminado', [], 200],
            'tecnico no borra ajena' => ['tecnico', 'creado', false, 'eliminado', [], 403],
            'tecnico no aprueba ni la suya' => ['tecnico', 'revisado', true, 'aprobado', [], 403],
            'tecnico no toca una aprobada' => ['tecnico', 'aprobado', true, 'anulado', $motivo, 422],
            'tecnico no salta de creado a revisado' => ['tecnico', 'creado', true, 'revisado', [], 422],

            // ------------------------------------------------ revisor
            'revisor revisa ajena' => ['revisor', 'en_revision', false, 'revisado', [], 200],
            'revisor tambien revisa la suya' => ['revisor', 'en_revision', true, 'revisado', [], 200],
            'revisor no aprueba' => ['revisor', 'revisado', false, 'aprobado', [], 403],
            'rechazo sin motivo no pasa' => ['revisor', 'en_revision', false, 'rechazado', [], 422],
            'revisor solicita correccion con motivo' => ['revisor', 'en_revision', false, 'rechazado', $motivo, 200],
            'revisor rechaza desde revisado' => ['revisor', 'revisado', false, 'rechazado', $motivo, 200],
            'revisor no mueve algo en creado' => ['revisor', 'creado', false, 'revisado', [], 422],

            // ------------------------------------------------- gerente
            'gerente aprueba ajena' => ['gerente', 'revisado', false, 'aprobado', [], 200],
            'gerente no aprueba la suya' => ['gerente', 'revisado', true, 'aprobado', [], 403],
            'anulacion sin motivo no pasa' => ['gerente', 'en_revision', false, 'anulado', [], 422],
            'gerente anula con motivo' => ['gerente', 'en_revision', false, 'anulado', $motivo, 200],
            'gerente no envia a revision' => ['gerente', 'creado', false, 'en_revision', [], 403],

            // --------------------------------------------------- admin
            'nadie toca una aprobada' => ['admin', 'aprobado', true, 'anulado', $motivo, 422],
        ];
    }

    #[DataProvider('matriz')]
    public function test_ninguna_transicion_invalida_pasa_por_api(
        string $rol,
        string $origen,
        bool $propia,
        string $destino,
        array $payload,
        int $esperado,
    ): void {
        $actor = User::factory()->conRol($rol)->create();
        $autor = $propia ? $actor : User::factory()->conRol('tecnico')->create();

        $liquidacion = Liquidacion::factory()->create([
            'usuario_registro_id' => $autor->id,
            'estado' => $origen,
        ]);

        $respuesta = $this->identificarseComo($actor)
            ->postJson('/api/v1/liquidaciones/'.$liquidacion->id.'/transiciones', array_merge(
                ['estado_destino' => $destino],
                $payload,
            ));

        $respuesta->assertStatus($esperado);

        // Ningun caso fallido mueve la fila ni escribe historial.
        $this->assertSame($esperado === 200 ? $destino : $origen, $liquidacion->fresh()->estado->value);
        $this->assertSame($esperado === 200 ? 1 : 0, $liquidacion->historial()->count());
    }

    public function test_las_transiciones_disponibles_se_calculan_por_rol_y_propiedad(): void
    {
        // El tecnico solo ve lo suyo, asi que el caso es siempre propio.
        $tecnico = User::factory()->conRol('tecnico')->create();
        $this->assertSame(
            ['en_revision', 'eliminado'],
            $this->disponibles($tecnico, $this->liquidacionDe($tecnico, ['estado' => 'creado'])),
        );

        $this->assertSame(
            ['en_revision'],
            $this->disponibles($tecnico, $this->liquidacionDe($tecnico, ['estado' => 'en_correccion'])),
        );

        // El revisor no tiene `anular`, aunque el enum lo permita desde aqui.
        $revisor = User::factory()->conRol('revisor')->create();
        $this->assertSame(
            ['revisado', 'rechazado'],
            $this->disponibles($revisor, $this->liquidacionDe($tecnico, ['estado' => 'en_revision'])),
        );

        // El gerente aprueba y anula, pero NUNCA lo suyo (4.2).
        $gerente = User::factory()->conRol('gerente')->create();
        $this->assertSame(
            ['aprobado', 'rechazado', 'anulado'],
            $this->disponibles($gerente, $this->liquidacionDe($tecnico, ['estado' => 'revisado'])),
        );
        $this->assertSame(
            ['rechazado', 'anulado'],
            $this->disponibles($gerente, $this->liquidacionDe($gerente, ['estado' => 'revisado'])),
        );

        // D2: una aprobada no tiene ninguna salida, ni para el administrador.
        $admin = User::factory()->conRol('admin')->create();
        $this->assertSame(
            [],
            $this->disponibles($admin, $this->liquidacionDe($tecnico, ['estado' => 'aprobado'])),
        );
    }

    // ------------------------------------------------------------ 9.6

    public function test_editar_lo_no_editable_responde_422_antes_que_403(): void
    {
        $tecnico = User::factory()->conRol('tecnico')->create();
        // El gerente NO tiene `liquidaciones.editar`: aun asi el corte tiene
        // que ser el ESTADO (422), porque el registro es de solo lectura para
        // todos y el 403 le diria "usted no puede", que seria mentir.
        $gerente = User::factory()->conRol('gerente')->create();

        foreach (['aprobado', 'en_revision', 'revisado', 'rechazado', 'anulado', 'eliminado'] as $estado) {
            $liquidacion = Liquidacion::factory()->create([
                'usuario_registro_id' => $tecnico->id,
                'estado' => $estado,
            ]);

            $this->identificarseComo($gerente)
                ->putJson('/api/v1/liquidaciones/'.$liquidacion->id, ['observaciones' => 'x'])
                ->assertStatus(422);

            $this->identificarseComo($tecnico)
                ->putJson('/api/v1/liquidaciones/'.$liquidacion->id, ['observaciones' => 'x'])
                ->assertStatus(422);
        }
    }

    public function test_editar_lo_editable_ajeno_o_sin_permiso_responde_403(): void
    {
        $tecnico = User::factory()->conRol('tecnico')->create();
        $gerente = User::factory()->conRol('gerente')->create();

        $creada = Liquidacion::factory()->create([
            'usuario_registro_id' => $tecnico->id,
            'estado' => 'creado',
        ]);

        // Estado editable pero el gerente no tiene el permiso de editar.
        $this->identificarseComo($gerente)
            ->putJson('/api/v1/liquidaciones/'.$creada->id, ['observaciones' => 'x'])
            ->assertForbidden();

        // Estado editable, permiso si, pero no es suya.
        $this->identificarseComo($tecnico)
            ->putJson('/api/v1/liquidaciones/'.$creada->id, ['observaciones' => 'x'])
            ->assertOk();
    }

    // ------------------------------------------------------------ 9.5

    public function test_la_bandeja_cuenta_lo_que_corresponde_a_cada_rol(): void
    {
        $tecnico = User::factory()->conRol('tecnico')->create();
        $revisor = User::factory()->conRol('revisor')->create();
        $gerente = User::factory()->conRol('gerente')->create();
        $admin = User::factory()->conRol('admin')->create();

        $this->liquidacionDe($tecnico, ['estado' => 'creado']);
        $this->liquidacionDe($tecnico, ['estado' => 'creado']);
        $this->liquidacionDe($tecnico, ['estado' => 'en_correccion']);
        $this->liquidacionDe($tecnico, ['estado' => 'en_revision']);
        $this->liquidacionDe($tecnico, ['estado' => 'en_revision']);
        $this->liquidacionDe($tecnico, ['estado' => 'revisado']);

        // Solo lo suyo, y las colas que no le tocan quedan en cero.
        $this->assertSame([
            'mis_liquidaciones' => 6,
            'por_revisar' => 0,
            'por_aprobar' => 0,
        ], $this->bandeja($tecnico));

        $this->assertSame([
            'mis_liquidaciones' => 0,
            'por_revisar' => 2,
            'por_aprobar' => 0,
        ], $this->bandeja($revisor));

        $this->assertSame([
            'mis_liquidaciones' => 0,
            'por_revisar' => 0,
            'por_aprobar' => 1,
        ], $this->bandeja($gerente));

        // El administrador administra, no aprueba ni revisa (RolPermisosSeeder).
        $this->assertSame([
            'mis_liquidaciones' => 0,
            'por_revisar' => 0,
            'por_aprobar' => 0,
        ], $this->bandeja($admin));
    }

    public function test_la_bandeja_no_esta_abierta_sin_sesion(): void
    {
        $this->withHeaders($this->cabecerasStateful())
            ->getJson('/api/v1/bandeja')
            ->assertUnauthorized();
    }

    // ------------------------------------------------------- paginacion

    public function test_el_listado_pagina_con_el_mismo_page_que_sus_links(): void
    {
        $tecnico = User::factory()->conRol('tecnico')->create();

        for ($i = 0; $i < 5; $i++) {
            $this->liquidacionDe($tecnico);
        }

        $primera = $this->identificarseComo($tecnico)
            ->getJson('/api/v1/liquidaciones?por_pagina=2')
            ->assertOk()
            ->json();

        $segunda = $this->identificarseComo($tecnico)
            ->getJson('/api/v1/liquidaciones?por_pagina=2&page=2')
            ->assertOk()
            ->json();

        $this->assertSame(1, $primera['meta']['current_page']);
        $this->assertSame(2, $segunda['meta']['current_page']);
        $this->assertSame(5, $segunda['meta']['total']);

        $idsPrimera = array_column($primera['data'], 'id');
        $idsSegunda = array_column($segunda['data'], 'id');

        $this->assertCount(2, $idsPrimera);
        $this->assertCount(2, $idsSegunda);
        $this->assertSame([], array_intersect($idsPrimera, $idsSegunda));

        // El alias documentado en api.md sigue funcionando.
        $alias = $this->identificarseComo($tecnico)
            ->getJson('/api/v1/liquidaciones?por_pagina=2&pagina=2')
            ->assertOk()
            ->json();

        $this->assertSame(2, $alias['meta']['current_page']);
    }

    // --------------------------------------------------------- utilidades

    /**
     * Actua como $usuario para la proxima peticion HTTP: sin tirar los guards
     * de Sanctum, la segunda peticion del mismo test seguia con el actor
     * anterior.
     */
    private function identificarseComo(User $usuario, string $guard = 'web'): static
    {
        app('auth')->forgetGuards();
        $this->actingAs($usuario, $guard);

        return $this;
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function liquidacionDe(User $autor, array $atributos = []): Liquidacion
    {
        return Liquidacion::factory()->create(array_merge(
            ['usuario_registro_id' => $autor->id],
            $atributos,
        ));
    }

    /** @return array<string, mixed> */
    private function bandeja(User $usuario): array
    {
        return $this->identificarseComo($usuario)
            ->getJson('/api/v1/bandeja')
            ->assertOk()
            ->json('data');
    }

    /**
     * Lo que el recurso le ofrece a $usuario para mover $liquidacion.
     *
     * @return list<string>
     */
    private function disponibles(User $usuario, Liquidacion $liquidacion): array
    {
        return $this->identificarseComo($usuario)
            ->getJson('/api/v1/liquidaciones/'.$liquidacion->id)
            ->assertOk()
            ->json('data.transiciones_disponibles');
    }
}
