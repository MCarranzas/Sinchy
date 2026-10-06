<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * El orden importa: RolPermisosSeeder debe correr antes que AdminSeeder,
     * porque asignar un rol a un usuario falla si el rol todavia no existe.
     */
    public function run(): void
    {
        $this->call([
            RolPermisosSeeder::class,
            AdminSeeder::class,
        ]);
    }
}
