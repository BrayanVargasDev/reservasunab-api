<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            if (Schema::hasColumn('pagos', 'id_reserva')) {
                $table->dropForeign(['id_reserva']);
                $table->dropColumn('id_reserva');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            if (!Schema::hasColumn('pagos', 'id_reserva')) {
                $table->foreignId('id_reserva')->nullable()->constrained('reservas');
            }
        });
    }
};
