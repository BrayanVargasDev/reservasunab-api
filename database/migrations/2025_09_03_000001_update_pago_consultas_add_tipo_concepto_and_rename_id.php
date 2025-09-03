<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pago_consultas', function (Blueprint $table) {
            if (Schema::hasColumn('pago_consultas', 'id_reserva')) {
                $table->renameColumn('id_reserva', 'id_concepto');
            }
            if (!Schema::hasColumn('pago_consultas', 'tipo_concepto')) {
                $table->string('tipo_concepto', 30)->nullable()->after('moneda');
            }
        });

        Schema::table('mensualidades', function (Blueprint $table) {
            if (!Schema::hasColumn('mensualidades', 'id_espacio')) {
                $table->foreignId('id_espacio')->after('id')->constrained('espacios');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pago_consultas', function (Blueprint $table) {
            if (Schema::hasColumn('pago_consultas', 'tipo_concepto')) {
                $table->dropColumn('tipo_concepto');
            }
            if (Schema::hasColumn('pago_consultas', 'id_concepto')) {
                $table->renameColumn('id_concepto', 'id_reserva');
            }
        });

        Schema::table('mensualidades', function (Blueprint $table) {
            if (Schema::hasColumn('mensualidades', 'id_espacio')) {
                $table->dropForeign(['id_espacio']);
                $table->dropColumn('id_espacio');
            }
        });
    }
};
