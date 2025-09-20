<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        $this->call([
            // RolSeeder::class,
            // UsuarioSeeder::class,
            // PersonaSeeder::class,
            // SedeSeeder::class,
            // CategoriaSeeder::class,
            PermisoSeeder::class,
        ]);
    }
}
