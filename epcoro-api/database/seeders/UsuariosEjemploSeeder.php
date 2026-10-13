<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Usuarios de ejemplo del flujo de aprobacion (FASE 9.7).
 *
 * `admin` ya lo crea AdminSeeder, y no se duplica: si no existe se avisa y se
 * sigue con los demas. Los roles del circuito son los que la demostracion
 * necesita para recorrer la maquina de estados de punta a punta (9, criterio
 * de aceptacion):
 *
 *   tecnico   -> crea y envia a revision (y los suyos unicamente, D3)
 *   tecnico2  -> segundo tecnico del mismo rol: sirve para comprobar que un
 *                tecnico no accede a las liquidaciones del otro (D3)
 *   revisor   -> revisa o rechaza
 *   gerente   -> aprueba o anula, nunca lo suyo (4.2)
 *
 * En desarrollo las contrasenas son las de usuarios_prueba.txt, fijas y
 * conocidas, para no depender de un volcado por consola. Fuera de desarrollo se
 * genera una aleatoria igual que en AdminSeeder: una clave de seeder conocida
 * es la forma mas habitual de que un sistema llegue a produccion con una clave
 * publicada. `debe_cambiar_password` queda en false a proposito: son cuentas de
 * DEMOSTRACION, y forzar el cambio en el primer ingreso obligaria a cambiarla
 * tres veces antes de poder enseñar el flujo completo. En produccion ninguna de
 * estas cuentas debe existir.
 */
class UsuariosEjemploSeeder extends Seeder
{
    /**
     * [usuario, correo, nombre, rol]
     *
     * @var list<array{string, string, string, string}>
     */
    private const CUENTAS = [
        ['admin', 'admin@sinchiwayra.local', 'Administrador del Sistema', 'admin'],
        ['tecnico', 'tecnico@sinchiwayra.local', 'Tecnico de Captura', 'tecnico'],
        ['tecnico2', 'tecnico2@sinchiwayra.local', 'Tecnico de Captura (2)', 'tecnico'],
        ['revisor', 'revisor@sinchiwayra.local', 'Revisor de Liquidaciones', 'revisor'],
        ['gerente', 'gerente@sinchiwayra.local', 'Gerente de Comercializacion', 'gerente'],
    ];

    /**
     * Contrasenas de desarrollo por usuario. Son las de usuarios_prueba.txt.
     *
     * @var array<string, string>
     */
    private const PASSWORDS_DESARROLLO = [
        'admin' => 'Admin123',
        'tecnico' => 'Tecnico123',
        'tecnico2' => 'tecnico2',
        'revisor' => 'Revisor123',
        'gerente' => 'Gerente123',
    ];

    public function run(): void
    {
        $produccion = app()->environment('production');
        $filas = [];

        foreach (self::CUENTAS as [$username, $correo, $nombre, $rol]) {
            if (User::query()->where('username', $username)->exists()) {
                $filas[] = [$username, '(ya existe)', $rol];

                continue;
            }

            $password = $produccion
                ? Str::password(24)
                : self::PASSWORDS_DESARROLLO[$username];

            $usuario = User::query()->create([
                'username' => $username,
                'email' => $correo,
                'nombre_completo' => $nombre,
                'password' => $password,
                'activo' => true,
                'debe_cambiar_password' => false,
            ]);

            $usuario->assignRole($rol);

            $filas[] = [$username, $password, $rol];
        }

        $this->command->newLine();
        $this->command->info('Usuarios de ejemplo del flujo de estados:');
        $this->command->table(['usuario', 'contrasena', 'rol'], $filas);

        if ($produccion) {
            $this->command->warn('Son contrasenas de PRODUCCION, generadas al azar y mostradas una sola vez.');
        } else {
            $this->command->warn('Son las contrasenas de DESARROLLO de usuarios_prueba.txt.');
        }

        $this->command->warn('No usar ninguna de estas cuentas fuera de esta maquina.');
        $this->command->newLine();
    }
}
