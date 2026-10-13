<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Los cuatro usuarios de ejemplo del flujo de aprobacion (FASE 9.7).
 *
 * `admin` ya lo crea AdminSeeder, y no se duplica: si no existe se avisa y se
 * sigue con los otros tres. Los tres roles del circuito son los que la
 * demostracion necesita para recorrer la maquina de estados de punta a punta
 * (9, criterio de aceptacion):
 *
 *   tecnico  -> crea y envia a revision (y los suyos unicamente, D3)
 *   revisor  -> revisa o rechaza
 *   gerente  -> aprueba o anula, nunca lo suyo (4.2)
 *
 * Contrasena ALEATORIA mostrada una sola vez, igual que en AdminSeeder: una
 * clave de seeder conocida es la forma mas habitual de que un sistema llegue
 * a produccion con una clave publicada. `debe_cambiar_password` queda en
 * false a proposito: son cuentas de DEMOSTRACION, y forzar el cambio en el
 * primer ingreso obligaria a cambiarla tres veces antes de poder enseñar el
 * flujo completo. En produccion ninguna de estas cuentas debe existir.
 */
class UsuariosEjemploSeeder extends Seeder
{
    /**
     * @var list<array{string, string, string}>
     */
    private const CUENTAS = [
        ['admin', 'admin@epcoro.local', 'Administrador del Sistema'],
        ['tecnico', 'tecnico@epcoro.local', 'Tecnico de Captura'],
        ['revisor', 'revisor@epcoro.local', 'Revisor de Liquidaciones'],
        ['gerente', 'gerente@epcoro.local', 'Gerente de Comercializacion'],
    ];

    public function run(): void
    {
        $filas = [];

        foreach (self::CUENTAS as [$username, $correo, $nombre]) {
            if (User::query()->where('username', $username)->exists()) {
                $filas[] = [$username, '(ya existe)', $username];

                continue;
            }

            $password = Str::password(24);

            $usuario = User::query()->create([
                'username' => $username,
                'email' => $correo,
                'nombre_completo' => $nombre,
                'password' => $password,
                'activo' => true,
                'debe_cambiar_password' => false,
            ]);

            $usuario->assignRole($username === 'admin' ? 'admin' : $username);

            $filas[] = [$username, $password, $username];
        }

        $this->command->newLine();
        $this->command->info('Usuarios de ejemplo del flujo de estados:');
        $this->command->table(['usuario', 'contrasena', 'rol'], $filas);
        $this->command->warn('Son contrasenas de DESARROLLO, generadas al azar y mostradas una sola vez.');
        $this->command->warn('No usar ninguna de estas cuentas fuera de esta maquina.');
        $this->command->newLine();
    }
}
