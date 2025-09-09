<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('reservas', function (Blueprint $table) {
            $table->boolean('reportado')->default(false)->after('observaciones');
            $table->unsignedTinyInteger('fallos_reporte')->default(0)->after('reportado');
            $table->string('ultimo_error_reporte', 500)->nullable()->after('fallos_reporte');
        });

        Schema::table('mensualidades', function (Blueprint $table) {
            $table->boolean('reportado')->default(false)->after('estado');
            $table->unsignedTinyInteger('fallos_reporte')->default(0)->after('reportado');
            $table->string('ultimo_error_reporte', 500)->nullable()->after('fallos_reporte');
        });
    }

    public function down(): void
    {
        Schema::table('reservas', function (Blueprint $table) {
            $table->dropColumn(['reportado', 'fallos_reporte', 'ultimo_error_reporte']);
        });
        Schema::table('mensualidades', function (Blueprint $table) {
            $table->dropColumn(['reportado', 'fallos_reporte', 'ultimo_error_reporte']);
        });
    }
};
