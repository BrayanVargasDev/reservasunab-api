<?php

namespace Database\Seeders;

use App\Models\Usuario;
use Illuminate\Database\Seeder;

class UsuarioSeeder extends Seeder
{
    public function run()
    {
        // Crear un usuario administrativo
        Usuario::factory()->create();
    }
}
