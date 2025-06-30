<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        Schema::create('tipos_documento', function (Blueprint $table) {
            $table->id('id_tipo');
            $table->string('codigo', 10)->unique();
            $table->string('nombre', 50);
            $table->boolean('activo')->default(true);
        });

        // Insertar los valores iniciales
        DB::table('tipos_documento')->insert([
            ['codigo' => 'CC', 'nombre' => 'Cédula de Ciudadanía'],
            ['codigo' => 'TI', 'nombre' => 'Tarjeta de Identidad'],
            ['codigo' => 'CE', 'nombre' => 'Cédula de Extranjería'],
            ['codigo' => 'PAS', 'nombre' => 'Pasaporte']
        ]);
    }

    public function down()
    {
        Schema::dropIfExists('tipos_documento');
    }
};