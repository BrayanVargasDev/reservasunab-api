<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $pantallas = [
            [
                'nombre' => 'Home',
                'descripcion' => 'Pantalla principal del sistema',
                'codigo' => 'HOME',
                'orden' => 1,
                'ruta' => '/home',
                'icono' => 'bar-chart-outline',
                'visible' => true,
                'creado_en' => now(),
            ],
            [
                'nombre' => 'Usuarios',
                'descripcion' => 'Gestión de usuarios del sistema',
                'codigo' => 'USUARIOS',
                'orden' => 5,
                'ruta' => '/usuarios',
                'icono' => 'people-outline',
                'visible' => true,
                'creado_en' => now(),
            ],
            [
                'nombre' => 'Permisos',
                'descripcion' => 'Gestión de permisos y roles',
                'codigo' => 'PERMISOS',
                'orden' => 6,
                'ruta' => '/permisos',
                'icono' => 'key-outline',
                'visible' => true,
                'creado_en' => now(),
            ],
            [
                'nombre' => 'Espacios',
                'descripcion' => 'Gestión de espacios y salas',
                'codigo' => 'ESPACIOS',
                'orden' => 3,
                'ruta' => '/espacios',
                'icono' => 'business-outline',
                'visible' => true,
                'creado_en' => now(),
            ],
            [
                'nombre' => 'Pagos',
                'descripcion' => 'Gestión de pagos y facturación',
                'codigo' => 'PAGOS',
                'orden' => 4,
                'ruta' => '/pagos',
                'icono' => 'card-outline',
                'visible' => true,
                'creado_en' => now(),
            ],
            [
                'nombre' => 'Reservas',
                'descripcion' => 'Gestión de reservas de espacios',
                'codigo' => 'RESERVAS',
                'orden' => 2,
                'ruta' => '/reservas',
                'icono' => 'calendar-outline',
                'visible' => true,
                'creado_en' => now(),
            ],
        ];

        foreach ($pantallas as $pantalla) {
            DB::table('pantallas')->insertOrIgnore($pantalla);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $codigos = ['HOME', 'USUARIOS', 'PERMISOS', 'ESPACIOS', 'PAGOS', 'RESERVAS'];

        DB::table('pantallas')->whereIn('codigo', $codigos)->delete();
    }
};
