<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Crea los 4 roles del diagrama con sus permisos.
 *
 * Los permisos NO se inventan aqui: son los de la seccion 4.2 de
 * instrucciones.txt, que a su vez salen del diagrama draw.io.
 *
 * El seeder es idempotente: se puede volver a ejecutar sin duplicar nada, y
 * sincroniza permisos en vez de anexarlos. Eso importa porque durante el
 * desarrollo los permisos van a cambiar, y un seeder que solo crea lo que
 * falta dejaria permisos viejos activos para siempre.
 */
class RolPermisosSeeder extends Seeder
{
    /**
     * Permiso => modulo.
     *
     * @var array<string, string>
     */
    private const PERMISOS = [
        'liquidaciones.crear' => 'liquidaciones',
        'liquidaciones.ver' => 'liquidaciones',
        'liquidaciones.ver_todas' => 'liquidaciones',
        'liquidaciones.ver_propias' => 'liquidaciones',
        'liquidaciones.editar' => 'liquidaciones',
        'liquidaciones.eliminar' => 'liquidaciones',
        'liquidaciones.enviar_revision' => 'liquidaciones',
        'liquidaciones.revisar' => 'liquidaciones',
        'liquidaciones.rechazar' => 'liquidaciones',
        'liquidaciones.devolver_tecnico' => 'liquidaciones',
        'liquidaciones.devolver_revisor' => 'liquidaciones',
        'liquidaciones.aprobar' => 'liquidaciones',
        'liquidaciones.anular' => 'liquidaciones',
        'liquidaciones.comentar' => 'liquidaciones',
        'reportes.ver' => 'reportes',
        'usuarios.gestionar' => 'administracion',
        'roles.gestionar' => 'administracion',
        'catalogos.gestionar' => 'administracion',
        'parametros.gestionar' => 'administracion',
    ];

    /**
     * Rol => permisos, exactamente los de la seccion 4.2.
     *
     * @var array<string, array<string>>
     */
    private const ROLES = [
        'tecnico' => [
            'liquidaciones.crear',
            'liquidaciones.ver_propias',
            'liquidaciones.editar',
            'liquidaciones.enviar_revision',
            'liquidaciones.ver',
        ],
        'revisor' => [
            'liquidaciones.ver_todas',
            'liquidaciones.revisar',
            'liquidaciones.rechazar',
            'liquidaciones.devolver_tecnico',
            'liquidaciones.comentar',
        ],
        'gerente' => [
            'liquidaciones.ver_todas',
            'liquidaciones.aprobar',
            'liquidaciones.rechazar',
            'liquidaciones.devolver_revisor',
            'liquidaciones.anular',
            'reportes.ver',
        ],
        'admin' => [
            'usuarios.gestionar',
            'roles.gestionar',
            'catalogos.gestionar',
            'parametros.gestionar',
            'reportes.ver',
            'liquidaciones.ver_todas',
            'liquidaciones.anular',
            'liquidaciones.eliminar',
        ],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISOS as $nombre => $modulo) {
            Permission::query()->updateOrCreate(
                ['name' => $nombre],
                ['guard_name' => 'web', 'module' => $modulo],
            );
        }

        // Se eliminan los permisos que ya no esten en la lista de arriba. Sin
        // esto, borrar un permiso del codigo no lo quita de la base y sigue
        // concediendo acceso.
        $vigentes = array_keys(self::PERMISOS);
        Permission::query()
            ->whereNotIn('name', $vigentes)
            ->delete();

        foreach (self::ROLES as $slug => $permisos) {
            $rol = Role::query()->updateOrCreate(
                ['name' => $slug],
                [
                    'guard_name' => 'web',
                    'descripcion' => $this->descripcion($slug),
                ],
            );

            $rol->syncPermissions($permisos);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function descripcion(string $slug): string
    {
        return match ($slug) {
            'tecnico' => 'Captura liquidaciones y las envia a revision.',
            'revisor' => 'Valida tecnicamente, devuelve al tecnico y rechaza.',
            'gerente' => 'Aprueba, devuelve al revisor o rechaza liquidaciones.',
            'admin' => 'Administra usuarios, roles, catalogos y parametros.',
            default => '',
        };
    }
}
