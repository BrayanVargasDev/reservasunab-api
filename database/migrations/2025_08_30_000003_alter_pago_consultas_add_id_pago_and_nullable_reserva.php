<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pago_consultas', function (Blueprint $table) {
            if (!Schema::hasColumn('pago_consultas', 'id_pago')) {
                $table->string('id_pago', 26)->nullable()->after('moneda');
                $table->foreign('id_pago')->references('codigo')->on('pagos')->nullOnDelete();
            }
        });

        // cambiar a nullable en Postgres sin DBAL para id_reserva
        if (Schema::hasColumn('pago_consultas', 'id_reserva')) {
            DB::statement('ALTER TABLE pago_consultas ALTER COLUMN id_reserva DROP NOT NULL');
        }
    }

    public function down(): void
    {
        Schema::table('pago_consultas', function (Blueprint $table) {
            if (Schema::hasColumn('pago_consultas', 'id_pago')) {
                $table->dropForeign(['id_pago']);
                $table->dropColumn('id_pago');
            }
        });

        if (Schema::hasColumn('pago_consultas', 'id_reserva')) {
            DB::statement('ALTER TABLE pago_consultas ALTER COLUMN id_reserva SET NOT NULL');
        }
    }
};
