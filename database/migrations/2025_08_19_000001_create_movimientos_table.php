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
        Schema::create('movimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_usuario')->constrained('usuarios', 'id_usuario');
            $table->foreignId('id_reserva')->constrained('reservas');
            $table->dateTime('fecha');
            $table->decimal('valor', 15, 2)->default(0.00);
            // ingreso, egreso, ajuste
            $table->enum('tipo', ['ingreso', 'egreso', 'ajuste']);
            $table->timestamp('creado_en')->useCurrent();
            $table->foreignId('creado_por')->constrained('usuarios', 'id_usuario');
            $table->timestamp('actualizado_en')->useCurrent()->nullable();
            $table->softDeletes('eliminado_en');
        });

        Schema::table('movimientos', function (Blueprint $table) {
            $table->index('id_usuario');
            $table->index('id_reserva');
            $table->index('tipo');
            $table->index('fecha');
            $table->index('creado_en');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('movimientos');
    }
};
