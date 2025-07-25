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
            $table->integer('reservas_estudiante')->default(1);
            $table->integer('reservas_administrativo')->default(1);
            $table->integer('reservas_egresado')->default(1);
            $table->integer('reservas_externo')->default(1);
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
