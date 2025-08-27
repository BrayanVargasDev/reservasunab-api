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
        Schema::table('movimientos', function (Blueprint $table) {
            $table->unsignedBigInteger('id_movimiento_principal')->nullable()->after('id_reserva');
            // Índice y clave foránea opcional (auto-referencial)
            $table->foreign('id_movimiento_principal')
                ->references('id')
                ->on('movimientos')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('movimientos', function (Blueprint $table) {
            $table->dropForeign(['id_movimiento_principal']);
            $table->dropColumn('id_movimiento_principal');
        });
    }
};
