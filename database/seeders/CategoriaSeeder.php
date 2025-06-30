<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategoriaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $grupos = [
            ['nombre' => 'deportivo y cultural', 'creado_por' => 2],
            ['nombre' => 'académico y administrativo', 'creado_por' => 2],
        ];

        DB::table('grupos')->insert($grupos);

        $categorias = [
            ['nombre' => 'cancha', 'creado_por' => 2, 'id_grupo' => 1],
            ['nombre' => 'otros espacios', 'creado_por' => 2, 'id_grupo' => 1],
            ['nombre' => 'musica', 'creado_por' => 2, 'id_grupo' => 2],
            ['nombre' => 'biblioteca', 'creado_por' => 2, 'id_grupo' => 2],
            ['nombre' => 'sala de reuniones', 'creado_por' => 2, 'id_grupo' => 2],
            ['nombre' => 'auditorios', 'creado_por' => 2, 'id_grupo' => 2],
        ];

        DB::table('categorias')->insert($categorias);
    }
}
