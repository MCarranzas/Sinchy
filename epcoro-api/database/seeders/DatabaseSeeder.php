<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * El orden importa:
     *  - RolPermisosSeeder antes que AdminSeeder, porque asignar un rol a un
     *    usuario falla si el rol todavia no existe.
     *  - Los catalogos antes que cualquier seeder que los referencie.
     *  - ParametrosSeeder al final: es el que deja listo el formulario.
     */
    public function run(): void
    {
        $this->call([
            RolPermisosSeeder::class,
            CatalogoGeograficoSeeder::class,
            PresentacionesSeeder::class,
            ParametrosSeeder::class,
            AdminSeeder::class,
        ]);
    }
}
