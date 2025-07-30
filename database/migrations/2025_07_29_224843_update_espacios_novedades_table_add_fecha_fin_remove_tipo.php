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
        Schema::table('espacios_novedades', function (Blueprint $table) {
            $table->date('fecha_fin')->after('fecha');
            $table->dropColumn('tipo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('espacios_novedades', function (Blueprint $table) {
            $table->string('tipo', 50)->default('mantenimiento');
            $table->dropColumn('fecha_fin');
        });
    }
};
