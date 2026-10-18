<?php

namespace Tests\Feature;

use App\Models\Liquidacion;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * La cuarta capa de defensa de la maquina de estados.
 *
 * Dos grupos de pruebas: D3 (quien ve que) y D2 (que ya no se toca nada que
 * este aprobado, el administrador incluido). Cada caso es una frase del
 * documento, no una invencion de este archivo.
 */
class LiquidacionPolicyTest extends AuthTestCase
{
    // ------------------------------------------------------------- D3 ver

    public function test_el_tecnico_ve_solo_sus_las_suyas(): void
    {
        $tecnico = $this->usuarioConRol('tecnico');
        $otroTecnico = $this->usuarioConRol('tecnico');

        $propia = $this->liquidacionDe($tecnico);
        $ajena = $this->liquidacionDe($otroTecnico);

        $this->assertTrue(Gate::forUser($tecnico)->allows('viewAny', Liquidacion::class));
        $this->assertTrue(Gate::forUser($tecnico)->allows('view', $propia));
        $this->assertFalse(Gate::forUser($tecnico)->allows('view', $ajena));
    }

    public function test_el_gerente_ve_las_ajenas(): void
    {
        $gerente = $this->usuarioConRol('gerente');
        $ajena = $this->liquidacionDe($this->usuarioConRol('tecnico'));

        $this->assertTrue(Gate::forUser($gerente)->allows('view', $ajena));
        $this->assertTrue(Gate::forUser($gerente)->allows('viewAny', Liquidacion::class));
    }

    public function test_el_revisor_ve_las_ajenas_y_las_suyas(): void
    {
        $revisor = $this->usuarioConRol('revisor');
        $ajena = $this->liquidacionDe($this->usuarioConRol('tecnico'));
        $suya = $this->liquidacionDe($revisor);

        $this->assertTrue(Gate::forUser($revisor)->allows('view', $ajena));
        $this->assertTrue(Gate::forUser($revisor)->allows('view', $suya));
    }

    public function test_el_administrador_ve_todo(): void
    {
        $admin = $this->usuarioConRol('admin');
        $ajena = $this->liquidacionDe($this->usuarioConRol('tecnico'));

        $this->assertTrue(Gate::forUser($admin)->allows('view', $ajena));
        $this->assertTrue(Gate::forUser($admin)->allows('viewAny', Liquidacion::class));
    }

    public function test_un_usuario_sin_rol_no_ve_nada_ni_lo_suyo(): void
    {
        $sinRol = User::factory()->create();
        $suya = $this->liquidacionDe($sinRol);

        $this->assertFalse(Gate::forUser($sinRol)->allows('viewAny', Liquidacion::class));
        $this->assertFalse(Gate::forUser($sinRol)->allows('view', $suya));
    }

    // ------------------------------------------------------- D2 aprobado

    /**
     * D2: aprobado es terminal e inmutable para TODOS. Ni siquiera el
     * administrador, que es quien mas permisos tiene.
     */
    public function test_nadie_toca_una_liquidacion_aprobada_ni_el_administrador(): void
    {
        $aprobada = Liquidacion::factory()->aprobada()->create();

        foreach (['admin', 'gerente', 'revisor', 'tecnico'] as $rol) {
            $usuario = $this->usuarioConRol($rol);

            foreach (['update', 'delete', 'enviarRevision', 'revisar', 'aprobar', 'rechazar', 'anular', 'devolverTecnico', 'devolverRevisor'] as $habilidad) {
                $this->assertFalse(
                    Gate::forUser($usuario)->allows($habilidad, $aprobada),
                    'El rol '.$rol.' no debe poder '.$habilidad.' una aprobada',
                );
            }
        }
    }

    public function test_una_liquidacion_aprobada_tampoco_es_editable(): void
    {
        $tecnico = $this->usuarioConRol('tecnico');
        $aprobada = $this->liquidacionDe($tecnico, ['estado' => 'aprobado']);

        $this->assertFalse(Gate::forUser($tecnico)->allows('update', $aprobada));
    }

    // ------------------------------------------- D2 separacion de roles

    public function test_el_tecnico_no_aproba_ni_la_suya_ni_la_ajena(): void
    {
        $tecnico = $this->usuarioConRol('tecnico');

        // Ni siquiera con la liquidacion en el estado exacto en el que la
        // aprobacion seria valida: el corte es el permiso, no el estado.
        $revisada = Liquidacion::factory()->create(['estado' => 'revisado']);

        $this->assertFalse(Gate::forUser($tecnico)->allows('aprobar', $revisada));
    }

    public function test_el_gerente_no_aproba_la_suya(): void
    {
        $gerente = $this->usuarioConRol('gerente');
        $suya = Liquidacion::factory()->create([
            'usuario_registro_id' => $gerente->id,
            'estado' => 'revisado',
        ]);
        $ajena = Liquidacion::factory()->create(['estado' => 'revisado']);

        $this->assertFalse(Gate::forUser($gerente)->allows('aprobar', $suya));
        $this->assertTrue(Gate::forUser($gerente)->allows('aprobar', $ajena));
    }

    // ------------------------------------------------------ edicion D3

    public function test_el_tecnico_edita_solo_suyas_y_solo_en_creado_o_en_correccion(): void
    {
        $tecnico = $this->usuarioConRol('tecnico');
        $otroTecnico = $this->usuarioConRol('tecnico');

        $suya = $this->liquidacionDe($tecnico);
        $ajena = $this->liquidacionDe($otroTecnico);
        $enRevision = $this->liquidacionDe($tecnico, ['estado' => 'en_revision']);
        $enCorreccion = $this->liquidacionDe($tecnico, ['estado' => 'en_correccion']);

        $this->assertTrue(Gate::forUser($tecnico)->allows('update', $suya));
        $this->assertFalse(Gate::forUser($tecnico)->allows('update', $ajena));
        $this->assertFalse(Gate::forUser($tecnico)->allows('update', $enRevision));
        $this->assertTrue(Gate::forUser($tecnico)->allows('update', $enCorreccion));
    }

    // ------------------------------------------------------ transiciones

    public function test_el_tecnico_envia_a_revision_solo_lo_suyo_y_desde_creado(): void
    {
        $tecnico = $this->usuarioConRol('tecnico');
        $otroTecnico = $this->usuarioConRol('tecnico');

        $suya = $this->liquidacionDe($tecnico);
        $ajena = $this->liquidacionDe($otroTecnico);
        $yaEnviada = $this->liquidacionDe($tecnico, ['estado' => 'en_revision']);

        $this->assertTrue(Gate::forUser($tecnico)->allows('enviarRevision', $suya));
        $this->assertFalse(Gate::forUser($tecnico)->allows('enviarRevision', $ajena));
        $this->assertFalse(Gate::forUser($tecnico)->allows('enviarRevision', $yaEnviada));
    }

    public function test_el_revisor_valida_solo_lo_que_esta_en_revision(): void
    {
        $revisor = $this->usuarioConRol('revisor');

        $enRevision = Liquidacion::factory()->create(['estado' => 'en_revision']);
        $creada = Liquidacion::factory()->create(['estado' => 'creado']);

        $this->assertTrue(Gate::forUser($revisor)->allows('revisar', $enRevision));
        $this->assertFalse(Gate::forUser($revisor)->allows('revisar', $creada));
    }

    public function test_solo_el_revisor_devuelve_al_tecnico_desde_en_revision(): void
    {
        $revisor = $this->usuarioConRol('revisor');
        $gerente = $this->usuarioConRol('gerente');

        $enRevision = Liquidacion::factory()->create(['estado' => 'en_revision']);
        $revisada = Liquidacion::factory()->create(['estado' => 'revisado']);

        // El revisor devuelve al tecnico (en_revision -> en_correccion).
        $this->assertTrue(Gate::forUser($revisor)->allows('devolverTecnico', $enRevision));
        // Pero no devuelve al revisor: eso es del gerente.
        $this->assertFalse(Gate::forUser($revisor)->allows('devolverRevisor', $revisada));
        // El gerente no devuelve al tecnico: le falta devolver_tecnico.
        $this->assertFalse(Gate::forUser($gerente)->allows('devolverTecnico', $enRevision));
        // Ni debe poder devolver algo que no esta en el estado que toca.
        $this->assertFalse(Gate::forUser($revisor)->allows('devolverTecnico', $revisada));
    }

    public function test_solo_el_gerente_devuelve_al_revisor_desde_revisado(): void
    {
        $gerente = $this->usuarioConRol('gerente');
        $revisor = $this->usuarioConRol('revisor');

        $revisada = Liquidacion::factory()->create(['estado' => 'revisado']);
        $enRevision = Liquidacion::factory()->create(['estado' => 'en_revision']);

        // El gerente devuelve al revisor (revisado -> en_revision).
        $this->assertTrue(Gate::forUser($gerente)->allows('devolverRevisor', $revisada));
        // El revisor no tiene devolver_revisor.
        $this->assertFalse(Gate::forUser($revisor)->allows('devolverRevisor', $revisada));
        // Ni el gerente devuelve algo que no este `revisado`: la fluidez de
        // `en_revision` no es camino para esta habilidad.
        $this->assertFalse(Gate::forUser($gerente)->allows('devolverRevisor', $enRevision));
    }

    public function test_el_gerente_anula_lo_que_no_esta_aprobado(): void
    {
        $gerente = $this->usuarioConRol('gerente');

        $enRevision = Liquidacion::factory()->create(['estado' => 'en_revision']);
        $aprobada = Liquidacion::factory()->aprobada()->create();

        $this->assertTrue(Gate::forUser($gerente)->allows('anular', $enRevision));
        $this->assertFalse(Gate::forUser($gerente)->allows('anular', $aprobada));
    }

    public function test_solo_el_autor_elimina_y_solo_en_creado(): void
    {
        $admin = $this->usuarioConRol('admin');

        $creada = $this->liquidacionDe($admin);
        $ajena = $this->liquidacionDe($this->usuarioConRol('tecnico'));
        $enviada = $this->liquidacionDe($admin, ['estado' => 'en_revision']);

        // "creado --eliminar--> eliminado (solo el autor, antes de enviar)".
        $this->assertTrue(Gate::forUser($admin)->allows('delete', $creada));
        $this->assertFalse(Gate::forUser($admin)->allows('delete', $ajena));
        $this->assertFalse(Gate::forUser($admin)->allows('delete', $enviada));
    }

    public function test_solo_el_revisor_comenta(): void
    {
        $revisor = $this->usuarioConRol('revisor');
        $tecnico = $this->usuarioConRol('tecnico');
        $ajena = $this->liquidacionDe($this->usuarioConRol('tecnico'));

        $this->assertTrue(Gate::forUser($revisor)->allows('comentar', $ajena));
        $this->assertFalse(Gate::forUser($tecnico)->allows('comentar', $ajena));
    }

    // --------------------------------------------------------- utilidades

    /**
     * Crea la liquidacion ya con el autor puesto: el factory no puede saber
     * quien va a ser el dueño del caso de prueba.
     *
     * @param  array<string, mixed>  $atributos
     */
    private function liquidacionDe(User $autor, array $atributos = []): Liquidacion
    {
        return Liquidacion::factory()->create(array_merge(
            ['usuario_registro_id' => $autor->id],
            $atributos,
        ));
    }

    private function usuarioConRol(string $rol): User
    {
        return User::factory()->conRol($rol)->create();
    }
}
