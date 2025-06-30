<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SedeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $sedes = [
            ['nombre' => 'CSU', 'direccion' => 'Calle Principal 123', 'telefono' => '123456789', 'creado_por' => 2],
            ['nombre' => 'Jardín', 'direccion' => 'Avenida Norte 456', 'telefono' => '987654321', 'creado_por' => 2],
            ['nombre' => 'Bosque', 'direccion' => 'Calle Sur 789', 'telefono' => '456789123', 'creado_por' => 2],
        ];

        DB::table('sedes')->insert($sedes);
    }
}
