<?php

namespace Database\Seeders;

use App\Models\Persona;
use Illuminate\Database\Seeder;

class PersonaSeeder extends Seeder
{
    public function run()
    {
        // Crear 10 personas por defecto
        Persona::factory()->count(10)->create();
    }
}
