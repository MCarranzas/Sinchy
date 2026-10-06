<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Crea el usuario administrador inicial.
 *
 * La contrasena es ALEATORIA y se muestra una sola vez por consola. No se usa
 * "admin123" ni nada fijo: una contrasena de seeder conocida es la forma mas
 * habitual de que un sistema llegue a produccion con una clave publicada, y
 * en una app que autoriza pagos eso no es un detalle menor.
 *
 * El usuario queda con debe_cambiar_password = true, asi que el sistema le
 * obliga a cambiarla en el primer login aunque nadie se la cambie a mano.
 */
class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $username = 'admin';

        if (User::query()->where('username', $username)->exists()) {
            $this->command->warn("El usuario '{$username}' ya existe. No se modifico.");

            return;
        }

        $password = Str::password(24);

        $usuario = User::query()->create([
            'username' => $username,
            'email' => 'admin@epcoro.local',
            'nombre_completo' => 'Administrador del Sistema',
            'password' => $password,
            'activo' => true,
            'debe_cambiar_password' => true,
        ]);

        $usuario->assignRole('admin');

        $this->command->newLine();
        $this->command->info('Usuario administrador creado.');
        $this->command->table(
            ['usuario', 'contrasena', 'cambiar al primer ingreso'],
            [[$usuario->username, $password, 'si']],
        );
        $this->command->warn('Esta contrasena no se vuelve a mostrar. Guardela ahora.');
        $this->command->warn('Si se ejecuta fuera de artisan se pierde: corre php artisan db:seed.');
        $this->command->newLine();
    }
}
