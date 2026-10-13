<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Crea el usuario administrador inicial.
 *
 * En desarrollo la contrasena es la de usuarios_prueba.txt (admin / Admin123),
 * fija y conocida, para poder entrar sin depender de un volcado por consola.
 *
 * Fuera de desarrollo sigue siendo ALEATORIA y se muestra una sola vez: una
 * contrasena de seeder conocida es la forma mas habitual de que un sistema
 * llegue a produccion con una clave publicada, y en una app que autoriza pagos
 * eso no es un detalle menor.
 *
 * La bandera debe_cambiar_password acompaña esa decision: en produccion queda
 * en true para forzar el cambio en el primer login; en desarrollo queda en
 * false, porque la clave ya se conoce y la pantalla de cambio obligatorio solo
 * estorbaria la revision manual.
 */
class AdminSeeder extends Seeder
{
    /** Contrasena de desarrollo. Es la de usuarios_prueba.txt. */
    private const PASSWORD_DESARROLLO = 'Admin123';

    public function run(): void
    {
        $username = 'admin';

        if (User::query()->where('username', $username)->exists()) {
            $this->command->warn("El usuario '{$username}' ya existe. No se modifico.");

            return;
        }

        $produccion = app()->environment('production');
        $password = $produccion ? Str::password(24) : self::PASSWORD_DESARROLLO;

        $usuario = User::query()->create([
            'username' => $username,
            'email' => 'admin@epcoro.local',
            'nombre_completo' => 'Administrador del Sistema',
            'password' => $password,
            'activo' => true,
            'debe_cambiar_password' => $produccion,
        ]);

        $usuario->assignRole('admin');

        $this->command->newLine();
        $this->command->info('Usuario administrador creado.');
        $this->command->table(
            ['usuario', 'contrasena', 'cambiar al primer ingreso'],
            [[$usuario->username, $password, $produccion ? 'si' : 'no']],
        );

        if ($produccion) {
            $this->command->warn('Esta contrasena no se vuelve a mostrar. Guardela ahora.');
            $this->command->warn('Si se ejecuta fuera de artisan se pierde: corre php artisan db:seed.');
        } else {
            $this->command->warn('Contrasena de DESARROLLO (usuarios_prueba.txt). No usar en produccion.');
        }

        $this->command->newLine();
    }
}
