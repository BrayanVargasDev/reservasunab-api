<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('categorias', function (Blueprint $table) {
            $table->integer('reservas_estudiante')->default(0)->after('id_grupo');
            $table->integer('reservas_administrativo')->default(0)->after('reservas_estudiante');
            $table->integer('reservas_egresado')->default(0)->after('reservas_administrativo');
            $table->integer('reservas_externo')->default(0)->after('reservas_egresado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categorias', function (Blueprint $table) {
            $table->dropColumn([
                'reservas_estudiante',
                'reservas_administrativo',
                'reservas_egresado',
                'reservas_externo',
            ]);
        });
    }
};
